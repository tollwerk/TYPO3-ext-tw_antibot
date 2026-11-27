<?php

/**
 * Checks a form asks for, on top of the settings of the site
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

/**
 * Checks a form asks for, on top of the settings of the site
 *
 * Every check that judges content is off until a form asks for it: a heuristic nobody chose is a heuristic nobody
 * can answer for. Field names are the names within the form, such as "message"; the guard adds the scope the form
 * submits under. The keys of the array are those of the rendering option "antibot" of EXT:form:
 *
 * - honeypots: fields no browser fills in, as name => type
 * - plausibility: word and character counts per field: minWords, maxWords, minLength, maxLength
 * - links: {parameters: fields, maximum: number of links}
 * - equality: {parameters: fields, tolerance: number of equal values}
 * - parameterSet: {expected: field names, tolerateUnexpected: bool, requireComplete: bool}
 * - duplicates: {parameters: fields, lifetime: seconds}
 * - stopwords: {parameters: fields}
 * - methods: order of request methods, such as "GET-POST"
 * - times: {minimum, maximum, followup} in seconds
 * - ban: whether a refused submission bans the client
 *
 * @phpstan-type Plausibility array<string, array<string, int>>
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final readonly class Options
{
    /**
     * Honeypots of a form that just asks for protection
     *
     * @var array<string, string>
     */
    public const array HONEYPOTS = ['email' => 'email', 'homepage' => 'url'];

    /**
     * Rules of plausibility a rule may have
     *
     * @var list<string>
     */
    private const array PLAUSIBILITY = ['minWords', 'maxWords', 'minLength', 'maxLength'];

    /**
     * Constructor
     *
     * @param array<string, string> $honeypots          Honeypots, as name => type
     * @param Plausibility          $plausibility       Word and character counts per field
     * @param list<string>          $links              Fields whose links are counted
     * @param integer               $linkMaximum        Number of links a field may carry
     * @param list<string>          $equality           Fields that must not carry the same value
     * @param integer               $equalityTolerance  Number of fields that may carry the same value
     * @param list<string>          $expected           Field names a submission carries; none if empty
     * @param boolean               $tolerateUnexpected Whether a submission may carry further fields
     * @param boolean               $requireComplete    Whether a submission must carry all expected fields
     * @param list<string>          $duplicates         Fields whose content must not repeat an earlier submission
     * @param integer               $duplicateLifetime  Seconds an accepted submission is remembered
     * @param list<string>          $stopwords          Fields that must not carry a stopword
     * @param string                $methods            Order of request methods, such as "GET-POST"; any if empty
     * @param float                 $minimum            Seconds before a form may come back
     * @param float                 $maximum            Seconds a form stays valid
     * @param float                 $followup           Seconds before a form may come back again
     * @param boolean               $ban                Whether a refused submission bans the client
     */
    public function __construct(
        public array $honeypots = self::HONEYPOTS,
        public array $plausibility = [],
        public array $links = [],
        public int $linkMaximum = 1,
        public array $equality = [],
        public int $equalityTolerance = 1,
        public array $expected = [],
        public bool $tolerateUnexpected = false,
        public bool $requireComplete = false,
        public array $duplicates = [],
        public int $duplicateLifetime = 86400,
        public array $stopwords = [],
        public string $methods = 'GET-POST',
        public float $minimum = 3,
        public float $maximum = 3600,
        public float $followup = 1,
        public bool $ban = true,
    ) {}

    /**
     * Options from the rendering option of a form, on top of the settings of a site
     *
     * @param array<array-key, mixed> $options  Options of the form
     * @param Settings                $settings Settings of the site
     *
     * @return self
     */
    public static function fromArray(array $options, Settings $settings): self
    {
        $links = self::map($options['links'] ?? null);
        $equality = self::map($options['equality'] ?? null);
        $set = self::map($options['parameterSet'] ?? null);
        $duplicates = self::map($options['duplicates'] ?? null);
        $times = self::map($options['times'] ?? null);

        return new self(
            honeypots: \array_key_exists('honeypots', $options)
                ? self::strings($options['honeypots'])
                : self::HONEYPOTS,
            plausibility: self::plausibility($options['plausibility'] ?? null),
            links: self::names($links['parameters'] ?? null),
            linkMaximum: self::integer($links['maximum'] ?? null, 1),
            equality: self::names($equality['parameters'] ?? null),
            equalityTolerance: self::integer($equality['tolerance'] ?? null, 1),
            expected: self::names($set['expected'] ?? null),
            tolerateUnexpected: (bool) ($set['tolerateUnexpected'] ?? false),
            requireComplete: (bool) ($set['requireComplete'] ?? false),
            duplicates: self::names($duplicates['parameters'] ?? null),
            duplicateLifetime: max(60, self::integer($duplicates['lifetime'] ?? null, $settings->duplicateLifetime)),
            stopwords: self::names(self::map($options['stopwords'] ?? null)['parameters'] ?? null),
            methods: \is_string($options['methods'] ?? null) ? $options['methods'] : $settings->methods,
            minimum: self::number($times['minimum'] ?? null, $settings->minimum),
            maximum: self::number($times['maximum'] ?? null, $settings->maximum),
            followup: self::number($times['followup'] ?? null, $settings->followup),
            ban: (bool) ($options['ban'] ?? $settings->ban),
        );
    }

    /**
     * An array with string keys, or an empty one
     *
     * @param mixed $value Value
     *
     * @return array<array-key, mixed>
     */
    private static function map(mixed $value): array
    {
        return \is_array($value) ? $value : [];
    }

    /**
     * Strings by name
     *
     * @param mixed $value Value
     *
     * @return array<string, string>
     */
    private static function strings(mixed $value): array
    {
        $strings = [];
        foreach (self::map($value) as $name => $type) {
            if (!\is_string($type) || '' === $type) {
                continue;
            }

            $strings[(string) $name] = $type;
        }

        return $strings;
    }

    /**
     * Field names
     *
     * @param mixed $value A list of names, or one name
     *
     * @return list<string>
     */
    private static function names(mixed $value): array
    {
        $names = \is_string($value) ? [$value] : self::map($value);

        return array_values(array_filter(
            array_map(static fn(mixed $name): string => \is_scalar($name) ? trim((string) $name) : '', $names),
            static fn(string $name): bool => '' !== $name,
        ));
    }

    /**
     * Rules of plausibility per field
     *
     * @param mixed $value Value
     *
     * @return Plausibility
     */
    private static function plausibility(mixed $value): array
    {
        $plausibility = [];
        foreach (self::map($value) as $field => $rules) {
            $limits = array_map(
                static fn(mixed $limit): int => self::integer($limit, 0),
                array_intersect_key(self::map($rules), array_flip(self::PLAUSIBILITY)),
            );
            if ([] === $limits) {
                continue;
            }

            $plausibility[(string) $field] = $limits;
        }

        return $plausibility;
    }

    /**
     * An integer, or a default
     *
     * @param mixed   $value   Value
     * @param integer $default Default
     *
     * @return integer
     */
    private static function integer(mixed $value, int $default): int
    {
        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * A number, or a default
     *
     * @param mixed $value   Value
     * @param float $default Default
     *
     * @return float
     */
    private static function number(mixed $value, float $default): float
    {
        return is_numeric($value) ? (float) $value : $default;
    }
}
