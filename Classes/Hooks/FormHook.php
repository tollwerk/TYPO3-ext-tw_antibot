<?php

/**
 * tollwerk
 *
 * @category   Tollwerk
 * @package    Tollwerk\TwAntibot
 * @subpackage Tollwerk\TwAntibot\Hooks
 * @author     Joschi Kuphal <joschi@tollwerk.de>
 * @copyright  Copyright © 2026 Joschi Kuphal <joschi@tollwerk.de>
 * @license    http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Hooks;

use Jkphl\Antibot\Domain\Contract\ValidationResultInterface;
use Jkphl\Antibot\Ports\Antibot;
use Jkphl\Antibot\Ports\Validators\BanValidator;
use Jkphl\Antibot\Ports\Validators\HmacValidator;
use Jkphl\Antibot\Ports\Validators\DuplicateValidator;
use Jkphl\Antibot\Ports\Validators\HoneypotValidator;
use Jkphl\Antibot\Ports\Validators\ParameterEqualityValidator;
use Jkphl\Antibot\Ports\Validators\ParameterLinkValidator;
use Jkphl\Antibot\Ports\Validators\ParameterPlausibilityValidator;
use Jkphl\Antibot\Ports\Validators\ParameterSetValidator;
use Jkphl\Antibot\Ports\Validators\ParameterStopwordValidator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Tollwerk\TwAntibot\Store\BlacklistStore;
use Tollwerk\TwAntibot\Store\CacheStore;
use Tollwerk\TwAntibot\Store\StopwordList;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use Jkphl\Antibot\Infrastructure\Model\ParameterPath;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\Renderable\CompositeRenderableInterface;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RootRenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * Form Rendering Hook
 *
 * Wraps the rendering of a protected form. Every way a form can reach the frontend —
 * the form content element, a `form:render` view helper, a form built by a factory in
 * an extension of its own — ends in FluidFormRenderer::render(), and that is where this
 * hook is called. It is therefore the one place that covers all of them.
 *
 * The renderer resolves the template name *after* the hook has run, so exchanging it
 * here replaces the entire output of the form. The block template lives in a path of
 * this extension that carries nothing else, so the templates of the surrounding project
 * keep precedence for everything but that one name.
 */
class FormHook implements SingletonInterface, LoggerAwareInterface
{
    use LoggerAwareTrait;

    /**
     * Template rendered instead of a form the client may not use
     *
     * @var string
     */
    const TEMPLATE_BLOCKED = 'AntibotBlocked';
    /**
     * Type of the armor fields that stay invisible
     *
     * @var string
     */
    const TYPE_HIDDEN = 'hidden';
    /**
     * Rendering option a form declares itself protected with
     *
     * @var string
     */
    const OPTION = 'antibot';
    /**
     * Rendering option the block template reads the contact address from
     *
     * @var string
     */
    const OPTION_CONTACT = 'antibotContact';
    /**
     * Rendering option the block template reads its classes from
     *
     * @var string
     */
    const OPTION_CLASS = 'antibotClass';

    /**
     * Key the expiry date is registered under
     *
     * `config.additionalHeaders` is a numbered list, and the entries are applied in the
     * order of their keys. Keys of their own keep the two apart from whatever a project
     * has configured there.
     */
    const HEADER_KEY = '900.';

    /**
     * Key the expiry date is registered under
     */
    const HEADER_KEY_EXPIRES = '901.';
    /**
     * Configuration a form falls back to when it just says "yes"
     *
     * @var array
     */
    const DEFAULT_CONFIGURATION = [
        'honeypots'    => ['email' => 'email', 'homepage' => 'url'],
        'methods'      => null,
        'times'        => null,
        'ban'          => true,
        'plausibility' => null,
        'links'        => null,
        'equality'     => null,
        'parameterSet' => null,
        'duplicates'   => null,
        'stopwords'    => null,
    ];
    /**
     * Validation results, indexed by form identifier
     *
     * @var ValidationResultInterface[]
     */
    protected $results = [];
    /**
     * Antibot instances, indexed by form identifier
     *
     * @var Antibot[]
     */
    protected $instances = [];
    /**
     * Identifiers of the forms already armed in this request
     *
     * @var bool[]
     */
    protected $armed = [];
    /**
     * Ban validators, indexed by form identifier
     *
     * @var BanValidator[]
     */
    protected $banValidators = [];
    /**
     * Duplicate validators and their lifetimes, indexed by form identifier
     *
     * @var array[]
     */
    protected $duplicates = [];
    /**
     * Settings of this extension, as the installation configured them
     *
     * @var array|null
     */
    protected $settings = null;

