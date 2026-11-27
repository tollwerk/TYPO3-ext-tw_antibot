<?php

/**
 * Settings of tw_antibot for a site
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

use TYPO3\CMS\Core\Site\Entity\Site;

/**
 * Settings of tw_antibot for a site
 *
 * They come from the site set "tollwerk/tw-antibot" and hold for every protected form of the site; a form may override
 * the times, the order of methods and whether a refusal bans. Without a site, as in a test, the defaults hold.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final readonly class Settings
{
    /**
     * Constructor
     *
     * @param integer $storage           Folder for the records of bans
     * @param string  $contact           Address offered to a client whose form is closed; none if empty
     * @param boolean $ban               Whether a refused submission bans the client
     * @param integer $banPeriod         Seconds a ban lasts; until someone lifts it if 0
     * @param float   $minimum           Seconds before a form may come back
     * @param float   $maximum           Seconds a form stays valid
     * @param float   $followup          Seconds before a form may come back again
     * @param string  $methods           Order of request methods, such as "GET-POST"; any if empty
     * @param integer $duplicateLifetime Seconds an accepted submission is remembered
     */
    public function __construct(
        public int $storage = 0,
        public string $contact = '',
        public bool $ban = true,
        public int $banPeriod = 2592000,
        public float $minimum = 3,
        public float $maximum = 3600,
        public float $followup = 1,
        public string $methods = 'GET-POST',
        public int $duplicateLifetime = 86400,
    ) {}

    /**
     * Settings of a site
     *
     * The contact address falls back to the one for mails of tw_base.
     *
     * @param Site|null $site Site; the defaults if null
     *
     * @return self
     */
    public static function fromSite(?Site $site): self
    {
        $values = $site?->getSettings()->getAllFlat() ?? [];
        $defaults = new self();
        $contact = self::text($values['twAntibot.contact'] ?? null);

        return new self(
            storage: self::integer($values['twAntibot.storage'] ?? null, $defaults->storage),
            contact: '' === $contact ? self::text($values['twBase.mail.contact'] ?? null) : $contact,
            ban: (bool) ($values['twAntibot.ban'] ?? $defaults->ban),
            banPeriod: max(0, self::integer($values['twAntibot.banPeriod'] ?? null, $defaults->banPeriod)),
            minimum: (float) self::integer($values['twAntibot.time.minimum'] ?? null, (int) $defaults->minimum),
            maximum: (float) self::integer($values['twAntibot.time.maximum'] ?? null, (int) $defaults->maximum),
            followup: (float) self::integer($values['twAntibot.time.followup'] ?? null, (int) $defaults->followup),
            methods: self::text($values['twAntibot.methods'] ?? $defaults->methods),
            duplicateLifetime: self::integer(
                $values['twAntibot.duplicateLifetime'] ?? null,
                $defaults->duplicateLifetime,
            ),
        );
    }

    /**
     * A trimmed string, or an empty one
     *
     * @param mixed $value Value
     *
     * @return string
     */
    private static function text(mixed $value): string
    {
        return \is_scalar($value) ? trim((string) $value) : '';
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
}
