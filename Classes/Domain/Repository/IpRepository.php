<?php

namespace Tollwerk\TwAntibot\Domain\Repository;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2015 Joschi Kuphal <joschi@tollwerk.de>, tollwerk GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * IP repository
 */
class IpRepository extends \TYPO3\CMS\Extbase\Persistence\Repository {

	/**
	 * Disable storage PID treatment
	 */
	public function initializeObject() {

		/** @var $querySettings \TYPO3\CMS\Extbase\Persistence\Generic\Typo3QuerySettings */
		$querySettings = $this->objectManager->get('TYPO3\\CMS\\Extbase\\Persistence\\Generic\\Typo3QuerySettings');
		$querySettings->setRespectStoragePage(FALSE);
		$this->setDefaultQuerySettings($querySettings);
	}
	
	/**
	 * Find an even expired IP record
	 * 
	 * @param \string $ip								IP address
	 * @return \Tollwerk\TwAntibot\Domain\Model\Ip		IP address
	 */
	public function findExpiredOneByIp($ip) {
		$query		= $this->createQuery();
		$query->getQuerySettings()->setIgnoreEnableFields(true);
		return $query->matching($query->equals('ip', $ip))->execute()->getFirst();
	}
}