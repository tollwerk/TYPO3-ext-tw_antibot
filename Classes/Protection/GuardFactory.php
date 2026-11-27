<?php

/**
 * Guards for the forms of a request, each with the checks its form asks for
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Protection;

use Jkphl\Antibot\Domain\Exceptions\RuntimeException;
use Jkphl\Antibot\Infrastructure\Model\ParameterPath;
use Jkphl\Antibot\Ports\Antibot;
use Jkphl\Antibot\Ports\Exceptions\InvalidArgumentException;
use Jkphl\Antibot\Ports\Validators\BanValidator;
use Jkphl\Antibot\Ports\Validators\DuplicateValidator;
use Jkphl\Antibot\Ports\Validators\HmacValidator;
use Jkphl\Antibot\Ports\Validators\HoneypotValidator;
use Jkphl\Antibot\Ports\Validators\ParameterEqualityValidator;
use Jkphl\Antibot\Ports\Validators\ParameterLinkValidator;
use Jkphl\Antibot\Ports\Validators\ParameterPlausibilityValidator;
use Jkphl\Antibot\Ports\Validators\ParameterSetValidator;
use Jkphl\Antibot\Ports\Validators\ParameterStopwordValidator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use Tollwerk\TwAntibot\Store\BanStore;
use Tollwerk\TwAntibot\Store\CacheStore;
use Tollwerk\TwAntibot\Store\StopwordList;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Frontend\Authentication\FrontendUserAuthentication;
use TYPO3\CMS\Frontend\Cache\CacheInstruction;

/**
 * Guards for the forms of a request, each with the checks its form asks for
 *
 * A form gets the same guard for the whole request, because handing out the fields and judging a submission must agree
 * on their names. The names depend on the session of the visitor, the form, the encryption key of the installation and
 * the checks: fields taken from one form are worthless in another session, another form or after the checks changed.
 *
 * Only a session the visitor brings counts. TYPO3 starts a new one for an anonymous visitor in every request, and
 * fields handed out under it would be looked for under a name that no longer exists. Without a session the names
 * depend on the rest alone; the checks of timing, honeypots and the signature still hold.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
#[Autoconfigure(public: true)]
final class GuardFactory
{
    /**
     * Guards by form
     *
     * @var array<string, Guard>
     */
    private array $guards = [];

    /**
     * Constructor
     *
     * @param ConnectionPool  $connectionPool Connections to the database, for the bans
     * @param CacheStore      $cacheStore     Fingerprints of accepted submissions
     * @param StopwordList    $stopwords      Stopwords
     * @param Context         $context        Context, for the time of the request
     * @param ProtectedPage   $page           Whether the page carries protective fields
     * @param LoggerInterface $logger         Logger
     */
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly CacheStore $cacheStore,
        private readonly StopwordList $stopwords,
        private readonly Context $context,
        private readonly ProtectedPage $page,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Guard of a form
     *
     * @param ServerRequestInterface  $request Request
     * @param string                  $form    Identifier of the form, such as "registration"
     * @param array<array-key, mixed> $options Checks the form asks for, see Options
     * @param list<string>            $scope   Names the form submits its fields under, such as
     *                                         ["tx_form_formframework", "contact"]; none if empty
     *
     * @return Guard
     *
     * @throws InvalidArgumentException If a field of the form has an invalid name
     * @throws RuntimeException If the checks are set up twice
     */
    public function create(ServerRequestInterface $request, string $form, array $options = [], array $scope = []): Guard
    {
        $key = $form . '|' . implode('|', $scope);
        if (isset($this->guards[$key])) {
            return $this->guards[$key];
        }

        $site = $request->getAttribute('site');
        $settings = Settings::fromSite($site instanceof Site ? $site : null);
        $options = Options::fromArray($options, $settings);
        $clock = new ContextClock($this->context);
        $banStore = new BanStore($this->connectionPool, $settings->storage, $settings->banPeriod, $clock->now());

        $antibot = new Antibot($this->session($request), $form);
        $antibot->setClock($clock);
        $antibot->setSecret($this->secret());
        $antibot->setClientAddress($this->address($request));
        $antibot->setLogger($this->logger);
        if ([] !== $scope) {
            $antibot->setParameterScope(...$scope);
        }

        $bans = new BanValidator($banStore);
        $antibot->addValidator($bans);
        $antibot->addValidator($this->timing($options));
        if ([] !== $options->honeypots) {
            $antibot->addValidator(new HoneypotValidator($options->honeypots));
        }

        $duplicates = $this->addContentChecks($antibot, $options, $scope);

        return $this->guards[$key] = new Guard(
            $antibot,
            $request,
            $scope,
            $options,
            $settings,
            $bans,
            $banStore,
            $duplicates,
            $this->cacheStore,
            $this->logger,
            function () use ($request): void {
                $this->page->mark();
                $instruction = $request->getAttribute('frontend.cache.instruction');
                if (!($instruction instanceof CacheInstruction)) {
                    return;
                }

                $instruction->disableCache('EXT:tw_antibot: A page with protective fields belongs to one visitor');
            },
        );
    }

    /**
     * Add the checks of content the form asks for
     *
     * @param Antibot      $antibot Checks
     * @param Options      $options Options
     * @param list<string> $scope   Names the form submits its fields under
     *
     * @return DuplicateValidator|null Check for repeated content, if the form asks for it
     *
     * @throws InvalidArgumentException If a field of the form has an invalid name
     */
    private function addContentChecks(Antibot $antibot, Options $options, array $scope): ?DuplicateValidator
    {
        if ([] !== $options->plausibility) {
            $rules = [];
            foreach ($options->plausibility as $field => $limits) {
                $rules[(string) new ParameterPath(self::qualify($scope, $field))] = $limits;
            }

            $antibot->addValidator(new ParameterPlausibilityValidator($rules, ParameterPlausibilityValidator::POST));
        }

        if ([] !== $options->links) {
            $antibot->addValidator(new ParameterLinkValidator(
                $this->qualifyAll($scope, $options->links),
                $options->linkMaximum,
                ParameterLinkValidator::POST,
            ));
        }

        if ([] !== $options->equality) {
            $antibot->addValidator(new ParameterEqualityValidator(
                $this->qualifyAll($scope, $options->equality),
                $options->equalityTolerance,
                ParameterEqualityValidator::POST,
            ));
        }

        if ([] !== $options->expected) {
            $set = new ParameterSetValidator($options->expected, ParameterSetValidator::POST, $scope);
            if ($options->tolerateUnexpected) {
                $set->tolerateUnexpected();
            }

            if ($options->requireComplete) {
                $set->requireComplete();
            }

            $antibot->addValidator($set);
        }

        if ([] !== $options->stopwords) {
            $antibot->addValidator(new ParameterStopwordValidator(
                $this->stopwords,
                $this->qualifyAll($scope, $options->stopwords),
                ParameterStopwordValidator::POST,
            ));
        }

        if ([] === $options->duplicates) {
            return null;
        }

        $duplicates = new DuplicateValidator(
            $this->cacheStore,
            $this->qualifyAll($scope, $options->duplicates),
            DuplicateValidator::POST,
        );
        $antibot->addValidator($duplicates);

        return $duplicates;
    }

    /**
     * Path of a field in the scope of the form
     *
     * @param list<string> $scope Names the form submits its fields under
     * @param string       $field Name of the field in the form, such as "message" or "address[city]"
     *
     * @return list<string>
     */
    private static function qualify(array $scope, string $field): array
    {
        return array_values([...$scope, ...array_map(strval(...), new ParameterPath($field)->getSegments())]);
    }

    /**
     * Paths of fields in the scope of the form
     *
     * @param list<string> $scope  Names the form submits its fields under
     * @param list<string> $fields Names of the fields in the form
     *
     * @return list<list<string>>
     */
    private function qualifyAll(array $scope, array $fields): array
    {
        return array_map(static fn(string $field): array => self::qualify($scope, $field), $fields);
    }

    /**
     * Check of the timing and the order of request methods
     *
     * @param Options $options Options
     *
     * @return HmacValidator
     */
    private function timing(Options $options): HmacValidator
    {
        $hmac = new HmacValidator();
        $methods = array_values(array_filter(
            explode('-', strtoupper($options->methods)),
            static fn(string $method): bool => '' !== $method,
        ));
        if ([] !== $methods) {
            $hmac->setMethodVector($methods[0], $methods[1] ?? $methods[0]);
        }

        $hmac->setSubmissionTimes($options->maximum, $options->minimum, $options->followup);

        return $hmac;
    }

    /**
     * Session the visitor brings
     *
     * @param ServerRequestInterface $request Request
     *
     * @return string Identifier of the session; empty for a visitor without one
     */
    private function session(ServerRequestInterface $request): string
    {
        $user = $request->getAttribute('frontend.user');
        if (!$user instanceof FrontendUserAuthentication) {
            return '';
        }

        $session = $user->getSession();

        return $session->isNew() ? '' : $session->getIdentifier();
    }

    /**
     * Address of the client, as TYPO3 resolves it behind the proxies it trusts
     *
     * Behind a reverse proxy, REMOTE_ADDR is the proxy and the same for every visitor: a ban on it would lock out
     * everybody.
     *
     * @param ServerRequestInterface $request Request
     *
     * @return string|null Null if unknown
     */
    private function address(ServerRequestInterface $request): ?string
    {
        $params = $request->getAttribute('normalizedParams');
        $address = $params instanceof NormalizedParams
            ? $params->getRemoteAddress()
            : ($request->getServerParams()['REMOTE_ADDR'] ?? null);

        return \is_string($address) && '' !== $address ? $address : null;
    }

    /**
     * Encryption key of the installation
     *
     * @return string
     */
    private function secret(): string
    {
        $configuration = $GLOBALS['TYPO3_CONF_VARS'] ?? null;
        $system = \is_array($configuration) ? $configuration['SYS'] ?? null : null;
        $key = \is_array($system) ? $system['encryptionKey'] ?? null : null;

        return \is_string($key) ? $key : '';
    }
}
