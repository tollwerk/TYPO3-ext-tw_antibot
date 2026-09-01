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
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Blacklist Store
 *
 * Backs the library's ban management with the blacklist records of this extension. A
 * ban lasts until an editor removes the record — that is the whole point of keeping it
 * in the database rather than in a cache: it is a decision, and decisions are revoked
 * by people, not by timeouts.
 *
 * The library addresses subjects by an opaque key; this store understands the two
 * shapes it produces and maps them onto the record's property and value, so that the
 * backend list shows an address and not a key.
 */
class BlacklistStore implements ExpiringStoreInterface
{
    /**
     * Table holding the blacklist records
     *
     * @var string
     */
    const TABLE = 'tx_twantibot_domain_model_blacklist';
    /**
     * Client address
     *
     * @var int
     */
    const PROPERTY_IP = 0;
    /**
     * Form parameter, e.g. an e-mail address
     *
     * @var int
     */
    const PROPERTY_PARAMETER = 1;
    /**
     * Storage page for new records
     *
     * @var int
     */
    protected $storagePid;
    /**
     * Seconds a new ban lasts (0 = until someone lifts it)
     *
     * @var int
     */
    protected $period;
    /**
     * Whether expired records have already been removed in this request
     *
     * @var bool
     */
    protected static $collected = false;

    /**
     * Constructor
     *
     * @param int $storagePid Storage page for new records
     * @param int $period     Seconds a new ban lasts (0 = until someone lifts it)
     */
    public function __construct(int $storagePid = 0, int $period = 0)
    {
        $this->storagePid = $storagePid;
        $this->period     = max(0, $period);

        $this->collectGarbage();
    }

    /**
     * Remove records whose ban has run out
     *
     * Filtering them out of the queries would be enough to lift the ban, but not enough
     * to be done with them: the record carries the whole submission — a name, an address,
     * whatever someone wrote. Once the ban is over there is no reason left to keep it,
     * and a retention period that quietly never ends is not a retention period.
     *
     * Runs once per request rather than on a schedule, so that the promise holds on an
     * installation where nobody set a scheduler task up.
     *
     * @return int Number of records removed
     */
    public function collectGarbage(): int
    {
        if (self::$collected) {
            return 0;
        }
        self::$collected = true;

        $query = $this->createQueryBuilder();

        return intval(
            $query->delete(self::TABLE)
                ->where(
                    $query->expr()->gt('endtime', $query->createNamedParameter(0, \PDO::PARAM_INT)),
                    $query->expr()->lt('endtime', $query->createNamedParameter(time(), \PDO::PARAM_INT))
                )
                ->execute()
        );
    }

    /**
     * Test whether a subject is currently banned
     *
     * @param string $key Subject key
     *
     * @return bool Subject is banned
     */
    public function has(string $key): bool
    {
        $subject = $this->parse($key);
        if ($subject === null) {
            return false;
        }

        $query = $this->createQueryBuilder();
        $count = $query->count('uid')
            ->from(self::TABLE)
            ->where(
                $query->expr()->eq('property', $query->createNamedParameter($subject[0], \PDO::PARAM_INT)),
                $query->expr()->eq('value', $query->createNamedParameter($subject[1])),
                // A ban whose end time has passed is no ban any more. Zero means it has
                // none and stands until someone takes it back.
                $query->expr()->orX(
                    $query->expr()->eq('endtime', $query->createNamedParameter(0, \PDO::PARAM_INT)),
                    $query->expr()->gt('endtime', $query->createNamedParameter(time(), \PDO::PARAM_INT))
                )
            )
            ->execute()
            ->fetchColumn(0);

        return intval($count) > 0;
    }

    /**
     * Ban a subject
     *
     * The lifetime is accepted for the sake of the contract and deliberately ignored: a
     * ban imposed here stands until someone with backend access takes it back.
     *
     * @param string $key   Subject key
     * @param int $lifetime Lifetime in seconds (ignored)
     */
    public function remember(string $key, int $lifetime): void
    {
        $this->ban($key, '', '');
    }

