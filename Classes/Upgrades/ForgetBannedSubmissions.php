<?php

/**
 * Forgets the submissions that bans recorded before they left out passwords
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

namespace Tollwerk\TwAntibot\Upgrades;

use Doctrine\DBAL\Exception as DBALException;
use Override;
use Tollwerk\TwAntibot\Store\BanStore;
use TYPO3\CMS\Core\Attribute\UpgradeWizard;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;

/**
 * Forgets the submissions that bans recorded before they left out passwords
 *
 * A ban recorded the whole submission, passwords included, in plain text. The records cannot tell a password from
 * other values, so this wizard empties the submission of every ban; the bans themselves stay.
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */
#[UpgradeWizard('twAntibot_forgetBannedSubmissions')]
final readonly class ForgetBannedSubmissions implements UpgradeWizardInterface
{
    /**
     * Constructor
     *
     * @param ConnectionPool $connectionPool Connections to the database
     */
    public function __construct(private ConnectionPool $connectionPool) {}

    /**
     * Title
     *
     * @return string
     */
    #[Override]
    public function getTitle(): string
    {
        return 'Forget the submissions recorded with bans';
    }

    /**
     * Description
     *
     * @return string
     */
    #[Override]
    public function getDescription(): string
    {
        return 'Bans of tw_antibot recorded the whole submission, passwords included. This empties the submission of'
            . ' every ban; the bans themselves stay.';
    }

    /**
     * Wizards that must have run before
     *
     * @return list<class-string>
     */
    #[Override]
    public function getPrerequisites(): array
    {
        return [DatabaseUpdatedPrerequisite::class];
    }

    /**
     * Whether a ban still holds a submission
     *
     * @return boolean
     *
     * @throws DBALException If the database cannot be read
     */
    #[Override]
    public function updateNecessary(): bool
    {
        $query = $this->connectionPool->getQueryBuilderForTable(BanStore::TABLE);
        $query->getRestrictions()->removeAll();

        $count = $query->count('uid')
            ->from(BanStore::TABLE)
            ->where($query->expr()->neq('data', $query->createNamedParameter('')))
            ->executeQuery()
            ->fetchOne();

        return is_numeric($count) && 0 < (int) $count;
    }

    /**
     * Empty the submission of every ban
     *
     * @return boolean
     *
     * @throws DBALException If the database cannot be written
     */
    #[Override]
    public function executeUpdate(): bool
    {
        $query = $this->connectionPool->getQueryBuilderForTable(BanStore::TABLE);
        $query->getRestrictions()->removeAll();
        $query->update(BanStore::TABLE)
            ->set('data', '')
            ->where($query->expr()->neq('data', $query->createNamedParameter('')))
            ->executeStatement();

        return true;
    }
}