    /**
     * Constructor
     *
     * TYPO3 replaces the logger when it instantiates this hook. The fallback stands for
     * the case where someone constructs it directly.
     */
    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * Add the armor to a form that has just been built
     *
     * This is the moment the form definition is complete and the runtime does not exist
     * yet — late enough to know the form, early enough for added elements to take part
     * in everything that follows.
     *
     * Contrary to what the name suggests, the hook is called once per renderable rather
     * than once per form, and the form definition is not among them: the factory starts
     * the walk at the first page. The armor therefore hangs itself on that page, once.
     *
     * @param RenderableInterface $renderable Renderable
     */
    public function afterBuildingFinished(RenderableInterface $renderable): void
    {
        if (!$renderable instanceof Page) {
            return;
        }

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;

        // The form editor builds forms too, and an armor would only be in its way
        if (($request === null) || !ApplicationType::fromRequest($request)->isFrontend()) {
            return;
        }

        $form = $renderable->getRootForm();
        if (!$form instanceof FormDefinition) {
            return;
        }

        $configuration = $this->getConfiguration($form);
        $identifier    = $form->getIdentifier();
        if (($configuration === null) || isset($this->armed[$identifier])) {
            return;
        }
        $this->armed[$identifier] = true;
        $this->keepOutOfCaches();

        foreach ($this->getAntibot($identifier, $configuration)->armorInputs($request) as $input) {
            $this->createArmorField($renderable, $input->getAttributes());
        }
    }

    /**
     * Keep the response out of the caches that would freeze an armor
     *
     * An armor belongs to one session and carries the second the form was served. A
     * response holding it therefore has to be built anew every time: a cached page hands
     * the first visitor's armor to everyone after them, and keeps handing it out until
     * it has aged past the submission window — from then on the form turns everybody
     * away. A ban imposed in the meantime would go unseen for just as long.
     *
     * Declining the page cache settles both halves. TYPO3 answers a request it could not
     * cache with `private, no-store`, so browsers, proxies and service workers keep their
     * hands off the page as well.
     *
     * Where the form is rendered by an uncached object anyway this costs nothing: by then
     * the page has long been written to the cache, and the header it earns is the one it
     * would have carried regardless.
     *
     * The two headers are spelled out rather than left to TYPO3, which sends cache
     * headers of its own only where `config.sendCacheHeaders` asks it to. The refusal
     * must not depend on that.
     *
     * The expiry date is there for the web server rather than for the visitor. A server
     * configured to give PHP output a default lifetime — Apache's `ExpiresDefault` is the
     * common case — grants it to every response that does not name one itself, `no-store`
     * or not, and the day-long `max-age` it writes lands on top of the refusal. A date in
     * the past is the one answer such a rule leaves alone.
     */
    protected function keepOutOfCaches(): void
    {
        $controller = $GLOBALS['TSFE'] ?? null;
        if (!$controller instanceof TypoScriptFrontendController) {
            return;
        }

        $controller->set_no_cache('A form armored against bots must not be served from a cache', true);
        $controller->config['config']['additionalHeaders.'][self::HEADER_KEY] = [
            'header' => 'Cache-Control: private, no-store',
        ];
        $controller->config['config']['additionalHeaders.'][self::HEADER_KEY_EXPIRES] = [
            'header' => 'Expires: Thu, 01 Jan 1970 00:00:00 GMT',
        ];
    }

