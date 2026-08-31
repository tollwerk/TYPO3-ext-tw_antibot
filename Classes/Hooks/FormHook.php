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
use Jkphl\Antibot\Ports\Validators\HoneypotValidator;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\NullLogger;
use Tollwerk\TwAntibot\Store\BlacklistStore;
use TYPO3\CMS\Core\Http\ApplicationType;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Service\ExtensionService;
use TYPO3\CMS\Form\Domain\Model\FormDefinition;
use TYPO3\CMS\Form\Domain\Model\FormElements\Page;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RootRenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

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

        $identifier = $renderable->getRootForm()->getIdentifier();
        if (isset($this->armed[$identifier])) {
            return;
        }
        $this->armed[$identifier] = true;

        foreach ($this->getAntibot($identifier)->armorInputs($request) as $input) {
            $this->createArmorField($renderable, $input->getAttributes());
        }
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
            $renderable->setRenderingOption('templateName', self::TEMPLATE_BLOCKED);

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
        $this->logger->warning('Antibot refused a submission', [
            'form'    => $renderable->getIdentifier(),
            'skipped' => $result->isSkipped(),
            'errors'  => array_map('strval', $result->getErrors()),
        ]);
        $renderable->setRenderingOption('templateName', self::TEMPLATE_BLOCKED);
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

        $body = (array)$request->getParsedBody();
        $body = $body[$this->getPluginNamespace()] ?? null;

        return is_array($body) && array_key_exists($formDefinition->getIdentifier(), $body);
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

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if ($request === null) {
            return $this->results[$identifier] = null;
        }

        $result = $this->getAntibot($identifier)->validate($request);

        if ($result->isBlacklisted()) {
            $this->logger->warning(
                'Antibot refused a banned client',
                ['form' => $identifier, 'reasons' => $result->getBlacklists()]
            );
        }

        return $this->results[$identifier] = $result;
    }

    /**
     * Return the Antibot instance guarding a form
     *
     * One instance per form and request: arming and validating have to agree on the
     * signature, and the signature is derived from the validators.
     *
     * @param string $identifier Form identifier
     *
     * @return Antibot Antibot instance
     */
    protected function getAntibot(string $identifier): Antibot
    {
        if (!array_key_exists($identifier, $this->instances)) {
            $antibot = new Antibot($this->getSessionIdentifier(), $identifier);
            $antibot->setSecret(strval($GLOBALS['TYPO3_CONF_VARS']['SYS']['encryptionKey'] ?? ''));
            $antibot->setLogger($this->logger);
            $antibot->setParameterScope($this->getPluginNamespace(), $identifier);
            $antibot->addValidator(new BanValidator(GeneralUtility::makeInstance(BlacklistStore::class)));
            $antibot->addValidator(new HoneypotValidator(['email' => 'email', 'homepage' => 'url']));
            $antibot->addValidator(new HmacValidator());

            $this->instances[$identifier] = $antibot;
        }

        return $this->instances[$identifier];
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
