<?php

/**
 * Bans as records in a folder, which an editor reads and deletes
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

use Doctrine\DBAL\Exception as DBALException;
use Jkphl\Antibot\Ports\Contract\ExpiringStoreInterface;
use Override;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;

/**
 * Bans as records in a folder, which an editor reads and deletes
 *
 * A ban holds the whole submission, so that whoever opens the record can judge whether it was spam or a person the
 * protection should not have caught. It ends after a period or, with a period of 0, when an editor deletes it. A record
 * whose ban has ended is deleted, not hidden: a note about somebody's submission has no reason to outlive its ban.
 *
 * The keys come from the BanValidator of jkphl/antibot: "ip:<address>" for a client, "param:<path>:<value>" for the
 * value of a field.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class BanStore implements ExpiringStoreInterface
{
    /**
     * Table of the bans
     *
     * @var string
     */
    public const string TABLE = 'tx_twantibot_domain_model_blacklist';

    /**
     * Kind of a banned client address
     *
     * @var integer
     */
    public const int ADDRESS = 0;

    /**
     * Kind of a banned value of a field
     *
     * @var integer
     */
    public const int VALUE = 1;

    /**
     * Whether the records of ended bans were deleted in this request
     *
     * @var boolean
     */
    private bool $collected = false;

    /**
     * Constructor
     *
     * @param ConnectionPool $connectionPool Connections to the database
     * @param integer        $storage        Folder for new records
     * @param integer        $period         Seconds a new ban lasts; until someone lifts it if 0
     * @param integer        $now            Current time
     */
    public function __construct(
        private readonly ConnectionPool $connectionPool,
        private readonly int $storage,
        private readonly int $period,
        private readonly int $now,
    ) {}

    /**
     * Whether a subject is banned
     *
     * @param string $key Subject
     *
     * @return boolean
     *
     * @throws DBALException If the database fails
     */
    #[Override]
    public function has(string $key): bool
    {
        return null !== $this->find($key, true);
    }

    /**
     * Ban a subject without a reason, as the interface asks
     *
     * @param string  $key      Subject
     * @param integer $lifetime Ignored: a ban lasts for the period of the site
     *
     * @return void
     *
     * @throws DBALException If the database fails
     */
    #[Override]
    public function remember(string $key, int $lifetime): void
    {
        $this->ban($key, '');
    }

    /**
     * Ban a subject, with the reason and the submission
     *
     * The record of an ended ban of the same subject is taken over; the folder holds one record per subject.
     *
     * @param string $key    Subject
     * @param string $reason Reason
     * @param string $data   Submission as JSON
     *
     * @return boolean Whether the subject was not banned before
     *
     * @throws DBALException If the database fails
     */
    public function ban(string $key, string $reason, string $data = ''): bool
    {
        $subject = $this->parse($key);
        if (null === $subject || $this->has($key)) {
            return false;
        }

        $values = [
            'tstamp' => $this->now,
            'property' => $subject[0],
            'value' => $subject[1],
            'error' => $reason,
            'data' => $data,
            'endtime' => $this->period > 0 ? $this->now + $this->period : 0,
        ];
        $connection = $this->connectionPool->getConnectionForTable(self::TABLE);
        $existing = $this->find($key, false);
        if (null !== $existing) {
            $connection->update(self::TABLE, $values, ['uid' => $existing]);

            return true;
        }

        $connection->insert(self::TABLE, $values + ['pid' => $this->storage, 'crdate' => $this->now]);

        return true;
    }

    /**
     * End of the ban of a subject
     *
     * @param string $key Subject
     *
     * @return integer|null Never ending while banned; null if not banned
     *
     * @throws DBALException If the database fails
     */
    #[Override]
    public function expiresAt(string $key): ?int
    {
        return $this->has($key) ? PHP_INT_MAX : null;
    }

    /**
     * Lift the ban of a subject
     *
     * @param string $key Subject
     *
     * @return void
     *
     * @throws DBALException If the database fails
     */
    #[Override]
    public function forget(string $key): void
    {
        $subject = $this->parse($key);
        if (null === $subject) {
            return;
        }

        $this->connectionPool->getConnectionForTable(self::TABLE)
            ->delete(self::TABLE, ['property' => $subject[0], 'value' => $subject[1]]);
    }

    /**
     * Delete the records of ended bans, once per request
     *
     * @return integer Number of deleted records
     *
     * @throws DBALException If the database fails
     */
    public function collectGarbage(): int
    {
        if ($this->collected) {
            return 0;
        }

        $this->collected = true;
        $query = $this->query();

        return $query->delete(self::TABLE)
            ->where(
                $query->expr()->gt('endtime', $query->createNamedParameter(0, Connection::PARAM_INT)),
                $query->expr()->lte('endtime', $query->createNamedParameter($this->now, Connection::PARAM_INT)),
            )
            ->executeStatement();
    }

    /**
     * Record of a subject
     *
     * @param string  $key    Subject
     * @param boolean $active Whether only a ban that has not ended counts
     *
     * @return integer|null Uid of the record; null if there is none
     *
     * @throws DBALException If the database fails
     */
    private function find(string $key, bool $active): ?int
    {
        $subject = $this->parse($key);
        if (null === $subject) {
            return null;
        }

        $this->collectGarbage();
        $query = $this->query();
        $query->select('uid')
            ->from(self::TABLE)
            ->where(
                $query->expr()->eq('property', $query->createNamedParameter($subject[0], Connection::PARAM_INT)),
                $query->expr()->eq('value', $query->createNamedParameter($subject[1])),
            )
            ->setMaxResults(1);
        if ($active) {
            $query->andWhere($query->expr()->or(
                $query->expr()->eq('endtime', $query->createNamedParameter(0, Connection::PARAM_INT)),
                $query->expr()->gt('endtime', $query->createNamedParameter($this->now, Connection::PARAM_INT)),
            ));
        }

        $uid = $query->executeQuery()->fetchOne();

        return is_numeric($uid) ? (int) $uid : null;
    }

    /**
     * Kind and value of a subject
     *
     * @param string $key Subject
     *
     * @return array{integer, string}|null Null if the key names no subject
     */
    private function parse(string $key): ?array
    {
        if (str_starts_with($key, 'ip:')) {
            $value = substr($key, 3);

            return '' === $value ? null : [self::ADDRESS, $value];
        }

        $parts = explode(':', $key, 3);
        if ('param' !== $parts[0] || !isset($parts[2]) || '' === $parts[2]) {
            return null;
        }

        return [self::VALUE, $parts[2]];
    }

    /**
     * Query on the table of bans, also on records whose ban has ended
     *
     * The table has no flag for deleted records: deleting a record in the backend removes it and lifts the ban.
     *
     * @return QueryBuilder
     */
    private function query(): QueryBuilder
    {
        $query = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $query->getRestrictions()->removeAll();

        return $query;
    }
}
