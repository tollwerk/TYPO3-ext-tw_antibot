<?php

namespace Tollwerk\TwAntibot\Formhandler;

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

if (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::isLoaded('formhandler')) {

	// Build the interceptor adapter
	if (class_exists('\\Typoheads\\Formhandler\\Interceptor\\AbstractInterceptor')) {
		abstract class InterceptorAdapter extends \Typoheads\Formhandler\Interceptor\AbstractInterceptor {}
	} else {
		abstract class InterceptorAdapter extends \Tx_Formhandler_AbstractInterceptor {}
	}

	/**
	 * Formhandler init interceptor
	 */
	class Interceptor extends InterceptorAdapter {
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

		/**
		 * Initialize the class variables
		 *
		 * @param array $gp GET and POST variable array
		 * @param array $settings Typoscript configuration for the component (component.1.config.*)
		 *
		 * @return void
		 */
		public function init($gp, $settings) {
			parent::init($gp, $settings);

			// Prepare the antibot settings
			$this->_extendedSettings		= \Tollwerk\TwAntibot\Utility\Utility::settings();
			if (array_key_exists('antibot.', $this->settings) && is_array($this->settings['antibot.'])) {
				$typoscriptService			= \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Service\\TypoScriptService');
				$antibotSettings			= $typoscriptService->convertTypoScriptArrayToPlainArray($this->settings['antibot.']);
				\TYPO3\CMS\Core\Utility\ArrayUtility::mergeRecursiveWithOverrule($this->_extendedSettings, $antibotSettings);
			}
		}
		/**
		 * The main method called by the controller
		 *
		 * @return \array			The current GET/POST parameters
		 */
		public function process() {
			$submitted				= !empty($this->gp['submitted']) && intval($this->gp['submitted']);
			$arguments				= $submitted ? array_diff_key($this->gp, array_flip(array('submitted', 'randomID', 'removeFile', 'removeFileField', 'submitField'))) : null;
			$valid					= \Tollwerk\TwAntibot\Validation\Validator::validate($this->_extendedSettings, $arguments);

			// If the request is not valid
			if (!$valid) {
				$this->log(true);

				//set view
				$viewClass			= 'Tx_Formhandler_View_AntiSpam';
				if ($this->settings['view']) {
					$viewClass		= $this->utilityFuncs->getSingle($this->settings, 'view');
				}

				$viewClass			= $this->utilityFuncs->prepareClassName($viewClass);

				/* @var $view \Tx_Formhandler_View_AntiSpam */
				$view				= $this->componentManager->getComponent($viewClass);
				$view->setLangFiles($this->globals->getLangFiles());
				$view->setPredefined($this->predefined);

				$templateCode		= $this->globals->getTemplateCode();
				if ($this->settings['templateFile']) {
					$templateCode	= $this->utilityFuncs->readTemplateFile(FALSE, $this->settings);
				}
				$view->setTemplate($templateCode, 'ANTIBOT');
				if (!$view->hasTemplate()) {
					$this->utilityFuncs->throwException('spam_detected');
					return 'Lousy spammer!';
				}

				$content			= $view->render($this->gp, array());
				$this->globals->getSession()->reset();
				return $content;
			}

			return $this->gp;
		}
	}
} else {
	/**
	 * Formhandler init interceptor stub
	 */
	class Interceptor {
		/**
		 * The main method called by the controller
		 *
		 * @return \array			The current GET/POST parameters
		 */
		public function process() {
			return $this->gp;
		}
	}
}
