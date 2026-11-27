<?php

/**
 * Keeps a page with protective fields out of every cache on the way to the visitor
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Http;

use InvalidArgumentException;
use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Tollwerk\TwAntibot\Protection\ProtectedPage;

/**
 * Keeps a page with protective fields out of every cache on the way to the visitor
 *
 * The fields belong to one visitor and carry the second they were handed out. A cached page would hand the fields of
 * the first visitor to everyone after them until they had aged past the time a form stays valid, and from then on the
 * form would refuse everybody. The guard keeps the page out of the page cache of TYPO3, which then sends
 * "Cache-Control: private, no-store" itself; this middleware makes sure of the header and adds an expiry in the past,
 * because a web server that gives PHP responses a default lifetime, such as with ExpiresDefault of Apache, leaves only
 * a response alone that names its expiry.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final readonly class KeepProtectedPagesPrivate implements MiddlewareInterface
{
    /**
     * Constructor
     *
     * @param ProtectedPage $page Whether the page carries protective fields
     */
    public function __construct(private ProtectedPage $page) {}

    /**
     * Add the headers to a response with protective fields
     *
     * @param ServerRequestInterface  $request Request
     * @param RequestHandlerInterface $handler Next handler
     *
     * @return ResponseInterface
     *
     * @throws InvalidArgumentException If a header is invalid
     */
    #[Override]
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);
        if (!$this->page->isProtected()) {
            return $response;
        }

        return $response->withHeader('Cache-Control', 'private, no-store')
            ->withHeader('Expires', 'Thu, 01 Jan 1970 00:00:00 GMT');
    }
}