    /**
     * Note the content of a submission that has run its course
     *
     * Recognizing repeated content takes two halves: the check, which sits among the
     * validators, and the note taken once a submission has been accepted. This is the
     * second half, and this hook is where it fits.
     *
     * A current page of NULL means the runtime is past the last one: everything mapped,
     * everything validated, the finishers about to run. A finisher of our own would
     * have been the obvious place, but it can only be appended — and the notification
     * finisher of a form redirects and cancels the chain, so anything behind it never
     * runs. This hook runs before all of them.
     *
     * @param FormRuntime $formRuntime                       Form runtime
     * @param CompositeRenderableInterface|null $currentPage Current page
     * @param CompositeRenderableInterface|null $lastPage    Last displayed page
     * @param array $requestArguments                        Request arguments
     *
     * @return CompositeRenderableInterface|null Current page, untouched
     */
    public function afterInitializeCurrentPage(
        FormRuntime $formRuntime,
        CompositeRenderableInterface $currentPage = null,
        CompositeRenderableInterface $lastPage = null,
        array $requestArguments = []
    ) {
        // Only the moment the form has run its course is of interest here
        if ($currentPage !== null) {
            return $currentPage;
        }

        $formDefinition = $formRuntime->getFormDefinition();
        $result         = $this->validate($formDefinition);
        if ($result === null) {
            return $currentPage;
        }

        // A submission that completes the form never renders it again — it goes
        // straight to the finishers, which send the mail. Refusing at rendering time
        // would therefore refuse nothing at all: exactly the submission a machine makes
        // would pass. Sending the runtime back to the last page instead keeps the
        // finishers from running, and the notice takes over from there.
        if ($result->isBlacklisted() || (!$result->isValid() && $this->isSubmission($formDefinition))) {
            return $lastPage ?? $formDefinition->getPageByIndex(0);
        }

        $this->rememberSubmission($formDefinition->getIdentifier());

        return $currentPage;
    }

    /**
     * Turn one armor input into a form element
     *
     * The library hands out parameter names in bracket notation; the form framework
     * addresses the same nesting with dots. Both end up as the identical name in the
     * markup, which is what lets the library find its own parameters again.
     *
     * @param Page $page        Page to add the element to
     * @param array $attributes Armor input attributes
     */
    protected function createArmorField(Page $page, array $attributes): void
    {
        $identifier = str_replace(['][', '[', ']'], ['.', '.', ''], $attributes['name']);

        try {
            $element = $page->createElement($identifier, 'AntibotField');
        } catch (\Throwable $e) {
            $this->logger->error(
                'Antibot could not add an armor field',
                ['identifier' => $identifier, 'error' => $e->getMessage()]
            );

            return;
        }

        $element->setDefaultValue(strval($attributes['value']));
        $element->setRenderingOption('antibotType', $attributes['type'] ?? self::TYPE_HIDDEN);
    }

    /**
     * Wrap the rendering of a form
     *
     * @param FormRuntime $formRuntime            Form runtime
     * @param RootRenderableInterface $renderable  Renderable
     */
    public function beforeRendering(FormRuntime $formRuntime, RootRenderableInterface $renderable): void
    {
        // The hook is called twice: once per form by the renderer, and once per
        // renderable by the view helper. Only the first carries the form definition,
        // and only it may exchange the template.
        if (!$renderable instanceof FormDefinition) {
            return;
        }

        $result = $this->validate($renderable);
        if ($result === null) {
            return;
        }

        // A banned client never gets a form, submission or not
        if ($result->isBlacklisted()) {
            $this->block($renderable);

            return;
        }

        // Everything else is only decided about a submission. The first visit carries no
        // armor and must not: that is where the form is handed out in the first place.
        if (!$this->isSubmission($renderable) || $result->isValid()) {
            return;
        }

        // Not valid is not the same as refused. A submission that arrives without any
        // armor is reported as skipped — nothing was there to judge — and treating that
        // as a pass would let every machine through that simply leaves the fields out.
        $reason = $this->describe($result);
        $this->logger->warning('Antibot refused a submission', [
            'form'   => $renderable->getIdentifier(),
            'reason' => $reason,
        ]);

        $configuration = $this->getConfiguration($renderable);
        if (!empty($configuration['ban'])) {
            $this->ban($renderable->getIdentifier(), $reason);
        }

        $this->block($renderable);
    }

    /**
     * Show the notice in place of a form
     *
     * The contact address travels as a rendering option because that is what the block
     * template can reach — the renderer hands it the form and nothing else.
     *
     * @param FormDefinition $formDefinition Form definition
     */
    protected function block(FormDefinition $formDefinition): void
    {
        $this->getInstallationDefaults();
        $formDefinition->setRenderingOption(
            self::OPTION_CONTACT,
            strval($this->settings['contact']['email'] ?? '')
        );

        // Where the installation names no classes, the notice takes those of the form:
        // it then stands in the same place in the same layout, whatever that layout is.
        $classes = trim(strval($this->settings['classAttribute'] ?? ''));
        $formDefinition->setRenderingOption(
            self::OPTION_CLASS,
            strlen($classes) ? $classes : strval($formDefinition->getRenderingOptions()['elementClassAttribute'] ?? '')
        );

        $formDefinition->setRenderingOption('templateName', self::TEMPLATE_BLOCKED);
    }

