<?php

/**
 * A page that shows a protected form or its notice does not go into the page cache
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Tests\Unit\Protection\EventListener;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\Test;
use Tollwerk\TwAntibot\Protection\EventListener\KeepProtectedPagesOutOfCache;
use Tollwerk\TwAntibot\Protection\ProtectedPage;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Event\AfterCacheableContentIsGeneratedEvent;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * A page that shows a protected form or its notice does not go into the page cache
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class KeepProtectedPagesOutOfCacheTest extends UnitTestCase
{
    /**
     * A marked page is not cached; any other page keeps its caching
     *
     * @return void
     *
     * @throws InvalidArgumentException If the request is invalid
     */
    #[Test]
    public function onlyAMarkedPageStaysOutOfTheCache(): void
    {
        $page = new ProtectedPage();
        $listener = new KeepProtectedPagesOutOfCache($page);
        $event = new AfterCacheableContentIsGeneratedEvent(new ServerRequest('http://localhost/'), '', 'page', true);
        $listener($event);
        self::assertTrue($event->isCachingEnabled());

        $page->mark();
        $listener($event);
        self::assertFalse($event->isCachingEnabled());
    }
}
