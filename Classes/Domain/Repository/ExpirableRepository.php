<?php

namespace Tollwerk\TwAntibot\Domain\Repository;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Joschi Kuphal <joschi@tollwerk.de>, tollwerk GmbH
 *
 *  All rights reserved
 *
 *  This scremailt is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This scremailt is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the scremailt!
 ***************************************************************/

/**
 * Abstract expirable repository base
 */
abstract class ExpirableRepository extends AbstractRepository
{

    /**
     * Delete expired records
     *
     * @return \int                Deleted records
     */
    public function collectGarbage()
    {
        $count = 0;
        $query = $this->createQuery();
        $query->getQuerySettings()->setIgnoreEnableFields(true);
        $query->matching($query->logicalAnd(array(
            $query->greaterThan('endtime', 0),
            $query->lessThan('endtime', time()),
        )));
        foreach ($query->execute() as $record) {
            $this->remove($record);
            ++$count;
        }
        return $count;
    }
}
