<?php

namespace Tollwerk\TwAntibot\ViewHelpers;

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
 * Abstract antibot form access viewhelper base
 * 
 * @package Tollwerk\TwAntibot\ViewHelpers
 */
abstract class AccessViewHelper extends \TYPO3\CMS\Fluid\Core\ViewHelper\AbstractViewHelper {
	/**
	 * Basic settings
	 * 
	 * @var \array
	 */
	protected static $_settings = null;
	/**
	 * Extended settings
	 * 
	 * @var \array
	 */
	protected $_extendedSettings = null;
	
	/************************************************************************************************
	 * PUBLIC METHODS
	 ***********************************************************************************************/
	
	/**
	 * Initializes the view helper before invoking the render method.
	 *
	 * Override this method to solve tasks before the view helper content is rendered.
	 *
	 * @return void
	 * @api
	 */
	public function initialize() {
		$this->_extendedSettings		= self::_settings();
		$templateVariableContainer		= $this->renderingContext->getTemplateVariableContainer();
		$settings						= $templateVariableContainer->get('settings');
		if (is_array($settings) && !empty($settings['antibot']) && is_array($settings['antibot'])) {
			\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($this->_extendedSettings, $settings['antibot']);
		}
	}
	
	/************************************************************************************************
	 * PRIVATE METHODS
	 ***********************************************************************************************/
	
	/**
	 * Initialize and return the base settings
	 * 
	 * @return \array				Base settings
	 */
	protected static function _settings() {
		if (self::$_settings === null) {
			$objectManager				= \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Object\ObjectManager');
			$setup						= \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Configuration\\BackendConfigurationManager')->getTypoScriptSetup();
			$typoscriptService			= \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Service\\TypoScriptService');
			self::$_settings			= $typoscriptService->convertTypoScriptArrayToPlainArray($setup['plugin.']['tx_twantibot.']['settings.']);
		}
		
		return self::$_settings;
	}
	
	/**
	 * Validate the current user
	 * 
	 * @param \string $object		Form object name
	 * @return \boolean				Successful validation
	 */
	protected function _validate($object = null) {
		$templateVariableContainer		= $this->renderingContext->getTemplateVariableContainer();
		$request						= $this->controllerContext->getRequest();
		$request						= $request->getOriginalRequest() ?: $request;
		$arguments						= $request->getArguments();
		
		// If the form has just been submitted
		if ($object && array_key_exists($object, $arguments)) {
			try {
				\Tollwerk\TwAntibot\Validation\Validator::validate($this->_extendedSettings, $arguments[$object]);
			} catch (\Tollwerk\TwAntibot\Validation\Exception $e) {
				die(get_class($e));
			}
		}
		
		return true;		
	}
}
