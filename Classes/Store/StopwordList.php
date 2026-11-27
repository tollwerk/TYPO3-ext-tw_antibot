<?php

/**
 * Stopwords that editors maintain as records
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
use Jkphl\Antibot\Ports\Contract\StopwordListInterface;
use Override;
use TYPO3\CMS\Core\Database\ConnectionPool;

/**
 * Stopwords that editors maintain as records
 *
 * A stopword holds for the whole installation, in every form that names fields for it. A disabled or deleted stopword
 * takes no part. The records are read once per request and only when a submission has a field to check.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
final class StopwordList implements StopwordListInterface
{
    /**
     * Table of the stopwords
     *
     * @var string
     */
    public const string TABLE = 'tx_twantibot_domain_model_stopword';

    /**
     * Stopwords, once read
     *
     * @var list<string>|null
     */
    private ?array $stopwords = null;

    /**
     * Constructor
     *
     * @param ConnectionPool $connectionPool Connections to the database
     */
    public function __construct(private readonly ConnectionPool $connectionPool) {}

    /**
     * Stopwords that are enabled
     *
     * @return list<string>
     *
     * @throws DBALException If the database fails
     */
    #[Override]
    public function getStopwords(): array
    {
        return $this->stopwords ??= $this->read();
    }

    /**
     * Read the stopwords
     *
     * @return list<string>
     *
     * @throws DBALException If the database fails
     */
    private function read(): array
    {
        $query = $this->connectionPool->getQueryBuilderForTable(self::TABLE);
        $terms = $query->select('term')
            ->from(self::TABLE)
            ->where($query->expr()->neq('term', $query->createNamedParameter('')))
            ->executeQuery()
            ->fetchFirstColumn();
        $terms = array_map(static fn(mixed $term): string => \is_string($term) ? trim($term) : '', $terms);

        return array_values(array_unique(array_filter($terms, static fn(string $term): bool => '' !== $term)));
    }
}
