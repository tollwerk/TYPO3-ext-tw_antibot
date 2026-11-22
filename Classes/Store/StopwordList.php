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

use Jkphl\Antibot\Ports\Contract\StopwordListInterface;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Database\Query\Restriction\HiddenRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Stopword List
 *
 * Backs the library's stopword check with the stopword records of this extension. The
 * records hold for the whole installation, wherever they are stored; a record that is
 * switched off is not part of the list.
 *
 * The library asks for the terms only once a submission has a field to check, and it
 * asks once per validation — the table is not read on every page that carries a form.
 */
class StopwordList implements StopwordListInterface
{
    /**
     * Table holding the stopword records
     *
     * @var string
     */
    const TABLE = 'tx_twantibot_domain_model_stopword';
    /**
     * Stopwords, once read
     *
     * @var string[]|null
     */
    protected $stopwords = null;

    /**
     * Return the stopwords
     *
     * @return string[] Stopwords
     */
    public function getStopwords(): array
    {
        if ($this->stopwords === null) {
            $this->stopwords = $this->fetchStopwords();
        }

        return $this->stopwords;
    }

    /**
     * Read the stopwords from the database
     *
     * @return string[] Stopwords
     */
    protected function fetchStopwords(): array
    {
        $query = $this->createQueryBuilder();
        $terms = $query->select('term')
            ->from(self::TABLE)
            ->where($query->expr()->neq('term', $query->createNamedParameter('')))
            ->execute()
            ->fetchAll(\PDO::FETCH_COLUMN);

        return array_values(array_unique(array_filter(array_map('trim', array_map('strval', $terms)), 'strlen')));
    }

    /**
     * Create a query builder for the stopword table
     *
     * @return QueryBuilder Query builder
     */
    protected function createQueryBuilder(): QueryBuilder
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)->getQueryBuilderForTable(self::TABLE);
        $queryBuilder->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(HiddenRestriction::class));

        return $queryBuilder;
    }
}
