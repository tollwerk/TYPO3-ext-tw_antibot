<?php

namespace Tollwerk\TwAntibot\Domain\Model;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2017 Joschi Kuphal <joschi@tollwerk.de>, tollwerk GmbH
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
 * Blocked email address
 */
class Email extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {

	/**
	 * Email address
	 *
	 * @var string
	 * @validate NotEmpty
	 */
	protected $email = '';

	/**
	 * Associated blocked form submission
	 *
	 * @var \Tollwerk\TwAntibot\Domain\Model\Submission
	 */
	protected $submission = null;

	/**
	 * Endtime
	 *
	 * @var \int
	 */
	protected $endtime;

	/**
	 * Returns the email
	 *
	 * @return string $email
	 */
	public function getEmail() {
		return $this->email;
	}

	/**
	 * Sets the email
	 *
	 * @param string $email
	 * @return void
	 */
	public function setEmail($email) {
		$this->email = $email;
	}

	/**
	 * Returns the associated blocked form submission
	 *
	 * @return \Tollwerk\TwAntibot\Domain\Model\Submission $submission $submission		Associated blocked form submission
	 */
	public function getSubmission() {
		return $this->submission;
	}

	/**
	 * Sets the associated blocked form submission
	 *
	 * @param \Tollwerk\TwAntibot\Domain\Model\Submission $submission		Associated blocked form submission
	 */
	public function setSubmission($submission = null) {
		$this->submission = $submission;
	}

	/**
	 * Returns the endtime
	 *
	 * @return string $endtime
	 */
	public function getEndtime() {
		return $this->endtime;
	}

	/**
	 * Sets the endtime
	 *
	 * @param string $endtime
	 * @return void
	 */
	public function setEndtime($endtime) {
		$this->endtime = $endtime;
	}
}
