<?php

/**
 * A ban is a record that ends after its period, holds the submission and goes when an editor deletes it
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Tests\Functional;

use Doctrine\DBAL\Exception as DBALException;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\Test;
use Tollwerk\TwAntibot\Store\BanStore;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * A ban is a record that ends after its period, holds the submission and goes when an editor deletes it
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class BanStoreTest extends FunctionalTestCase
{
    /**
     * Extensions under test
     *
     * @var array<non-empty-string>
     */
    #[Override]
    protected array $testExtensionsToLoad = ['tollwerk/tw-base', 'tollwerk/tw-antibot'];

    /**
     * Extensions of the core that tw_base needs
     *
     * @var array<non-empty-string>
     */
    #[Override]
    protected array $coreExtensionsToLoad = ['filemetadata'];

    /**
     * A ban holds the reason and the submission in the folder of the site, and a second ban adds nothing
     *
     * @return void
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If a class cannot be instantiated
     */
    #[Test]
    public function aBanIsRecordedOnce(): void
    {
        $store = $this->store(period: 60, now: 1000);

        self::assertFalse($store->has('ip:203.0.113.7'));
        self::assertTrue($store->ban('ip:203.0.113.7', 'Honeypot', '{"message":"Hi"}'));
        self::assertFalse($store->ban('ip:203.0.113.7', 'Again'));
        self::assertTrue($store->has('ip:203.0.113.7'));
        self::assertSame(PHP_INT_MAX, $store->expiresAt('ip:203.0.113.7'));
        self::assertFalse($store->has('ip:203.0.113.8'));
        self::assertNull($store->expiresAt('ip:203.0.113.8'));

        self::assertSame([[
            'pid' => 5,
            'property' => BanStore::ADDRESS,
            'value' => '203.0.113.7',
            'error' => 'Honeypot',
            'data' => '{"message":"Hi"}',
            'endtime' => 1060,
            'crdate' => 1000,
        ]], $this->records());
    }

    /**
     * A ban ends after its period, and its record goes with it
     *
     * @return void
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If a class cannot be instantiated
     */
    #[Test]
    public function aBanEndsAfterItsPeriod(): void
    {
        $this->store(period: 60, now: 1000)->ban('ip:203.0.113.7', 'Honeypot');

        self::assertTrue($this->store(period: 60, now: 1059)->has('ip:203.0.113.7'));
        self::assertFalse($this->store(period: 60, now: 1060)->has('ip:203.0.113.7'));
        self::assertSame([], $this->records());
    }

    /**
     * A ban without a period lasts until somebody lifts it
     *
     * @return void
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If a class cannot be instantiated
     */
    #[Test]
    public function aBanWithoutAPeriodLastsUntilLifted(): void
    {
        $this->store(period: 0, now: 1000)->ban('ip:203.0.113.7', 'Honeypot');

        self::assertTrue($this->store(period: 0, now: PHP_INT_MAX)->has('ip:203.0.113.7'));
        $this->store(period: 0, now: 1000)->forget('ip:203.0.113.7');
        self::assertFalse($this->store(period: 0, now: 1000)->has('ip:203.0.113.7'));
    }

    /**
     * The value of a field can be banned, and remember() bans without a reason
     *
     * @return void
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If a class cannot be instantiated
     */
    #[Test]
    public function aValueOfAFieldCanBeBanned(): void
    {
        $store = $this->store(period: 0, now: 1000);
        $store->remember('param:contact.email:spam@example.org', 3600);

        self::assertTrue($store->has('param:contact.email:spam@example.org'));
        self::assertFalse($store->has('ip:spam@example.org'));
        self::assertSame(BanStore::VALUE, $this->records()[0]['property'] ?? null);
        self::assertSame('', $this->records()[0]['error'] ?? null);
    }

    /**
     * A key that names no subject bans nothing
     *
     * @return void
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If a class cannot be instantiated
     */
    #[Test]
    public function aKeyWithoutASubjectBansNothing(): void
    {
        $store = $this->store(period: 0, now: 1000);

        foreach (['ip:', 'param:email:', 'param:email', 'session:abc', ''] as $key) {
            self::assertFalse($store->ban($key, 'Nothing'), $key);
            self::assertFalse($store->has($key), $key);
            $store->forget($key);
        }

        self::assertSame([], $this->records());
    }

    /**
     * Store of bans in folder 5
     *
     * @param integer $period Seconds a ban lasts
     * @param integer $now    Current time
     *
     * @return BanStore
     *
     * @throws InvalidArgumentException If a class cannot be instantiated
     */
    private function store(int $period, int $now): BanStore
    {
        return new BanStore(GeneralUtility::makeInstance(ConnectionPool::class), 5, $period, $now);
    }

    /**
     * Records of bans
     *
     * @return list<array<string, mixed>>
     *
     * @throws DBALException If the database fails
     * @throws InvalidArgumentException If a class cannot be instantiated
     */
    private function records(): array
    {
        $query = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(BanStore::TABLE);
        $query->getRestrictions()->removeAll();

        return array_values($query->select('pid', 'property', 'value', 'error', 'data', 'endtime', 'crdate')
            ->from(BanStore::TABLE)
            ->executeQuery()
            ->fetchAllAssociative());
    }
}
