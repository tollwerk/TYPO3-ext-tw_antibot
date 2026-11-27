<?php

/**
 * A form asks for checks with an array, on top of the settings of its site
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
use Tollwerk\TwAntibot\Protection\Options;
use Tollwerk\TwAntibot\Protection\Settings;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * A form asks for checks with an array, on top of the settings of its site
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class OptionsTest extends UnitTestCase
{
    /**
     * A form that just asks for protection gets honeypots and the settings of its site, and no check of content
     *
     * @return void
     */
    #[Test]
    public function aFormWithoutOptionsGetsHoneypotsAndTheSettings(): void
    {
        $settings = new Settings(
            ban: false,
            minimum: 5,
            maximum: 600,
            followup: 2,
            methods: 'POST',
            duplicateLifetime: 120,
        );
        $options = Options::fromArray([], $settings);

        self::assertSame(Options::HONEYPOTS, $options->honeypots);
        self::assertSame([], $options->plausibility);
        self::assertSame([], $options->links);
        self::assertSame([], $options->duplicates);
        self::assertSame([], $options->stopwords);
        self::assertFalse($options->ban);
        self::assertSame(5.0, $options->minimum);
        self::assertSame(600.0, $options->maximum);
        self::assertSame(2.0, $options->followup);
        self::assertSame('POST', $options->methods);
        self::assertSame(120, $options->duplicateLifetime);
    }

    /**
     * The checks of content take the field names of the form, and invalid values fall back to the defaults
     *
     * @return void
     */
    #[Test]
    public function aFormAsksForChecksOfContent(): void
    {
        $options = Options::fromArray([
            'honeypots' => ['website' => 'url', 'empty' => '', 'invalid' => []],
            'plausibility' => ['message' => ['minWords' => '5', 'maxLength' => 5000, 'colour' => 'red'], 'name' => []],
            'links' => ['parameters' => ['message', ' ', 'subject'], 'maximum' => 2],
            'equality' => ['parameters' => 'name', 'tolerance' => 'many'],
            'parameterSet' => ['expected' => ['name', 'message'], 'tolerateUnexpected' => 1, 'requireComplete' => true],
            'duplicates' => ['parameters' => ['message'], 'lifetime' => 10],
            'stopwords' => ['parameters' => ['name', 'message']],
            'methods' => 'GET-POST',
            'times' => ['minimum' => '0', 'maximum' => 60, 'followup' => 'soon'],
            'ban' => 0,
        ], new Settings());

        self::assertSame(['website' => 'url'], $options->honeypots);
        self::assertSame(['message' => ['minWords' => 5, 'maxLength' => 5000]], $options->plausibility);
        self::assertSame(['message', 'subject'], $options->links);
        self::assertSame(2, $options->linkMaximum);
        self::assertSame(['name'], $options->equality);
        self::assertSame(1, $options->equalityTolerance);
        self::assertSame(['name', 'message'], $options->expected);
        self::assertTrue($options->tolerateUnexpected);
        self::assertTrue($options->requireComplete);
        self::assertSame(['message'], $options->duplicates);
        self::assertSame(60, $options->duplicateLifetime, 'An accepted submission is remembered for a minute at least');
        self::assertSame(['name', 'message'], $options->stopwords);
        self::assertSame(0.0, $options->minimum);
        self::assertSame(60.0, $options->maximum);
        self::assertSame(1.0, $options->followup);
        self::assertFalse($options->ban);
    }

    /**
     * A form may do without honeypots
     *
     * @return void
     */
    #[Test]
    public function aFormMayDoWithoutHoneypots(): void
    {
        self::assertSame([], Options::fromArray(['honeypots' => []], new Settings())->honeypots);
        self::assertSame([], Options::fromArray(['honeypots' => false], new Settings())->honeypots);
    }
}