    /**
     * Put the client behind the refused submission on the blacklist
     *
     * The ban outlasts the request and has no end of its own: it stands until an editor
     * removes the record. What was submitted goes in with it, so that the decision to
     * lift it can be made by looking rather than by guessing.
     *
     * @param string $identifier Form identifier
     * @param string $reason     Reason for the ban
     */
    protected function ban(string $identifier, string $reason): void
    {
        $request      = $GLOBALS['TYPO3_REQUEST'] ?? null;
        $banValidator = $this->banValidators[$identifier] ?? null;
        if (($request === null) || ($banValidator === null)) {
            return;
        }

        // With the instance, because it knows the client address the installation resolved.
        // Without it the subject would be REMOTE_ADDR — behind a proxy the proxy itself,
        // and a ban on that locks out everybody.
        $subject = $banValidator->getSubjectKey($request, $this->instances[$identifier] ?? null);
        if ($subject === null) {
            return;
        }

        $body    = (array)$request->getParsedBody();
        $payload = json_encode($body[$this->getPluginNamespace()] ?? $body, JSON_UNESCAPED_UNICODE);

        if ($this->getStore()->ban($subject, $reason, strval($payload))) {
            $this->logger->warning('Antibot banned a client', ['subject' => $subject, 'reason' => $reason]);
        }
    }

    /**
     * Put a validation result into words
     *
     * @param ValidationResultInterface $result Validation result
     *
     * @return string Reason
     */
    protected function describe(ValidationResultInterface $result): string
    {
        if ($result->isSkipped() && !$result->hasErrors()) {
            return 'Submitted without an armor';
        }

        $reasons = [];
        foreach ($result->getErrors() as $error) {
            $class     = explode('\\', get_class($error));
            $reasons[] = end($class) . ': ' . $error->getMessage();
        }

        return count($reasons) ? implode('; ', $reasons) : 'Refused without a stated reason';
    }

    /**
     * Return the blacklist store
     *
     * @return BlacklistStore Blacklist store
     */
    protected function getStore(): BlacklistStore
    {
        return GeneralUtility::makeInstance(
            BlacklistStore::class,
            $this->getStoragePid(),
            $this->getBanPeriod()
        );
    }

    /**
     * Return the number of seconds a new ban lasts
     *
     * Zero means it lasts until someone takes it back. A month is the default, as it was
     * in the 6.x version: long enough to be a deterrent, short enough that a false
     * positive does not become a life sentence nobody remembers to lift.
     *
     * @return int Seconds
     */
    protected function getBanPeriod(): int
    {
        $this->getInstallationDefaults();

        return max(0, intval($this->settings['banning']['period'] ?? 0));
    }

    /**
     * Test whether the current request submits a given form
     *
     * @param FormDefinition $formDefinition Form definition
     *
     * @return bool The form was submitted
     */
    protected function isSubmission(FormDefinition $formDefinition): bool
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (($request === null) || (strtoupper($request->getMethod()) !== 'POST')) {
            return false;
        }

        $body   = (array)$request->getParsedBody();
        $scoped = $body[$this->getPluginNamespace()][$formDefinition->getIdentifier()] ?? null;
        if (!is_array($scoped)) {
            return false;
        }

