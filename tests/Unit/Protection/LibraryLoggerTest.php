<?php

/**
 * The logger of the library passes on what may stand in a log, and nothing else
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
use Tollwerk\TwAntibot\Protection\LibraryLogger;
use Tollwerk\TwAntibot\Tests\Support\CollectingLogger;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The logger of the library passes on what may stand in a log, and nothing else
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class LibraryLoggerTest extends UnitTestCase
{
    /**
     * Messages for debugging, which carry sessions and addresses, stay out; the others lose their context
     *
     * @return void
     */
    #[Test]
    public function messagesForDebuggingAndContextsStayOut(): void
    {
        $records = new CollectingLogger();
        $logger = new LibraryLogger($records);
        $logger->debug('[HMAC] Created HMAC abc', ['session-id']);
        $logger->debug('[BAN ] Refused banned subject "ip:203.0.113.7"');
        $logger->info('Start validation', ['session' => 'session-id']);
        $logger->warning('Something failed', ['address' => '203.0.113.7']);

        self::assertSame([
            ['level' => 'info', 'message' => 'Start validation', 'context' => []],
            ['level' => 'warning', 'message' => 'Something failed', 'context' => []],
        ], $records->records);
    }
}
