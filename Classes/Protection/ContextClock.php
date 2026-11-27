<?php

/**
 * Time of the request for jkphl/antibot, from the context of TYPO3
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

use Jkphl\Antibot\Ports\Contract\ClockInterface;
use Override;
use TYPO3\CMS\Core\Context\Context;
use TYPO3\CMS\Core\Context\Exception\AspectNotFoundException;

/**
 * Time of the request for jkphl/antibot, from the context of TYPO3
 *
 * The checks of timing and the ends of bans use the same time as the rest of TYPO3, which a test can set.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final readonly class ContextClock implements ClockInterface
{
    /**
     * Constructor
     *
     * @param Context $context Context of the request
     */
    public function __construct(private Context $context) {}

    /**
     * Time of the request
     *
     * @return integer Unix timestamp; the time of the system if the context has none
     */
    #[Override]
    public function now(): int
    {
        try {
            $now = $this->context->getPropertyFromAspect('date', 'timestamp');
        } catch (AspectNotFoundException) {
            return time();
        }

        return \is_int($now) ? $now : time();
    }
}
