<?php

/**
 * Protection of one form in one request: its protective fields and the judgement of a submission
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

use Closure;
use Doctrine\DBAL\Exception as DBALException;
use Jkphl\Antibot\Domain\Contract\ValidationResultInterface;
use Jkphl\Antibot\Infrastructure\Model\InputElement;
use Jkphl\Antibot\Infrastructure\Model\ParameterPath;
use Jkphl\Antibot\Ports\Antibot;
use Jkphl\Antibot\Ports\Exceptions\InvalidArgumentException;
use Jkphl\Antibot\Ports\Validators\BanValidator;
use Jkphl\Antibot\Ports\Validators\DuplicateValidator;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Tollwerk\TwAntibot\Store\BanStore;
use Tollwerk\TwAntibot\Store\CacheStore;
use TYPO3\CMS\Core\Http\NormalizedParams;

/**
 * Protection of one form in one request: its protective fields and the judgement of a submission
 *
 * A form shows the fields of fields() and, when it is closed, the notice with the address of contact(). Before it
 * handles a submission it asks isSubmitted(), and then judge(); a submission it accepted in the end, it passes to
 * remember(), so that the same content is refused for a while if the form asks for that. view() has the arguments of
 * the components. A page that shows the form or its notice must stay out of caches: fields() and keepPagePrivate()
 * see to it.
 *
 * @phpstan-type Field array{name: string, value: string, type: string}
 * @phpstan-type GuardView array{fields: list<Field>, contact: string}
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class Guard
{
    /**
     * Result of the checks, once run
     *
     * @var ValidationResultInterface|null
     */
    private ?ValidationResultInterface $result = null;

    /**
     * Judgement of the submission, once made
     *
     * @var Verdict|null
     */
    private ?Verdict $verdict = null;

    /**
     * Protective fields, once handed out
     *
     * @var list<Field>|null
     */
    private ?array $fields = null;

    /**
     * Fingerprint of the content remembered in this request
     *
     * @var string|null
     */
    private ?string $remembered = null;

    /**
     * Constructor
     *
     * @param Antibot                 $antibot    Checks of jkphl/antibot
     * @param ServerRequestInterface  $request    Request
     * @param list<string>            $scope      Names the form submits its fields under
     * @param Options                 $options    Checks the form asks for
     * @param Settings                $settings   Settings of the site
     * @param BanValidator            $bans       Check for bans
     * @param BanStore                $banStore   Records of bans
     * @param DuplicateValidator|null $duplicates Check for repeated content; none if the form does not ask for it
     * @param CacheStore              $cacheStore Fingerprints of accepted submissions
     * @param LoggerInterface         $logger     Logger
     * @param Closure(): void         $onArmor    Called when the page shows the form or its notice
     */
    public function __construct(
        private readonly Antibot $antibot,
        private readonly ServerRequestInterface $request,
        private readonly array $scope,
        private readonly Options $options,
        private readonly Settings $settings,
        private readonly BanValidator $bans,
        private readonly BanStore $banStore,
        private readonly ?DuplicateValidator $duplicates,
        private readonly CacheStore $cacheStore,
        private readonly LoggerInterface $logger,
        private readonly Closure $onArmor,
    ) {}

    /**
     * Protective fields: hidden fields and honeypots, with their names in the scope of the form
     *
     * A page with them must not come from a cache: they belong to one visitor and carry the time they were handed out.
     *
     * @return list<Field>
     */
    public function fields(): array
    {
        if (null !== $this->fields) {
            return $this->fields;
        }

        $this->keepPagePrivate();
        $this->fields = [];
        foreach ($this->antibot->armorInputs($this->request) as $input) {
            if (!($input instanceof InputElement)) {
                continue;
            }

            $this->fields[] = $this->field($input->getAttributes());
        }

        return $this->fields;
    }

    /**
     * Keep the page of the request out of caches, because it shows the form or its notice
     *
     * The notice belongs to one client as much as the fields do: from a cache, every visitor after a banned client
     * would get the notice instead of the form.
     *
     * @return void
     */
    public function keepPagePrivate(): void
    {
        ($this->onArmor)();
    }

    /**
     * Whether the request sends this form
     *
     * A form sends its fields under its scope. A request without them sends another form, or none, and must not be
     * judged: the judgement would refuse it, and ban its client, for the fields it lacks. A form without a scope cannot
     * tell its submissions from others and counts every POST.
     *
     * @return boolean
     */
    public function isSubmitted(): bool
    {
        if ([] === $this->scope) {
            return 'POST' === strtoupper($this->request->getMethod());
        }

        $body = $this->request->getParsedBody();
        $body = \is_array($body) ? $body : [];

        return null !== $this->find($body, $this->scope)
            || null !== $this->find($this->request->getQueryParams(), $this->scope);
    }

    /**
     * Address offered to a client whose form is closed
     *
     * @return string Empty if the site has none
     */
    public function contact(): string
    {
        return $this->settings->contact;
    }

    /**
     * Arguments of the components "twc:antibot.fields" and "twc:antibot.notice"
     *
     * @return GuardView
     */
    public function view(): array
    {
        return ['fields' => $this->fields(), 'contact' => $this->settings->contact];
    }

    /**
     * Whether the client is banned and gets the notice instead of the form, submission or not
     *
     * @return boolean
     *
     * @throws InvalidArgumentException If a field of the form has an invalid name
     */
    public function isBanned(): bool
    {
        $subject = $this->bans->getSubjectKey($this->request, $this->antibot);

        return null !== $subject && \in_array($subject, $this->result()->getBlacklists(), true);
    }

    /**
     * Judge the submission of the request, once
     *
     * A submission without the protective fields is refused: nothing was there to check, and passing it would let
     * every machine through that leaves the fields out. So is a submission from a page of another site. Neither bans
     * the client: another site can make the browser of any visitor send a form, with or without the fields.
     *
     * @return Verdict
     *
     * @throws DBALException If the ban cannot be recorded
     * @throws InvalidArgumentException If a field of the form has an invalid name
     */
    public function judge(): Verdict
    {
        return $this->verdict ??= $this->decide();
    }

    /**
     * Judgement of the submission, if it was judged
     *
     * @return Verdict|null Null before judge()
     */
    public function verdict(): ?Verdict
    {
        return $this->verdict;
    }

    /**
     * Remember the content of a submission the form accepted, if the form asks to refuse repeated content
     *
     * @return void
     *
     * @throws InvalidArgumentException If a field of the form has an invalid name
     */
    public function remember(): void
    {
        $fingerprint = $this->duplicates?->getFingerprint($this->request);
        if (null === $fingerprint) {
            return;
        }

        $this->cacheStore->remember($fingerprint, $this->options->duplicateLifetime);
        $this->remembered = $fingerprint;
    }

    /**
     * Forget the content remembered in this request, such as for a submission the form refused after all
     *
     * @return void
     */
    public function forget(): void
    {
        if (null === $this->remembered) {
            return;
        }

        $this->cacheStore->forget($this->remembered);
        $this->remembered = null;
    }

    /**
     * Whether content was remembered in this request
     *
     * @return boolean
     */
    public function hasRemembered(): bool
    {
        return null !== $this->remembered;
    }

    /**
     * Judge the submission
     *
     * @return Verdict
     *
     * @throws DBALException If the ban cannot be recorded
     * @throws InvalidArgumentException If a field of the form has an invalid name
     */
    private function decide(): Verdict
    {
        if ($this->isBanned()) {
            $this->logger->warning('A banned client sent a form', ['form' => $this->antibot->getPrefix()]);

            return Verdict::Banned;
        }

        if ($this->isForeign()) {
            $this->logger->warning('A submission was refused', [
                'form' => $this->antibot->getPrefix(),
                'reason' => 'Sent from a page of another site',
            ]);

            return Verdict::Refused;
        }

        $result = $this->result();
        if ($result->isValid()) {
            return Verdict::Accepted;
        }

        $reason = $this->reason($result);
        $this->logger->warning('A submission was refused', [
            'form' => $this->antibot->getPrefix(),
            'reason' => $reason,
        ]);

        // Content sent before may come from a person who sent a form twice, and earns no ban; a submission without the
        // fields may come from a page of another site that sent it from the browser of a visitor
        if ($this->options->ban && !$result->isBlacklisted() && !$result->isSkipped()) {
            $this->ban($reason);
        }

        return Verdict::Refused;
    }

    /**
     * Result of the checks
     *
     * @return ValidationResultInterface
     */
    private function result(): ValidationResultInterface
    {
        return $this->result ??= $this->antibot->validate($this->request);
    }

    /**
     * Whether a page of another site sent the request
     *
     * Browsers name the site that sent a request in "Sec-Fetch-Site", and its origin in "Origin"; "null" stands for an
     * origin they keep to themselves, such as after a redirect from another site. A request without either, such as
     * from an older browser, counts as one of this site.
     *
     * @return boolean
     */
    private function isForeign(): bool
    {
        if ('cross-site' === strtolower($this->request->getHeaderLine('Sec-Fetch-Site'))) {
            return true;
        }

        $origin = strtolower(trim($this->request->getHeaderLine('Origin')));
        if ('' === $origin) {
            return false;
        }

        $params = $this->request->getAttribute('normalizedParams');
        $host = $params instanceof NormalizedParams
            ? $params->getRequestHost()
            : $this->request->getUri()->getScheme() . '://' . $this->request->getUri()->getAuthority();

        return strtolower(rtrim($host, '/')) !== $origin;
    }

    /**
     * Ban the client, with the submission for the editor who reads the record
     *
     * The record holds what the form sent under its scope, without the values of the fields the options name as secret,
     * such as passwords: an editor needs neither to judge whether a person or a machine sent it.
     *
     * @param string $reason Reason
     *
     * @return void
     *
     * @throws DBALException If the ban cannot be recorded
     * @throws InvalidArgumentException If a field of the form has an invalid name
     */
    private function ban(string $reason): void
    {
        $subject = $this->bans->getSubjectKey($this->request, $this->antibot);
        if (null === $subject) {
            return;
        }

        $body = $this->request->getParsedBody();
        $values = $this->find(\is_array($body) ? $body : [], $this->scope) ?? [];
        foreach ($this->options->secrets as $secret) {
            $values = self::without($values, array_values(new ParameterPath($secret)->getSegments()));
        }

        $submission = json_encode(
            $values,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE
                | JSON_PARTIAL_OUTPUT_ON_ERROR,
        );
        if (!$this->banStore->ban($subject, $reason, (string) $submission)) {
            return;
        }

        // The address of the client stays out of the log; the record of the ban has it
        $this->logger->warning('A client was banned', ['form' => $this->antibot->getPrefix(), 'reason' => $reason]);
    }

    /**
     * Values under a path of names
     *
     * @param array<array-key, mixed> $parameters Parameters
     * @param list<string|integer>    $path       Names, outermost first; the parameters themselves if empty
     *
     * @return array<array-key, mixed>|null Null if the path leads to no array
     */
    private function find(array $parameters, array $path): ?array
    {
        foreach ($path as $name) {
            $parameters = $parameters[$name] ?? null;
            if (!\is_array($parameters)) {
                return null;
            }
        }

        return $parameters;
    }

    /**
     * Values without the one under a path of names
     *
     * @param array<array-key, mixed> $values Values
     * @param list<string|integer>    $path   Names, outermost first
     *
     * @return array<array-key, mixed>
     */
    private static function without(array $values, array $path): array
    {
        $name = array_shift($path);
        if (null === $name || !\array_key_exists($name, $values)) {
            return $values;
        }

        if ([] === $path) {
            unset($values[$name]);

            return $values;
        }

        if (\is_array($values[$name])) {
            $values[$name] = self::without($values[$name], $path);
        }

        return $values;
    }

    /**
     * Protective field with its name in the scope of the form
     *
     * @param array<array-key, mixed> $attributes Attributes from jkphl/antibot, with a name such as
     *                                            "contact_<hash>[hmac]"
     *
     * @return Field
     */
    private function field(array $attributes): array
    {
        $name = \is_string($attributes['name'] ?? null) ? $attributes['name'] : '';
        $value = $attributes['value'] ?? '';
        $type = $attributes['type'] ?? 'hidden';
        $segments = [...$this->scope, ...(array) preg_split('/[\[\]]+/', $name, -1, PREG_SPLIT_NO_EMPTY)];
        $first = (string) array_shift($segments);

        return [
            'name' => $first . implode('', array_map(
                static fn(mixed $segment): string => '[' . $segment . ']',
                $segments,
            )),
            'value' => \is_scalar($value) ? (string) $value : '',
            'type' => \is_string($type) ? $type : 'hidden',
        ];
    }

    /**
     * Reason for a refusal, for the log and the record of the ban
     *
     * @param ValidationResultInterface $result Result of the checks
     *
     * @return string
     */
    private function reason(ValidationResultInterface $result): string
    {
        $reasons = [];
        foreach ($result->getErrors() as $error) {
            $class = strrchr($error::class, '\\');
            $reasons[] = (false === $class ? $error::class : substr($class, 1)) . ': ' . $error->getMessage();
        }

        if ([] !== $reasons) {
            return implode('; ', $reasons);
        }

        return $result->isSkipped() ? 'Sent without the protective fields' : 'Refused without a reason';
    }
}
