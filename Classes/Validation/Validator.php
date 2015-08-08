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
	 * Antibot token
	 * 
	 * @var \string
	 */
	protected $_token = null;
	/**
	 * Previous request method
	 * 
	 * @var \string
	 */
	protected $_method = null;
	/**
	 * Last submission delay
	 * 
	 * @var \int
	 */
	protected $_delay = null;
	/**
	 * Validity
	 * 
	 * @var \boolean
	 */
	protected $_valid = null;
	/**
	 * Initial validation
	 * 
	 * @var \boolean
	 */
	protected $_initial = true;
	/**
	 * Client IP address
	 * 
	 * @var \string
	 */
	protected $_ip = null;
	/**
	 * IP whitelist
	 * 
	 * @var \array
	 */
	protected $_whitelist = null;
	/**
	 * Honeypot fields
	 * 
	 * @var \array
	 */
	protected $_honeypotFields = null;
	/**
	 * Validator instances
	 * 
	 * @var \array
	 */
	protected static $_instances = array();
	
	/**
	 * Block access
	 * 
	 * @var \string
	 */
	const BLOCK = 'BLOCK';
	
	/**
	 * Instanciate a validator
	 *
	 * @param \array $settings									Settings
	 * @param \array $fields									Object field values
	 * @return \Tollwerk\TwAntibot\Validation\Validator         Validator instance
	 */
	public static function &instance(array $settings, array $fields = array()) {
        $token                          = self::_token($settings);
        if (!array_key_exists($token, self::$_instances)) {
            self::$_instances[$token]   = new self($settings, $fields);
        }
	    
        return self::$_instances[$token];
	}
	
	/**
	 * Validate a request
	 *
	 * @param \array $settings									Settings
	 * @param \array $fields									Object field values
	 * @return void;
	 */
	public static function validate(array $settings, array $fields = array()) {
	    self::instance($settings, $fields)->_validate();
	}
	
	/**
	 * Create the armor fields for a form
	 *
	 * @param \array $settings									Settings
	 * @return \string                                          Armor fields
	 */
	public static function armor(array $settings) {
		return self::instance($settings, array())->_armor();
	}
	
	/************************************************************************************************
	 * PRIVATE METHODS
	 ***********************************************************************************************/
	
	/**
	 * Return the antibot token
	 * 
	 * @param array $settings
	 * @throws \Tollwerk\TwAntibot\Validation\Exception\InvalidSettingsException
	 * @return void
	 */
	protected static function _token(array $settings) {
	    
	    // Get the antibot token name
	    if (empty($settings['token'])) {
	        throw new \Tollwerk\TwAntibot\Validation\Exception\InvalidSettingsException();
	    }
	    
        return  $settings['token'].'_'.\TYPO3\CMS\Core\Utility\GeneralUtility::shortMD5($GLOBALS['TSFE']->fe_user->id.serialize($settings));
	}
	
	/**
	 * Private constructor
	 *
	 * @param \array $settings									Settings
	 * @param \array $fields									Object field values
	 
	 */
	protected function __construct(array $settings, array $fields) {
		$this->_settings		= $settings;
		$this->_fields			= $fields;
		$this->_token           = self::_token($this->_settings);
		$this->_ip				= $_SERVER['REMOTE_ADDR'];
		$this->_whitelist		= \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->_settings['whitelist'], true);
		
		// If antibot data has been submitted
		$data                   = \TYPO3\CMS\Core\Utility\GeneralUtility::_GP($this->_token);
		if ($data) {
		    $this->_initial     = false;
		    
		    // If the current client is whitelisted
		    if (in_array($this->_ip, $this->_whitelist)) {
		    	$this->_valid	= true;
		    	
		    	\ChromePhp::log('IP is whitelisted');
		    
		    // Else ff an array has been submitted
		    } elseif (is_array($data) && !empty($data['hmac'])) {
		    	
		    	\ChromePhp::log('Decrypting HMAC', $data['hmac']);
		        
		    	$this->_valid   = $this->_decryptHmac($data['hmac']);
		    	
		    	\ChromePhp::log('HMAC valid:', $this->_valid);
		    	\ChromePhp::log('Submission delay:', $this->_delay);
		        
		    // Else: Error
		    } else {
		        throw new \Tollwerk\TwAntibot\Validation\Exception\InvalidTokenException();
		    }
		}
	}
	
	/**
	 * Main validation
	 * 
	 * @throws \Tollwerk\TwAntibot\Validation\Exception										If a validation error occurs
	 */
	protected function _validate() {
		try {
			
			// Validate the presence and integrity of the antibot token
			$this->_validateAntibotToken();
			
			// Validate against the BotSmasher API
			$this->_validateBotSmasher();

			// Validate honeypots
			$this->_validateHoneypots();

			// Validity of the antibot token implies validity of the session token, submission time and
			// submission method order so there's non need for additional checks.
			
		} catch (\Tollwerk\TwAntibot\Validation\Exception $e) {
			\ChromePhp::log('Blocked by exception:', get_class($e));
		}
	}
	
	/**
	 * Validate the presence of an antibot token
	 * 
	 * @throws \Tollwerk\TwAntibot\Validation\Exception\MissingAntibotTokenException		If no antibot token has been submitted
	 * @throws \Tollwerk\TwAntibot\Validation\Exception\InvalidTokenException				If the antibot token was invalid
	 */
	protected function _validateAntibotToken() {
		
		// If no antibot token has been submitted: Error
		if ($this->_valid === null) {
			throw new \Tollwerk\TwAntibot\Validation\Exception\MissingAntibotTokenException();
		
			// Else: If the antibot token was invalid
		} elseif ($this->_valid === false) {
			throw new \Tollwerk\TwAntibot\Validation\Exception\InvalidTokenException();
		}
	}
	
	/**
	 * Validate against the BotSmasher API
	 * 
	 * @throws \Tollwerk\TwAntibot\Validation\Exception\BotSmasherException					If BotSmasher returned a positive result
	 */
	protected function _validateBotSmasher() {
		
		// If BotSmasher checks are enabled
		if ($this->_botSmasherEnabled()) {
			try {
				$botSmasherEmailField		= trim($this->_settings['botsmasher']['field']);
				$botSmasherEmail			= (!strlen($botSmasherEmailField) || empty($this->_fields[$botSmasherEmailField])) ? null : trim($this->_fields[$botSmasherEmailField]);
				$botSmasherClient			= new \Tollwerk\TwAntibot\Utility\BotSmasherClient($this->_settings['botsmasher']);
				$botSmasherStatus			= $botSmasherClient->check($this->_ip, $botSmasherEmail);

				// If the BotSmasher status isn't valid
				if ($botSmasherStatus !== \Tollwerk\TwAntibot\Utility\BotSmasherClient::STATUS_VALID) {
					throw new \Tollwerk\TwAntibot\Validation\Exception\BotSmasherException(null, $botSmasherStatus);
				}
				
			} catch (\Tollwerk\TwAntibot\Utility\BotSmasher\Exception $e) {
				foreach ($e->getMessages() as $message) {
					\ChromePhp::log($message->message);
				}
			}
		}
	}
	
	/**
	 * Validate honeypots
	 */
	protected function _validateHoneypots() {
		
		// If honeypot checks are enabled
		if ($this->_honeypotEnabled()) {
			
		}
	}
	
	/**
	 * Create and return the armor fields for this validator
	 * 
	 * @return \string                 Armor fields
	 */
	protected function _armor() {
	    
	    // Add the HMAC hidden field
	    $armor                         = '<input type="hidden" name="'.htmlspecialchars($this->_token).'[hmac]" value="'.htmlspecialchars($this->_hmac()).'"/>';
	    
	    // Add the honeypot field
	    if ($this->_honeypotEnabled()) {
	        $objectManager		        = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\CMS\Extbase\Object\ObjectManager');
			$setup						= \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Configuration\\BackendConfigurationManager')->getTypoScriptSetup();
			$typoscriptService			= \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Service\\TypoScriptService');
			$viewSettings       		= $setup['plugin.']['tx_twantibot.']['view.'];
			
			/* @var $standaloneView \TYPO3\CMS\Fluid\View\StandaloneView */
			$standaloneView             = $objectManager->get('TYPO3\\CMS\\Fluid\\View\\StandaloneView');
			$standaloneView->setTemplateRootPaths($viewSettings['templateRootPaths.']);
			$standaloneView->setPartialRootPaths($viewSettings['partialRootPaths.']);
			$standaloneView->setLayoutRootPaths($viewSettings['layoutRootPaths.']);
			$standaloneView->setTemplate('Armor'.DIRECTORY_SEPARATOR.'Honeypot.html');
			$standaloneView->assign('honeypots', $this->_honeypotFields());
	        $armor						.= $standaloneView->render();
	    }
	    
	    return $armor;
	}
	
	/**
	 * Create and return the submission HMAC
	 * 
	 * @return \string                 Submission HMAC
	 */
	protected function _hmac() {
        $hmacParams					= array($this->_token);

        // If session token checks are enabled
        if ($this->_sessionTokenEnabled()) {
           $hmacParams[]			= $GLOBALS['TSFE']->fe_user->id;
        }
        
        // If there is an invalid current HMAC
        if ($this->_valid === false) {
        	$hmacParams[]			= self::BLOCK;
        	
        // Else
        } else {

	        // If submission time checks are enabled
	        if ($this->_submissionMethodOrderEnabled()) {
	           $hmacParams[]        = $this->_method ?: strtoupper($_SERVER['REQUEST_METHOD']);
	        }
	        
	        // If submission time checks are enabled
	        if ($this->_submissionTimeEnabled()) {
	        	if (!$this->_initial) {
	            	$hmacParams[]	= true;
	        	}
	            $hmacParams[]		= time();
	        }
        }
        
        $hmac						= \TYPO3\CMS\Core\Utility\GeneralUtility::hmac(serialize($hmacParams));
        
        \ChromePhp::log('---------------------------------');
        \ChromePhp::log('Creating HMAC for parameters', $hmacParams);
        \ChromePhp::log('HMAC:', $hmac);
        
        return $hmac;
	}
	
	/**
	 * Build and return the honeypot field names
	 * 
	 * @return \array					Honeypot field names
	 */
	protected function _honeypotFields() {
		if ($this->_honeypotFields === null) {
			$this->_honeypotFields			= array();
			foreach (array_diff(\TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->_settings['honeypot']['fields'], true), array('hmac')) as $honeypotField) {
				$this->_honeypotFields[]	= $this->_token.'['.htmlspecialchars($honeypotField).']';
			}
		}
		return $this->_honeypotFields;
	}
	
	/**
	 * Check if session token checks are enabled
	 * 
	 * @return \boolean                Session token checks enabled
	 */
	protected function _sessionTokenEnabled() {
	    return !empty($this->_settings['session'])
	       && is_array($this->_settings['session'])
	       && !empty($this->_settings['session']['enable'])
	       && intval($this->_settings['session']['enable'])
	       && (TYPO3_MODE == 'FE');
	}
	
	/**
	 * Check if submission time checks are enabled
	 * 
	 * @return \boolean                Submission time checks enabled
	 */
	protected function _submissionTimeEnabled() {
	    return !empty($this->_settings['time'])
	       && is_array($this->_settings['time'])
	       && !empty($this->_settings['time']['enable'])
	       && intval($this->_settings['time']['enable'])
	       && !empty($this->_settings['time']['minimum'])
	       && intval($this->_settings['time']['minimum'])
	       && !empty($this->_settings['time']['maximum'])
	       && intval($this->_settings['time']['maximum'])
	       && (intval($this->_settings['time']['maximum']) > intval($this->_settings['time']['minimum']))
	       && (TYPO3_MODE == 'FE');
	}

	/**
	 * Check if submission method order checks are enabled
	 *
	 * @return \boolean                Submission method order checks enabled
	 */
	protected function _submissionMethodOrderEnabled() {
	    return !empty($this->_settings['order'])
	    && is_array($this->_settings['order'])
	    && !empty($this->_settings['order']['enable'])
	    && intval($this->_settings['order']['enable'])
	    && !empty($this->_settings['order']['method'])
	    && strlen(trim($this->_settings['order']['method']))
	    && (TYPO3_MODE == 'FE');
	}

	/**
	 * Check if BotSmasher checks are enabled
	 *
	 * @return \boolean                BotSmasher checks enabled
	 */
	protected function _botSmasherEnabled() {
	    return !empty($this->_settings['botsmasher'])
	    && is_array($this->_settings['botsmasher'])
	    && !empty($this->_settings['botsmasher']['enable'])
	    && intval($this->_settings['botsmasher']['enable'])
	    && !empty($this->_settings['botsmasher']['apiKey'])
	    && strlen(trim($this->_settings['botsmasher']['apiKey']))
	    && !empty($this->_settings['botsmasher']['apiUrl'])
	    && strlen(trim($this->_settings['botsmasher']['apiUrl']))
	    && (TYPO3_MODE == 'FE');
	}

	/**
	 * Check if honeypot checks are enabled
	 *
	 * @return \boolean                Honeypot checks enabled
	 */
	protected function _honeypotEnabled() {
	    return !empty($this->_settings['honeypot'])
	    && is_array($this->_settings['honeypot'])
	    && !empty($this->_settings['honeypot']['enable'])
	    && intval($this->_settings['honeypot']['enable'])
	    && !empty($this->_settings['honeypot']['fields'])
	    && strlen(trim($this->_settings['honeypot']['fields']))
	    && count(array_diff(\TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->_settings['honeypot']['fields'], true), array('hmac')))
	    && (TYPO3_MODE == 'FE');
	}
	
	/**
	 * Decrypt the submitted HMAC
	 * 
	 * In fact the HMAC cannot be decrypted, but it can be validated against the expected values. 
	 * 
	 * @param \string $hmac            HMAC
	 * @return \boolean                HMAC validity
	 */
	public function _decryptHmac($hmac) {
	    $decrypted              = false;
	    $previousMethod         = null;
	    $hmacParams             = array($this->_token);
	    
	    // If session token checks are enabled
        if ($this->_sessionTokenEnabled()) {
           $hmacParams[]        = $GLOBALS['TSFE']->fe_user->id;
        }
        
        // Short-circuit blocked HMAC
        $hmacBlock				= $hmacParams;
        $hmacBlock[]			= self::BLOCK;
        if (\TYPO3\CMS\Core\Utility\GeneralUtility::hmac(serialize($hmacBlock)) == $hmac) {
        	return false;
        }

        // If submission time checks are enabled
        if ($this->_submissionMethodOrderEnabled()) {
            list($previousMethod, $currentMethod)       = \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode('-', $this->_settings['order']['method'], true);
            
            // If the current request method doesn't match
            if ($currentMethod != strtoupper($_SERVER['REQUEST_METHOD'])) {
                throw new \Tollwerk\TwAntibot\Validation\Exception\InvalidRequestMethodOrderException();
            }
            
            $hmacParams[]        = $previousMethod;
        }
        
        // If submission time checks are enabled
        if ($this->_submissionTimeEnabled()) {
            $minimum             = intval($this->_settings['time']['minimum']);
            $maximium            = intval($this->_settings['time']['maximum']);
            $first               = max($minimum, intval($this->_settings['time']['first']));
            
            // Run through the valid seconds range
            for ($now = time(), $time = $now - $minimum, $initial = $now - $first; $time >= $now - $maximium; --$time) {
                
            	// Compose the HMAC parameters
            	$hmacParamsTime     	= $hmacParams;
                if ($time > $initial) {
                	$hmacParamsTime[]   = true;
                }
                $hmacParamsTime[]		= $time;
                $currentHMAC			= \TYPO3\CMS\Core\Utility\GeneralUtility::hmac(serialize($hmacParamsTime));
                
                \ChromePhp::log('Probing HMAC with parameters', $hmacParamsTime);
                \ChromePhp::log('Current HMAC:', $currentHMAC);
                
                if ($currentHMAC == $hmac) {
                    $this->_delay		= $now - $time;
                    $decrypted			= true;
                    break;
                }
                
                // Additional check for late follow-up submissions
                if ($time <= $initial) {
                	$hmacParamsTime     = $hmacParams;
                	$hmacParamsTime[]   = true;
                	$hmacParamsTime[]	= $time;
                	$currentHMAC		= \TYPO3\CMS\Core\Utility\GeneralUtility::hmac(serialize($hmacParamsTime));
                	
                	\ChromePhp::log('Probing HMAC with parameters', $hmacParamsTime);
                	\ChromePhp::log('Current HMAC:', $currentHMAC);
                	
                	if ($currentHMAC == $hmac) {
                		$this->_delay	= $now - $time;
                		$decrypted		= true;
                		break;
                	}
                }
            }
            
        // Else: Check for HMAC match
        } else {
        	$currentHMAC			= \TYPO3\CMS\Core\Utility\GeneralUtility::hmac(serialize($hmacParams));
            $decrypted              = $hmac == $currentHMAC;
            
            \ChromePhp::log('Probing HMAC with parameters', $hmacParams);
            \ChromePhp::log('Current HMAC:', $currentHMAC);
            if ($decrypted) {
            	\ChromePhp::log('SUCCESS!');
            }
        }
        
        // Register the initial HTTP method in case decryption was successfull
        if ($decrypted && $previousMethod) {
            $this->_method          = $previousMethod;
        }
        
        return $decrypted;
	}
}