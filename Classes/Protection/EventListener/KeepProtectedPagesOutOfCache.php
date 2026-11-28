<?php

/**
 * Keeps a page that shows a protected form, or its notice, out of the page cache
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Protection\EventListener;

use Tollwerk\TwAntibot\Protection\ProtectedPage;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;

/**
 * Keeps a page that shows a protected form, or its notice, out of the page cache
 *
 * A guard disables the cache through the instruction of the request it was created with. That instruction may not be
 * the one of the page: a guard created in a middleware, before TYPO3 resolves the page, has none, and a form rendered
 * later in the page gets the same guard. The mark of the page holds in every case, and this listener reads it before
 * TYPO3 stores the page.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
#[AsEventListener('tollwerk/tw-antibot/keep-protected-pages-out-of-cache')]
final readonly class KeepProtectedPagesOutOfCache
{
    /**
     * Constructor
     *
     * @param ProtectedPage $page Whether the page shows a protected form or its notice
     */
    public function __construct(private ProtectedPage $page) {}

    /**
     * Disable the caching of a page that shows a protected form or its notice
     *
     * @param AfterCacheableContentIsGeneratedEvent $event Event
     *
     * @return void
     */
    public function __invoke(AfterCacheableContentIsGeneratedEvent $event): void
    {
        if (!$this->page->isProtected()) {
            return;
        }

        $event->disableCaching();
    }
}
