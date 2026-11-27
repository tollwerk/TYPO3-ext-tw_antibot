<?php

/**
 * A page with protective fields leaves no copy in a cache on the way to the visitor
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Tests\Unit\Http;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tollwerk\TwAntibot\Http\KeepProtectedPagesPrivate;
use Tollwerk\TwAntibot\Protection\ProtectedPage;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * A page with protective fields leaves no copy in a cache on the way to the visitor
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class KeepProtectedPagesPrivateTest extends UnitTestCase
{
    /**
     * A page without protective fields keeps its headers
     *
     * @return void
     *
     * @throws InvalidArgumentException If a header is invalid
     */
    #[Test]
    public function aPageWithoutFieldsKeepsItsHeaders(): void
    {
        $page = new ProtectedPage();
        $request = new ServerRequest('http://localhost/');
        $response = new KeepProtectedPagesPrivate($page)->process($request, $this->handler());

        self::assertSame('public, max-age=86400', $response->getHeaderLine('Cache-Control'));
        self::assertFalse($response->hasHeader('Expires'));
    }

    /**
     * A page with protective fields is private, must not be stored and has expired already
     *
     * @return void
     *
     * @throws InvalidArgumentException If a header is invalid
     */
    #[Test]
    public function aPageWithFieldsIsPrivateAndExpired(): void
    {
        $page = new ProtectedPage();
        $page->mark();
        $request = new ServerRequest('http://localhost/');
        $response = new KeepProtectedPagesPrivate($page)->process($request, $this->handler());

        self::assertSame('private, no-store', $response->getHeaderLine('Cache-Control'));
        self::assertSame('Thu, 01 Jan 1970 00:00:00 GMT', $response->getHeaderLine('Expires'));
    }

    /**
     * Handler that answers with a page for a day in every cache
     *
     * @return RequestHandlerInterface
     */
    private function handler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            /**
             * Answer with a page for a day in every cache
             *
             * @param ServerRequestInterface $request Request
             *
             * @return ResponseInterface
             *
             * @throws InvalidArgumentException If a header is invalid
             */
            #[Override]
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return new Response()->withHeader('Cache-Control', 'public, max-age=86400');
            }
        };
    }
}
