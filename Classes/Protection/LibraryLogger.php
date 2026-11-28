<?php

/**
 * Logger for jkphl/antibot, without what its messages for debugging carry
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

use Override;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Stringable;

/**
 * Logger for jkphl/antibot, without what its messages for debugging carry
 *
 * The library logs every step on the level "debug", with the session of the visitor in the context of a signature and
 * the address of a client in the message of a ban. Neither belongs in a log. This logger passes on the messages from
 * the level "info" up, without their context; the guard logs its judgements itself.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class LibraryLogger extends AbstractLogger
{
    /**
     * Constructor
     *
     * @param LoggerInterface $logger Logger the messages go to
     */
    public function __construct(private readonly LoggerInterface $logger) {}

    /**
     * Pass on a message from the level "info" up, without its context
     *
     * @param mixed                   $level   Level
     * @param string|Stringable       $message Message
     * @param array<array-key, mixed> $context Context, left out
     *
     * @return void
     */
    #[Override]
    public function log(mixed $level, string|Stringable $message, array $context = []): void
    {
        if (LogLevel::DEBUG === $level) {
            return;
        }

        $this->logger->log($level, $message);
    }
}
