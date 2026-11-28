<?php

/**
 * Whether the page of the request shows a protected form or its notice
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
 * Whether the page of the request shows a protected form or its notice
 *
 * A guard marks the page when the page shows its form or its notice; the listener KeepProtectedPagesOutOfCache and the
 * middleware KeepProtectedPagesPrivate read the mark.
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
     * Whether the page shows a protected form or its notice
     *
     * @var boolean
     */
    private bool $protected = false;

    /**
     * Mark the page as showing a protected form or its notice
     *
     * @return void
     */
    public function mark(): void
    {
        $this->protected = true;
    }

    /**
     * Whether the page shows a protected form or its notice
     *
     * @return boolean
     */
    public function isProtected(): bool
    {
        return $this->protected;
    }
}
