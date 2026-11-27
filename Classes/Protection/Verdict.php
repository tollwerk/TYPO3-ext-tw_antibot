<?php

/**
 * Judgement of a submission
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
 * Judgement of a submission
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
enum Verdict
{
    /**
     * The submission passed every check; the form goes on with its own validation
     */
    case Accepted;

    /**
     * The submission failed a check or came without the protective fields; the client is banned if the form says so,
     * unless the content was sent before
     */
    case Refused;

    /**
     * The client was banned before
     */
    case Banned;

    /**
     * Whether the form is closed and the notice takes its place
     *
     * @return boolean
     */
    public function isClosed(): bool
    {
        return self::Accepted !== $this;
    }
}
