<?php

/**
 * tollwerk
 *
 * @category   Tollwerk
 * @package    Tollwerk\TwAntibot
 * @subpackage Tollwerk\TwAntibot\Store
 * @author     Joschi Kuphal <joschi@tollwerk.de>
 * @copyright  Copyright © 2026 Joschi Kuphal <joschi@tollwerk.de>
 * @license    http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Store;

use Jkphl\Antibot\Ports\Contract\ExpiringStoreInterface;
use TYPO3\CMS\Core\Cache\CacheManager;
use TYPO3\CMS\Core\Cache\Frontend\FrontendInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Cache Store
 *
 * Remembers what is meant to be forgotten again — content fingerprints above all.
 * Unlike a ban, which is a decision and belongs in the database where an editor can see
 * and revoke it, a fingerprint is an observation with a shelf life.
 */
class CacheStore implements ExpiringStoreInterface
{
    /**
     * Cache identifier
     *
     * @var string
     */
    const CACHE = 'tw_antibot';
    /**
     * Cache frontend
     *
     * @var FrontendInterface
     */
    protected $cache;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->cache = GeneralUtility::makeInstance(CacheManager::class)->getCache(self::CACHE);
    }

    /**
     * Test whether a key is currently present
     *
     * @param string $key Key
     *
     * @return bool Key is present
     */
    public function has(string $key): bool
    {
        return $this->cache->has($this->identifier($key));
    }

    /**
     * Remember a key for a given lifetime
     *
     * @param string $key   Key
     * @param int $lifetime Lifetime in seconds
     */
    public function remember(string $key, int $lifetime): void
    {
        if ($lifetime > 0) {
            $this->cache->set($this->identifier($key), time() + $lifetime, [], $lifetime);
        }
    }

    /**
     * Return the expiration timestamp of a key
     *
     * @param string $key Key
     *
     * @return int|null Expiration timestamp (NULL if the key is not present)
     */
    public function expiresAt(string $key): ?int
    {
        $expiration = $this->cache->get($this->identifier($key));

        return ($expiration === false) ? null : intval($expiration);
    }

    /**
     * Drop a key ahead of its expiration
     *
     * @param string $key Key
     */
    public function forget(string $key): void
    {
        $this->cache->remove($this->identifier($key));
    }

    /**
     * Turn a subject key into a cache entry identifier
     *
     * Cache identifiers may carry only a narrow set of characters; the keys the library
     * hands out carry colons, dots and whatever a form field contained.
     *
     * @param string $key Key
     *
     * @return string Cache entry identifier
     */
    protected function identifier(string $key): string
    {
        return sha1($key);
    }
}
