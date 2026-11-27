<?php

/**
 * The settings of a site hold for all of its forms, with the defaults where a site sets nothing
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Tests\Unit\Protection;

use PHPUnit\Framework\Attributes\Test;
use Tollwerk\TwAntibot\Protection\Settings;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\Entity\SiteSettings;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The settings of a site hold for all of its forms, with the defaults where a site sets nothing
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class SettingsTest extends UnitTestCase
{
    /**
     * Without a site, the defaults hold: a month of ban, at least three seconds and at most an hour
     *
     * @return void
     */
    #[Test]
    public function withoutASiteTheDefaultsHold(): void
    {
        self::assertEquals(new Settings(), Settings::fromSite(null));
        self::assertSame(2592000, Settings::fromSite(null)->banPeriod);
        self::assertSame(3.0, Settings::fromSite(null)->minimum);
    }

    /**
     * A site sets the folder, the ban, the times and the order of methods
     *
     * @return void
     */
    #[Test]
    public function aSiteSetsItsSettings(): void
    {
        $settings = Settings::fromSite($this->site([
            'twAntibot' => [
                'storage' => 7,
                'contact' => ' antibot@example.org ',
                'ban' => false,
                'banPeriod' => -5,
                'methods' => 'POST',
                'duplicateLifetime' => 600,
                'time' => ['minimum' => 2, 'maximum' => 900, 'followup' => 0],
            ],
            'twBase' => ['mail' => ['contact' => 'hello@example.org']],
        ]));

        self::assertSame(7, $settings->storage);
        self::assertSame('antibot@example.org', $settings->contact);
        self::assertFalse($settings->ban);
        self::assertSame(0, $settings->banPeriod, 'A negative period means a ban until somebody lifts it');
        self::assertSame('POST', $settings->methods);
        self::assertSame(600, $settings->duplicateLifetime);
        self::assertSame(2.0, $settings->minimum);
        self::assertSame(900.0, $settings->maximum);
        self::assertSame(0.0, $settings->followup);
    }

    /**
     * Without an address of its own, the notice offers the contact address for mails
     *
     * @return void
     */
    #[Test]
    public function theContactFallsBackToTheAddressForMails(): void
    {
        $site = $this->site([
            'twAntibot' => ['contact' => ''],
            'twBase' => ['mail' => ['contact' => 'hello@example.org']],
        ]);

        self::assertSame('hello@example.org', Settings::fromSite($site)->contact);
    }

    /**
     * Site with settings
     *
     * @param array<string, mixed> $settings Tree of settings
     *
     * @return Site
     */
    private function site(array $settings): Site
    {
        return new Site('main', 1, ['base' => 'http://localhost/'], SiteSettings::createFromSettingsTree($settings));
    }
}