        // Only the first step can be judged. Element identifiers are unique within a
        // form, so the armor exists once and lives on the first page — and the form
        // template renders none but the current one, which leaves every later step
        // without it. Judging those would refuse every multi-page form at step two.
        //
        // Nothing is lost by it: reaching a later step takes a form state that TYPO3
        // signs, and that signature is only handed out to a first step that passed.
        //
        // __currentPage names the page to be shown *next*, not the one submitted. The
        // first page therefore comes back as 1 — as does a single page form, where 1 is
        // past the end and sends the submission to the finishers. A missing value is
        // treated the same way, so that a bare POST is judged rather than waved through.
        return intval($scoped['__currentPage'] ?? 1) === 1;
    }

    /**
     * Validate the current request against a form
     *
     * @param FormDefinition $formDefinition Form definition
     *
     * @return ValidationResultInterface|null Validation result (NULL if unprotected)
     */
    protected function validate(FormDefinition $formDefinition): ?ValidationResultInterface
    {
        $identifier = $formDefinition->getIdentifier();
        if (array_key_exists($identifier, $this->results)) {
            return $this->results[$identifier];
        }

        $configuration = $this->getConfiguration($formDefinition);
        $request       = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (($configuration === null) || ($request === null)) {
            return $this->results[$identifier] = null;
        }

        $result = $this->getAntibot($identifier, $configuration)->validate($request);

        if ($result->isBlacklisted()) {
            $this->logger->warning(
                'Antibot refused a banned client',
                ['form' => $identifier, 'reasons' => $result->getBlacklists()]
            );
        }

        return $this->results[$identifier] = $result;
    }

    /**
     * Return the Antibot configuration of a form
     *
     * A form declares itself protected through a rendering option, so the decision sits
     * with the form and not with the installation. `antibot: true` takes the defaults;
     * an array configures them. Anything else means the form is none of Antibot's
     * business.
     *
     * @param FormDefinition $formDefinition Form definition
     *
     * @return array|null Configuration (NULL if the form is unprotected)
     */
    protected function getConfiguration(FormDefinition $formDefinition): ?array
    {
        $option = $formDefinition->getRenderingOptions()[self::OPTION] ?? null;
        if (empty($option)) {
            return null;
        }

        $configuration = array_replace(self::DEFAULT_CONFIGURATION, $this->getInstallationDefaults());

        return is_array($option) ? array_replace($configuration, $option) : $configuration;
    }

    /**
     * Return the defaults the installation sets through TypoScript
     *
     * Three levels, each overriding the one before: what this extension ships, what the
     * installation sets in its constants, and what a form asks for. The middle one keeps
     * a decision that holds for every form — how long a form stays valid, whether a
     * refusal bans — from having to be repeated in each of them.
     *
     * @return array Installation defaults
     */
    protected function getInstallationDefaults(): array
    {
        if ($this->settings === null) {
            $this->settings = (array)GeneralUtility::makeInstance(ObjectManager::class)
                ->get(ConfigurationManagerInterface::class)
                ->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_SETTINGS, 'TwAntibot');
        }

        $defaults = [];

        if (isset($this->settings['banning']['enable'])) {
            $defaults['ban'] = (bool)intval($this->settings['banning']['enable']);
        }

        if (!empty($this->settings['time']['maximum'])) {
            $defaults['times'] = [
                'minimum'  => intval($this->settings['time']['minimum'] ?? 0),
                'maximum'  => intval($this->settings['time']['maximum']),
                'followup' => intval($this->settings['time']['followup'] ?? 0),
            ];
        }

        if (!empty($this->settings['order']['method'])) {
            $defaults['methods'] = strval($this->settings['order']['method']);
        }

        return $defaults;
    }

    /**
     * Return the storage page new blacklist records are written to
     *
     * @return int Storage page
     */
    protected function getStoragePid(): int
    {
        $this->getInstallationDefaults();

        return intval($this->settings['storagePid'] ?? 0);
    }

    /**
     * Return the number of seconds a submitted text is remembered
     *
     * @param array $configuration Antibot configuration
     *
     * @return int Lifetime in seconds
     */
    protected function getDuplicateLifetime(array $configuration): int
    {
        $this->getInstallationDefaults();
        $lifetime = $configuration['duplicates']['lifetime']
            ?? $this->settings['content']['duplicateLifetime']
            ?? 86400;

        return max(60, intval($lifetime));
    }

    /**
     * Return the Antibot instance guarding a form
     *
     * One instance per form and request: arming and validating have to agree on the
     * signature, and the signature is derived from the validators.
     *
     * @param string $identifier    Form identifier
     * @param array $configuration  Antibot configuration
     *
     * @return Antibot Antibot instance
     */
    protected function getAntibot(string $identifier, array $configuration): Antibot
    {
        if (!array_key_exists($identifier, $this->instances)) {
            $antibot = new Antibot($this->getSessionIdentifier(), $identifier);
            $antibot->setSecret(strval($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? ''));

            // Behind a reverse proxy REMOTE_ADDR is the proxy, and the same for everybody:
            // a ban imposed on it locks out every visitor at once. TYPO3 resolves the real
            // address from the headers the installation says it may trust, and that answer
            // is the one antibot judges by.
            $antibot->setClientAddress(strval(GeneralUtility::getIndpEnv('REMOTE_ADDR')));
            $antibot->setLogger($this->logger);
            $antibot->setParameterScope($this->getPluginNamespace(), $identifier);
            $banValidator                     = new BanValidator($this->getStore());
            $this->banValidators[$identifier]   = $banValidator;
            $antibot->addValidator($banValidator);

            if (!empty($configuration['honeypots'])) {
                $antibot->addValidator(new HoneypotValidator((array)$configuration['honeypots']));
            }

            $antibot->addValidator($this->createHmacValidator($configuration));
            $this->addContentValidators($antibot, $identifier, $configuration);

            $this->instances[$identifier] = $antibot;
        }

        return $this->instances[$identifier];
    }

    /**
     * Add the validators that judge what was typed into the form
     *
     * All of them are off unless a form asks for them: a heuristic that nobody chose is
     * a heuristic nobody can answer for.
     *
     * @param Antibot $antibot       Antibot instance
     * @param string $identifier     Form identifier
     * @param array $configuration   Antibot configuration
     */
    protected function addContentValidators(Antibot $antibot, string $identifier, array $configuration): void
    {
        // Word and character counts per field
        if (!empty($configuration['plausibility'])) {
            $rules = [];
            foreach ((array)$configuration['plausibility'] as $parameter => $parameterRules) {
                $rules[strval(new ParameterPath($this->qualify($identifier, $parameter)))] = $parameterRules;
            }
            $antibot->addValidator(new ParameterPlausibilityValidator($rules, ParameterPlausibilityValidator::POST));
        }

        // How many hyperlinks a field may carry
        if (!empty($configuration['links']['parameters'])) {
            $antibot->addValidator(new ParameterLinkValidator(
                $this->qualifyAll($identifier, (array)$configuration['links']['parameters']),
                intval($configuration['links']['maximum'] ?? 1),
                ParameterLinkValidator::POST
            ));
        }

        // The same value repeated across unrelated fields
        if (!empty($configuration['equality']['parameters'])) {
            $antibot->addValidator(new ParameterEqualityValidator(
                $this->qualifyAll($identifier, (array)$configuration['equality']['parameters']),
                intval($configuration['equality']['tolerance'] ?? 1),
                ParameterEqualityValidator::POST
            ));
        }

        // Which field names a submission may carry
        if (!empty($configuration['parameterSet']['expected'])) {
            $setValidator = new ParameterSetValidator(
                (array)$configuration['parameterSet']['expected'],
                ParameterSetValidator::POST,
                [$this->getPluginNamespace(), $identifier]
            );
            if (!empty($configuration['parameterSet']['tolerateUnexpected'])) {
                $setValidator->tolerateUnexpected();
            }
            if (!empty($configuration['parameterSet']['requireComplete'])) {
                $setValidator->requireComplete();
            }
            $antibot->addValidator($setValidator);
        }

        // Content that has been submitted before
        if (!empty($configuration['duplicates']['parameters'])) {
            $duplicateValidator = new DuplicateValidator(
                GeneralUtility::makeInstance(CacheStore::class),
                $this->qualifyAll($identifier, (array)$configuration['duplicates']['parameters']),
                DuplicateValidator::POST
            );
            $this->duplicates[$identifier] = [$duplicateValidator, $this->getDuplicateLifetime($configuration)];
            $antibot->addValidator($duplicateValidator);
        }

        $this->addStopwordValidator($antibot, $identifier, $configuration);
    }

    /**
     * Add the validator that refuses terms no welcome submission contains
     *
     * The terms are the stopword records maintained in the backend. The form only says
     * which of its fields they apply to.
     *
     * @param Antibot $antibot       Antibot instance
     * @param string $identifier     Form identifier
     * @param array $configuration   Antibot configuration
     */
    protected function addStopwordValidator(Antibot $antibot, string $identifier, array $configuration): void
    {
        if (empty($configuration['stopwords']['parameters'])) {
            return;
        }

        $antibot->addValidator(new ParameterStopwordValidator(
            GeneralUtility::makeInstance(StopwordList::class),
            $this->qualifyAll($identifier, (array)$configuration['stopwords']['parameters']),
            ParameterStopwordValidator::POST
        ));
    }

    /**
     * Note down the content of a submission that has been accepted
     *
     * Called by the finisher this extension adds to a form that asks for duplicate
     * detection — the one moment at which a submission is known to have made it through
     * everything.
     *
     * @param string $identifier Form identifier
     */
    public function rememberSubmission(string $identifier): void
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        list($validator, $lifetime) = $this->duplicates[$identifier] ?? [null, 0];
        if (($request === null) || ($validator === null)) {
            return;
        }

        $fingerprint = $validator->getFingerprint($request);
        if ($fingerprint === null) {
            return;
        }

        GeneralUtility::makeInstance(CacheStore::class)->remember($fingerprint, $lifetime);
        $this->logger->info('Antibot noted the content of an accepted submission', [
            'form'     => $identifier,
            'lifetime' => $lifetime,
        ]);
    }

    /**
     * Qualify a field name with the nesting the framework submits it under
     *
     * A form is configured with the names its editor knows — "message", not
     * "tx_twtollwerk_contact[contact][message]". The nesting is the framework's business
     * and is added here, which also keeps a configuration portable between the form
     * content element and a plugin of its own.
     *
     * @param string $identifier Form identifier
     * @param string $parameter  Field name
     *
     * @return string[] Parameter path segments
     */
    protected function qualify(string $identifier, string $parameter): array
    {
        return array_merge(
            [$this->getPluginNamespace(), $identifier],
            (new ParameterPath($parameter))->getSegments()
        );
    }

    /**
     * Qualify a list of field names
     *
     * @param string $identifier  Form identifier
     * @param array $parameters   Field names
     *
     * @return array[] Parameter paths
     */
    protected function qualifyAll(string $identifier, array $parameters): array
    {
        $qualified = [];
        foreach ($parameters as $parameter) {
            $qualified[] = $this->qualify($identifier, strval($parameter));
        }

        return $qualified;
    }

    /**
     * Assemble the HMAC validator from a form's configuration
     *
     * @param array $configuration Antibot configuration
     *
     * @return HmacValidator HMAC validator
     */
    protected function createHmacValidator(array $configuration): HmacValidator
    {
        $hmacValidator = new HmacValidator();

        // The order of request methods a form is expected to travel, e.g. "GET-POST"
        if (!empty($configuration['methods'])) {
            $methods = GeneralUtility::trimExplode('-', strtoupper($configuration['methods']), true, 2);
            $hmacValidator->setMethodVector($methods[0], $methods[1] ?? $methods[0]);
        }

        // How long a form may lie around, and how quickly it may come back
        if (!empty($configuration['times']) && is_array($configuration['times'])) {
            $times = $configuration['times'];
            $hmacValidator->setSubmissionTimes(
                floatval($times['maximum'] ?? HmacValidator::MAXIMUM_SUBMISSION),
                floatval($times['minimum'] ?? HmacValidator::MINIMUM_SUBMISSION),
                floatval($times['followup'] ?? HmacValidator::MINIMUM_FOLLOWUP_SUBMISSION)
            );
        }

        return $hmacValidator;
    }

    /**
     * Return the parameter namespace the surrounding plugin submits under
     *
     * A form does not always live in the form content element. Rendered from a plugin of
     * its own — a factory behind a `form:render` view helper — it submits under that
     * plugin's namespace instead, and Antibot would look for its parameters in a place
     * nothing ever arrives. Hence asking rather than assuming.
     *
     * @return string Plugin namespace, e.g. "tx_form_formframework"
     */
    protected function getPluginNamespace(): string
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);
        $configuration = $objectManager->get(ConfigurationManagerInterface::class)
            ->getConfiguration(ConfigurationManagerInterface::CONFIGURATION_TYPE_FRAMEWORK);

        $namespace = $objectManager->get(ExtensionService::class)->getPluginNamespace(
            $configuration['extensionName'] ?? '',
            $configuration['pluginName'] ?? ''
        );
        $this->logger->debug('Antibot resolved the parameter namespace', ['namespace' => $namespace]);

        return $namespace;
    }

    /**
     * Return the session-persistent identifier of the current visitor
     *
     * Only a session the visitor actually carries may be used. TYPO3 hands out a session
     * identifier to everyone, but it keeps none for an anonymous visitor: the next
     * request brings a new one, and an armor issued under the old one would be looked
     * for under a name that no longer exists.
     *
     * Without a session Antibot binds to the installation secret and the form alone.
     * That gives up telling one anonymous visitor from another — the checks that matter
     * against a machine, timing, honeypots and the signature, all still hold.
     *
     * @return string Session identifier, empty for a visitor without one
     */
    protected function getSessionIdentifier(): string
    {
        $frontendUser = $GLOBALS['TSFE']->fe_user ?? null;
        if ($frontendUser === null) {
            return '';
        }

        return $frontendUser->isCookieSet() ? $frontendUser->getSessionId() : '';
    }
}
