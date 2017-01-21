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
 * Blocked form submission
 */
class Submission extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity {

	/**
	 * Block reason
	 *
	 * @var string
	 * @validate NotEmpty
	 */
	protected $reason = '';

	/**
	 * IP4 address
	 *
	 * @var string
	 * @validate NotEmpty
	 */
	protected $ip = '';

	/**
	 * Settings
	 *
	 * @var string
	 * @validate NotEmpty
	 */
	protected $settings = '';

	/**
	 * Submission data
	 *
	 * @var string
	 * @validate NotEmpty
	 */
	protected $data = '';

	/**
	 * Submission fields
	 *
	 * @var string
	 * @validate NotEmpty
	 */
	protected $fields = '';

	/**
	 * Returns the ip
	 *
	 * @return string $ip
	 */
	public function getIp() {
		return $this->ip;
	}

	/**
	 * Sets the ip
	 *
	 * @param string $ip
	 * @return void
	 */
	public function setIp($ip) {
		$this->ip = $ip;
	}

	/**
	 * Returns the reason
	 *
	 * @return string $reason
	 */
	public function getReason() {
		return $this->reason;
	}

	/**
	 * Sets the reason
	 *
	 * @param string $reason
	 * @return void
	 */
	public function setReason($reason) {
		$this->reason = $reason;
	}

	/**
	 * Returns the settings
	 *
	 * @return string $settings
	 */
	public function getSettings() {
		return $this->settings;
	}

	/**
	 * Sets the settings
	 *
	 * @param string $settings
	 * @return void
	 */
	public function setSettings($settings) {
		$this->settings = $settings;
	}

	/**
	 * Returns the data
	 *
	 * @return string $data
	 */
	public function getData() {
		return $this->data;
	}

	/**
	 * Sets the data
	 *
	 * @param string $data
	 * @return void
	 */
	public function setData($data) {
		$this->data = $data;
	}

	/**
	 * Returns the fields
	 *
	 * @return string $fields
	 */
	public function getFields() {
		return $this->fields;
	}

	/**
	 * Sets the fields
	 *
	 * @param string $fields
	 * @return void
	 */
	public function setFields($fields) {
		$this->fields = $fields;
	}
}
