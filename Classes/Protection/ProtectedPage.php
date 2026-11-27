<?php

/**
 * Whether the page of the request carries protective fields
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
 * Whether the page of the request carries protective fields
 *
 * A guard marks the page when it hands out fields; the middleware KeepProtectedPagesPrivate reads the mark.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class ProtectedPage
{
    /**
     * Whether the page carries protective fields
     *
     * @var boolean
     */
    private bool $protected = false;

    /**
     * Mark the page as carrying protective fields
     *
     * @return void
     */
    public function mark(): void
    {
        $this->protected = true;
    }

    /**
     * Whether the page carries protective fields
     *
     * @return boolean
     */
    public function isProtected(): bool
    {
        return $this->protected;
    }
}
