<?php

/**
 * Logger that keeps its records for a test to read
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Tests\Support;

use Override;
use Psr\Log\AbstractLogger;
use Stringable;

/**
 * Logger that keeps its records for a test to read
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class CollectingLogger extends AbstractLogger
{
    /**
     * Records, in the order they were logged
     *
     * @var list<array{level: mixed, message: string, context: array<array-key, mixed>}>
     */
    public array $records = [];

    /**
     * Keep a record
     *
     * @param mixed                   $level   Level
     * @param string|Stringable       $message Message
     * @param array<array-key, mixed> $context Context
     *
     * @return void
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
    }
}