    /**
     * Ban a subject and record why
     *
     * The record carries the reason and what was submitted, so that whoever later
     * decides whether to lift the ban can see what earned it. Without that, the entry
     * would be an address and nothing else, and no one could tell a spambot from a
     * false positive.
     *
     * @param string $key    Subject key
     * @param string $reason Reason for the ban
     * @param string $data   What was submitted
     *
     * @return bool A new ban was recorded
     */
    public function ban(string $key, string $reason, string $data = ''): bool
    {
        $subject = $this->parse($key);
        if ($subject === null) {
            return false;
        }

        // An expired record for the same subject is taken over rather than duplicated;
        // the folder should hold one entry per subject, not a history of one.
        $query = $this->createQueryBuilder();
        $existing = $query->select('uid')
            ->from(self::TABLE)
            ->where(
                $query->expr()->eq('property', $query->createNamedParameter($subject[0], \PDO::PARAM_INT)),
                $query->expr()->eq('value', $query->createNamedParameter($subject[1]))
            )
            ->execute()
            ->fetchColumn(0);

        $values = [
            'tstamp'   => time(),
            'property' => $subject[0],
            'value'    => $subject[1],
            'error'    => $reason,
            'data'     => $data,
            'endtime'  => $this->period ? (time() + $this->period) : 0,
        ];

        if ($existing !== false) {
            if ($this->has($key)) {
                return false;
            }

            $update = $this->createQueryBuilder();
            $update->update(self::TABLE)
                ->where($update->expr()->eq('uid', $update->createNamedParameter(intval($existing), \PDO::PARAM_INT)));
            foreach ($values as $column => $value) {
                $update->set($column, $value);
            }
            $update->execute();

            return true;
        }

        $this->createQueryBuilder()
            ->insert(self::TABLE)
            ->values($values + ['pid' => $this->storagePid, 'crdate' => time()])
            ->execute();

        return true;
    }

    /**
     * Return the expiration timestamp of a ban
     *
     * A ban has none. The contract asks for a timestamp, so the answer is the end of
     * time rather than a date that would come to pass.
     *
     * @param string $key Subject key
     *
     * @return int|null End of time, or NULL if the subject is not banned
     */
    public function expiresAt(string $key): ?int
    {
        return $this->has($key) ? PHP_INT_MAX : null;
    }

    /**
     * Lift a ban
     *
     * @param string $key Subject key
     */
    public function forget(string $key): void
    {
        $subject = $this->parse($key);
        if ($subject === null) {
            return;
        }

        $query = $this->createQueryBuilder();
        $query->delete(self::TABLE)
            ->where(
                $query->expr()->eq('property', $query->createNamedParameter($subject[0], \PDO::PARAM_INT)),
                $query->expr()->eq('value', $query->createNamedParameter($subject[1]))
            )
            ->execute();
    }

    /**
     * Split a subject key into property and value
     *
     * @param string $key Subject key
     *
     * @return array|null Property and value, or NULL if the key is not understood
     */
    protected function parse(string $key): ?array
    {
        // A client address: "ip:1.2.3.4"
        if (strpos($key, 'ip:') === 0) {
            $value = substr($key, 3);

            return strlen($value) ? [self::PROPERTY_IP, $value] : null;
        }

        // A form parameter: "param:<path>:<value>"
        if (strpos($key, 'param:') === 0) {
            $parts = explode(':', $key, 3);

            return (count($parts) === 3) && strlen($parts[2]) ? [self::PROPERTY_PARAMETER, $parts[2]] : null;
        }

        return null;
    }

    /**
     * Create a query builder for the blacklist table
     *
     * @return \TYPO3\CMS\Core\Database\Query\QueryBuilder Query builder
     */
    protected function createQueryBuilder()
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));

        return $queryBuilder;
    }
}
