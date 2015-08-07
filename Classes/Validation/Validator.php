<?php

namespace Tollwerk\TwAntibot\Validation;

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
 * Antibot validator
 *
 */
class Validator {
	/**
	 * Settings
	 * 
	 * @var \array
	 */
	protected $_settings = null;
	/**
	 * Fields
	 * 
	 * @var \array
	 */
	protected $_fields = null;
	/**
	 * Submission HMAC
	 * 
	 * @var \string
	 */
	protected $_hmac = null;
	
	/**
	 * Validate a request
	 *
	 * @param \array $settings									Settings
	 * @param \array $fields									Object field values
	 * @return void;
	 */
	public static function validate(array $settings, array $fields = array()) {
		$validator				= new self($settings, $fields);
		$validator->_validate();
	}
	
	/************************************************************************************************
	 * PRIVATE METHODS
	 ***********************************************************************************************/
	
	/**
	 * Private constructor
	 *
	 * @param \array $settings									Settings
	 * @param \array $fields									Object field values
	 */
	protected function __construct(array $settings, array $fields) {
		$this->_settings		= $settings;
		$this->_fields			= $fields;
	}
	
	/**
	 * Main validation
	 * 
	 * @throws \Tollwerk\TwAntibot\Validation\Exception			If a validation error occurs
	 */
	protected function _validate() {
		$this->_validateAntibotToken();	
	}
	
	/**
	 * Validate the presence of an antibot token
	 * 
	 * @throws \Tollwerk\TwAntibot\Validation\Exception\InvalidSettingsException
	 */
	protected function _validateAntibotToken() {
		
		// Get the antibot token name
		if (empty($this->_settings['token'])) {
			throw new \Tollwerk\TwAntibot\Validation\Exception\InvalidSettingsException();
		}
		
		// Get the antibot token
		$this->_hmac			= \TYPO3\CMS\Core\Utility\GeneralUtility::_GP($this->_settings['antibot']);
		
		// If the token is missing
		if (empty($this->_hmac)) {
			throw new \Tollwerk\TwAntibot\Validation\Exception\MissingAntibotTokenException();
		}
	}
}