<?php

/**
 * Fingerprints of accepted submissions in a cache, for as long as they are remembered
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Store;

use Jkphl\Antibot\Ports\Contract\ExpiringStoreInterface;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;

/**
 * Fingerprints of accepted submissions in a cache, for as long as they are remembered
 *
 * What is meant to expire lives in the cache "tw_antibot"; bans are decisions and live in the database.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final readonly class CacheStore implements ExpiringStoreInterface
{
    /**
     * Constructor
     *
     * @param FrontendInterface $cache Cache "tw_antibot"
     */
    public function __construct(#[Autowire(service: 'cache.tw_antibot')] private FrontendInterface $cache) {}

    /**
     * Whether a key is remembered
     *
     * @param string $key Key
     *
     * @return boolean
     */
    #[Override]
    public function has(string $key): bool
    {
        return $this->cache->has($this->identifier($key));
    }

    /**
     * Remember a key
     *
     * @param string  $key      Key
     * @param integer $lifetime Seconds; not at all if 0
     *
     * @return void
     */
    #[Override]
    public function remember(string $key, int $lifetime): void
    {
        if ($lifetime <= 0) {
            return;
        }

        $this->cache->set($this->identifier($key), time() + $lifetime, [], $lifetime);
    }

    /**
     * When a key is forgotten
     *
     * @param string $key Key
     *
     * @return integer|null Time; null if the key is not remembered
     */
    #[Override]
    public function expiresAt(string $key): ?int
    {
        $expiration = $this->cache->get($this->identifier($key));

        return \is_int($expiration) ? $expiration : null;
    }

    /**
     * Forget a key
     *
     * @param string $key Key
     *
     * @return void
     */
    #[Override]
    public function forget(string $key): void
    {
        $this->cache->remove($this->identifier($key));
    }

    /**
     * Identifier in the cache, which allows fewer characters than a key
     *
     * @param string $key Key
     *
     * @return string
     */
    private function identifier(string $key): string
    {
        return hash('sha256', $key);
    }
}
