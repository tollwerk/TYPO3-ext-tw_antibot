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

use \Tollwerk\TwAntibot\Validation\Exception;

/**
 * Antibot validator
 *
 */
class Validator {
	/**
	 * Object manager
	 * 
	 * @var \TYPO3\CMS\Extbase\Object\ObjectManager
	 * @inject
	 */
	protected $_objectManager = null;
	/**
	 * IP repository
	 * 
	 * @var \Tollwerk\TwAntibot\Domain\Repository\IpRepository
	 */
	protected $_ipRepository = null;
	/**
	 * Email repository
	 * 
	 * @var \Tollwerk\TwAntibot\Domain\Repository\EmailRepository
	 */
	protected $_emailRepository = null;
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
	 * GET/POST data for this validator
	 * 
	 * @var \array
	 */
	protected $_data = null;
	/**
	 * Antibot token timestamp (used as a hint)
	 * 
	 * @var \int
	 */
	protected $_timestamp = null;
	/**
	 * TypoScript setup
	 * 
	 * @var \array
	 */
	protected $_setup = null;
	/**
	 * Ban reason
	 * 
	 * @var \int
	 */
	protected $_banned = 0;
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
	 * IP address is banned
	 * 
	 * @var \int
	 */
	const BANNED_IP = 1;
	
	/**
	 * Email address is banned
	 * 
	 * @var \int
	 */
	const BANNED_EMAIL = 2;
	
	/**
	 * Instanciate a validator
	 *
	 * @param \array $settings									Settings
	 * @param \array $fields									Optional: Field values to use for validation
	 * @return \Tollwerk\TwAntibot\Validation\Validator         Validator instance
	 */
	public static function &instance(array $settings, array $fields = null) {
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
	 * @param \array $fields									Optional: Field values to use for validation
	 * @return \boolean											Validation success
	 */
	public static function validate(array $settings, array $fields = null) {
	    return self::instance($settings, $fields)->_validate();
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
	        throw new Exception\InvalidSettingsException();
	    }
	    
        return  $settings['token'].'_'.\TYPO3\CMS\Core\Utility\GeneralUtility::shortMD5($GLOBALS['TSFE']->fe_user->id.serialize($settings));
	}
	
	/**
	 * Private constructor
	 *
	 * @param \array $settings									Settings
	 * @param \array $fields									Object field values
	 * @throws \Tollwerk\TwAntibot\Validation\Exception			If TYPO3 mode is not FE
	 */
	protected function __construct(array $settings, array $fields = null) {
		
		// Ensure that we're in FE mode
		if (TYPO3_MODE != 'FE') {
			throw new Exception('Antibot validation works for FE only');
		}
		
		$this->_settings		= $settings;
		$this->_fields			= $fields;
		$this->_objectManager	= \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Object\\ObjectManager');
		$this->_token           = self::_token($this->_settings);
		$this->_ip				= $_SERVER['REMOTE_ADDR'];
		$this->_whitelist		= \TYPO3\CMS\Core\Utility\GeneralUtility::trimExplode(',', $this->_settings['whitelist'], true);
		
		// If the current client is whitelisted: No further steps will be taken ...
		if (in_array($this->_ip, $this->_whitelist)) {
			$this->_log('IP is whitelisted');
			$this->_valid	= true;
			
		// Else: Prepare validation
		} else {
			
			// Check IP ban
			if ($this->_ipBanningEnabled()) {
				$this->_ipRepository			= $this->_objectManager->get('Tollwerk\\TwAntibot\\Domain\\Repository\\IpRepository');
				if ($this->_ipRepository->findOneByIp($this->_ip) instanceof \Tollwerk\TwAntibot\Domain\Model\Ip) {
					$this->_banned				= self::BANNED_IP;
				}
			}
		
			// If antibot data has been submitted
			$this->_data			= \TYPO3\CMS\Core\Utility\GeneralUtility::_GP($this->_token);
			if ($this->_data) {
				$this->_fields		= (array)$this->_fields;
				
				// Check email ban
				if ($this->_emailBanningEnabled()) {
					$emailField					= trim($this->_settings['email']);
					$emailAddress				= array_key_exists($emailField, $this->_fields) ? trim($this->_fields[$emailField]) : null;
					if (strlen($emailAddress)) {
						$this->_emailRepository	= $this->_objectManager->get('Tollwerk\\TwAntibot\\Domain\\Repository\\EmailRepository');
						if ($this->_emailRepository->findOneByEmail($emailAddress) instanceof \Tollwerk\TwAntibot\Domain\Model\Email) {
							$this->_banned		|= self::BANNED_EMAIL;
							return;
						}
					}
				}
			    
			    // If a HMAC has been submitted
			    if (is_array($this->_data) && !empty($this->_data['hmac'])) {
			    	$this->_log('Decrypting HMAC', $this->_data['hmac']);
			        
			    	// Check if a timestamp hint has been sent
			    	if (!empty($this->_data['ts'])) {
			    		$this->_timestamp		= intval($this->_data['ts']);
			    	}
			    	
			    	$this->_valid				= $this->_decryptHmac($this->_data['hmac']);
			    	
			    	$this->_log('HMAC valid:', $this->_valid);
			    	$this->_log('Submission delay:', $this->_delay);
			        
			    // Else: Error
			    } else {
			        throw new Exception\MissingTokenException();
			    }
			    
			// Else: Reset data
			} else {
				$this->_data		= false;
			}
		}
	}
	
	/**
	 * Main validation
	 * 
	 * @return \boolean				Validation success
	 */
	protected function _validate() {
		
		// Deny access in case the client is banned
		if ($this->_banned) {
			$this->_log(sprintf('Client is banned (%s)', $this->_banned));
			return false;
		}
		
		try {
			
			// Validate the presence and integrity of the antibot token
			$this->_validateAntibotToken();
			
			// Validate against the BotSmasher API
			$this->_validateBotSmasher();

			// Validate honeypots
			$this->_validateHoneypots();

			// Validity of the antibot token implies validity of the session token, submission time and
			// submission method order so there's non need for additional checks.

		// If an exception is thrown: Fail and potentially ban the request
		} catch (\Tollwerk\TwAntibot\Validation\Exception $e) {
			$reflect			= new \ReflectionClass($e);
			
			$this->_log('Submission blocked by', $reflect->getShortName());
			$submission			= $this->_logSubmission($e);
			
			$this->_ban($submission);
			return false;
		}
		
		return true;
	}
	
	/**
	 * Validate the presence of an antibot token
	 * 
	 * @throws \Tollwerk\TwAntibot\Validation\Exception\MissingAntibotTokenException		If no antibot token has been submitted
	 * @throws \Tollwerk\TwAntibot\Validation\Exception\InvalidTokenException				If the antibot token was invalid
	 */
	protected function _validateAntibotToken() {
		
		// Only validate if antibot data has been submitted
		if ($this->_data) {
		
			// If no antibot token has been submitted: Error
			if ($this->_valid === null) {
				throw new Exception\MissingTokenException();
			
			// Else: If the antibot token was invalid
			} elseif ($this->_valid === false) {
				throw new Exception\InvalidTokenException();
			}
			
			$this->_log('Passed antibot token checks ...');
			
		// Else: Skip
		} else {
			$this->_log('Skipped antibot token checks');
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
				$botSmasherEmailField		= trim($this->_settings['email']);
				$botSmasherEmail			= (!strlen($botSmasherEmailField) || empty($this->_fields[$botSmasherEmailField])) ? null : trim($this->_fields[$botSmasherEmailField]);
				$botSmasherClient			= new \Tollwerk\TwAntibot\Utility\BotSmasherClient($this->_settings['botsmasher']);
				$botSmasherStatus			= $botSmasherClient->check($this->_ip, $botSmasherEmail);
				
				// If the BotSmasher status isn't valid
				if ($botSmasherStatus !== \Tollwerk\TwAntibot\Utility\BotSmasherClient::STATUS_VALID) {
					throw new Exception\BotSmasherException(null, $botSmasherStatus);
				}
				
			// If an error occurs: Don't do anything about it
			} catch (\Tollwerk\TwAntibot\Utility\BotSmasher\Exception $e) {
// 				foreach ($e->getMessages() as $message) {
// 					$this->_log($message->message);
// 				}
			}
			
			$this->_log('Passed BotSmasher checks ...');
			
		// Else: Skip
		} else {
			$this->_log('Skipped BotSmasher checks');
		}
	}
	
	/**
	 * Validate honeypots
	 * 
	 * @throws \Tollwerk\TwAntibot\Validation\Exception\HoneypotException					If any of the registered honeypots was filled in
	 */
	protected function _validateHoneypots() {
		
		// If honeypot checks are enabled and data has been submitted
		if ($this->_honeypotEnabled() && is_array($this->_data)) {
			foreach ($this->_honeypotFields() as $honeypotField) {
				if (!empty($this->_data[$honeypotField])) {
					throw new Exception\HoneypotException($honeypotField);
				}
			}
			
			$this->_log('Passed honeypot checks ...');
			
		// Else: Skip
		} else {
			$this->_log('Skipped honeypot checks');
		}
	}
	
	/**
	 * Create and return the armor fields for this validator
	 * 
	 * @return \string                 Armor fields
	 */
	protected function _armor() {
		$now							= null;
	    
	    // Add the HMAC hidden field
	    $armor							= '<input type="hidden" name="'.htmlspecialchars($this->_token).'[hmac]" value="'.htmlspecialchars($this->_hmac($now)).'"/>';
	    
	    // Add the timestamp field
	    if ($now !== null) {
	    	$armor						.= '<input type="hidden" name="'.htmlspecialchars($this->_token).'[ts]" value="'.intval($now).'"/>';
	    }
	    
	    // Add the honeypot field
	    if ($this->_honeypotEnabled()) {
			$viewSettings       		= $this->_setup('view.');
			
			/* @var $standaloneView \TYPO3\CMS\Fluid\View\StandaloneView */
			$standaloneView             = $this->_objectManager->get('TYPO3\\CMS\\Fluid\\View\\StandaloneView');
			$standaloneView->setTemplateRootPaths($viewSettings['templateRootPaths.']);
			$standaloneView->setPartialRootPaths($viewSettings['partialRootPaths.']);
			$standaloneView->setLayoutRootPaths($viewSettings['layoutRootPaths.']);
			$standaloneView->setTemplate('Armor'.DIRECTORY_SEPARATOR.'Honeypot.html');
			$standaloneView->assign('honeypots', array_keys($this->_honeypotFields()));
	        $armor						.= $standaloneView->render();
	    }
	    
	    return $armor;
	}
	
	/**
	 * Return the TypoScript setup
	 * 
	 * @param \string $key				Optional: Key
	 * @return \array					TypoScript setup
	 */
	protected function _setup($key = null) {
		if ($this->_setup === null) {
			$setup						= \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Extbase\\Configuration\\BackendConfigurationManager')->getTypoScriptSetup();
			$this->_setup				= $setup['plugin.']['tx_twantibot.'];
		}
		
		return ($key === null) ? $this->_setup : (($key && array_key_exists($key, $this->_setup)) ? $this->_setup[$key] : null);
	}
	
	/**
	 * Create and return the submission HMAC
	 * 
	 * @param \int $now					Current timestamp
	 * @return \string					Submission HMAC
	 */
	protected function _hmac(&$now = null) {
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
	        	if ($this->_data) {
	            	$hmacParams[]	= true;
	        	}
	            $hmacParams[]		=
	            $now				= time();
	        }
        }
        
        $hmac						= \TYPO3\CMS\Core\Utility\GeneralUtility::hmac(serialize($hmacParams));
        
        $this->_log('---------------------------------');
        $this->_log('Creating HMAC for parameters', $hmacParams);
        $this->_log('HMAC:', $hmac);
        
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
				$this->_honeypotFields[$this->_token.'['.htmlspecialchars($honeypotField).']']	= $honeypotField;
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
	 * Check if IP address banning is enabled
	 *
	 * @return \boolean                IP banning is enabled
	 */
	protected function _ipBanningEnabled() {
	    return !empty($this->_settings['banning'])
	    && is_array($this->_settings['banning'])
	    && !empty($this->_settings['banning']['ip'])
	    && is_array($this->_settings['banning']['ip'])
	    && !empty($this->_settings['banning']['ip']['enable'])
	    && intval($this->_settings['banning']['ip']['enable'])
	    && !empty($this->_settings['banning']['ip']['period'])
	    && (intval($this->_settings['banning']['ip']['period']) >= 0)
	    && (TYPO3_MODE == 'FE');
	}

	/**
	 * Check if email address banning is enabled
	 *
	 * @return \boolean                Email address banning is enabled
	 */
	protected function _emailBanningEnabled() {
	    return !empty($this->_settings['banning'])
	    && is_array($this->_settings['banning'])
	    && !empty($this->_settings['banning']['email'])
	    && is_array($this->_settings['banning']['email'])
	    && !empty($this->_settings['banning']['email']['enable'])
	    && intval($this->_settings['banning']['email']['enable'])
	    && !empty($this->_settings['banning']['email']['period'])
	    && (intval($this->_settings['banning']['email']['period']) >= 0)
	    && !empty($this->_settings['email'])
	    && strlen(trim($this->_settings['email']))
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
                throw new Exception\InvalidRequestMethodOrderException(strtoupper($_SERVER['REQUEST_METHOD']));
            }
            
            $hmacParams[]        = $previousMethod;
        }
        
        // If submission time checks are enabled
        if ($this->_submissionTimeEnabled()) {
        	
            $minimum			= intval($this->_settings['time']['minimum']);
            $maximium			= intval($this->_settings['time']['maximum']);
            $first				= max($minimum, intval($this->_settings['time']['first']));
            $now				= time();
            $initial			= $now - $first;

            // If a timestamp hint has been submitted: Probe this first
            if ($this->_timestamp && (($this->_timestamp + $minimum) <= $now) && (($this->_timestamp + $maximium) >= $now) && $this->_log('Probing timestamp hint first') && (
            	$this->_probeTimedHMAC($hmac, $hmacParams, $this->_timestamp, $this->_timestamp > $initial) ||
            	(($this->_timestamp <= $initial) ? $this->_probeTimedHMAC($hmac, $hmacParams, $this->_timestamp, true) : false))) {
            		
            	$this->_delay	= $now - $this->_timestamp;
            	$decrypted		= true;
            	
            // Else (or if decryption failed for some reason: Probe the valid time range
            } else {
            
	            // Run through the valid seconds range
	            for ($time = $now - $minimum; $time >= $now - $maximium; --$time) {
	            	
	            	// Probe the current timestamp
	            	if ($this->_probeTimedHMAC($hmac, $hmacParams, $time, $time > $initial) || (($time <= $initial) && $this->_probeTimedHMAC($hmac, $hmacParams, $time, true))) {
	            		$this->_delay		= $now - $time;
	            		$decrypted			= true;
	            		break;
	            	}
	            }
            }
            
        // Else: Check for HMAC match
        } else {
        	$currentHMAC			= \TYPO3\CMS\Core\Utility\GeneralUtility::hmac(serialize($hmacParams));
            $decrypted              = $hmac == $currentHMAC;
            
            $this->_log('Probing HMAC with parameters', $hmacParams);
            $this->_log('Current HMAC:', $currentHMAC);
            if ($decrypted) {
            	$this->_log('SUCCESS!');
            }
        }
        
        // Register the initial HTTP method in case decryption was successfull
        if ($decrypted && $previousMethod) {
            $this->_method          = $previousMethod;
        }
        
        return $decrypted;
	}
	
	/**
	 * Probe a set of HMAC parameters with timestamp (for both initial or follow-up requests)
	 * 
	 * @param \string $hmac			HMAC
	 * @param \array $hmacParams	HMAC parameters
	 * @param \int $timestamp		Timestamp
	 * @param \boolean $followUp	Follow-up request
	 * @return \boolean				HMAC matches
	 */
	protected function _probeTimedHMAC($hmac, array $hmacParams, $timestamp, $followUp = false) {
		if ($followUp) {
			$hmacParams[]		= true;
		}
		$hmacParams[]			= $timestamp;
		$currentHMAC			= \TYPO3\CMS\Core\Utility\GeneralUtility::hmac(serialize($hmacParams));
		
		$this->_log('Probing HMAC with parameters', $hmacParams);
		$this->_log('Current HMAC:', $currentHMAC);
		
		return $currentHMAC == $hmac;
	}
	
	/**
	 * Log a blocked submission
	 *
	 * @param \Tollwerk\TwAntibot\Validation\Exception $e		Occured exception
	 * @return \Tollwerk\TwAntibot\Domain\Model\Submission		Submission
	 */
	protected function _logSubmission(\Tollwerk\TwAntibot\Validation\Exception $e) {
		$persistence				= $this->_setup('persistence.');
		
		// Readable reason
		switch (true) {
			case ($e instanceof Exception\MissingTokenException):
				$reason				= 'Missing token';
				break;
			case ($e instanceof Exception\InvalidTokenException):
				$reason				= 'Invalid token';
				break;
			case ($e instanceof Exception\InvalidSettingsException):
				$reason				= 'Invalid settings';
				break;
			case ($e instanceof Exception\InvalidRequestMethodOrderException):
				$reason				= sprintf('Invalid request method (%s)', $e->getMessage());
				break;
			case ($e instanceof Exception\HoneypotException):
				$reason				= sprintf('Honeypot alert (%s)', $e->getMessage());
				break;
			case ($e instanceof Exception\BotSmasherException):
				$badguy				= array();
				if ($e->ipMatch()) {
					$badguy[]		= 'IP';
				}
				if ($e->emailMatch()) {
					$badguy[]		= 'email';
				}
				if ($e->nameMatch()) {
					$badguy[]		= 'name';
				}
				$reason				= sprintf('BotSmasher badguy (%s)', implode(',', $badguy));
				break;
			default:
				$reason				= 'Unknown';
				break;
		}
		
		$submission					= new \Tollwerk\TwAntibot\Domain\Model\Submission();
		$submission->setReason($reason);
		$submission->setPid(intval($persistence['storagePid']));
		$submission->setIp($this->_ip);
		$submission->setSettings(json_encode($this->_settings));
		$submission->setData(json_encode($this->_data));
		$submission->setFields(json_encode($this->_fields));
		
		/* @var $submissionRepository \Tollwerk\TwAntibot\Domain\Repository\SubmissionRepository */
		$submissionRepository		= $this->_objectManager->get('Tollwerk\\TwAntibot\\Domain\\Repository\\SubmissionRepository');
		$submissionRepository->add($submission);
		
		return $submission;
	}
	
	/**
	 * Perform internal banning
	 * 
	 * @param \Tollwerk\TwAntibot\Domain\Model\Submission $submission		Submission
	 * @return void
	 */
	protected function _ban(\Tollwerk\TwAntibot\Domain\Model\Submission $submission = null) {
		$persistence				= $this->_setup('persistence.');
		
		// If IP banning is enabled
		if ($this->_ipBanningEnabled()) {
			$ip						= $this->_ipRepository->findExpiredOneByIp($this->_ip);
			
			if (!($ip instanceof \Tollwerk\TwAntibot\Domain\Model\Ip)) {
				$ip					= new \Tollwerk\TwAntibot\Domain\Model\Ip();
				$ip->setPid(intval($persistence['storagePid']));
				$ip->setIp($this->_ip);
				$ipUpdate			= false;
			} else {
				$ipUpdate			= true;
			}
			
			$ip->setSubmission($submission);
			
			// Set a period if configured
			$ipPeriod				= intval($this->_settings['banning']['ip']['period']);
			if ($ipPeriod > 0) {
				$ip->setEndtime(time() + $ipPeriod);
			}
			
			if ($ipUpdate) {
				$this->_ipRepository->update($ip);
			} else {
				$this->_ipRepository->add($ip);
			}
		}
		
		// If email banning is enabled
		if ($this->_emailBanningEnabled()) {
			$emailField					= trim($this->_settings['email']);
			$emailAddress				= array_key_exists($emailField, $this->_fields) ? trim($this->_fields[$emailField]) : null;
			if (strlen($emailAddress)) {
				$email					= $this->_emailRepository->findExpiredOneByEmail($emailAddress);
				
				if (!($email instanceof \Tollwerk\TwAntibot\Domain\Model\Email)) {
					$email				= new \Tollwerk\TwAntibot\Domain\Model\Email();
					$email->setPid(intval($persistence['storagePid']));
					$email->setEmail($emailAddress);
					$emailUpdate		= false;
				} else {
					$emailUpdate		= true;
				}
				
				$email->setSubmission($submission);
				
				
				// Set a period if configured
				$emailPeriod			= intval($this->_settings['banning']['email']['period']);
				if ($emailPeriod > 0) {
					$email->setEndtime(time() + $emailPeriod);
				}
				
				if ($emailUpdate) {
					$this->_emailRepository->update($email);
				} else {
					$this->_emailRepository->add($email);
				}
			}
		}
	}
	
	/**
	 * Log a message
	 * 
	 * @param \string $message			Message
	 * @return \boolean					Always TRUE
	 */
	protected function _log($message) {
		call_user_func_array(array('\ChromePhp', 'log'), func_get_args());
		return true;
	}
}