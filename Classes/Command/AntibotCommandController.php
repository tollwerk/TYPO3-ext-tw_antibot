<?php

namespace Tollwerk\TwAntibot\Command;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Joschi <joschi@tollwerk.de>, tollwerk GmbH
 *           Klaus Fiedler <klaus@tollwerk.de>, tollwerk GmbH
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

use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\CommandController;

/**
 * Facsimile import / export controller
 */
class AntibotCommandController extends CommandController {
	
	/**
	 * Configuration manager
	 * 
	 * @var \TYPO3\CMS\Extbase\Configuration\ConfigurationManagerInterface
	 * @inject
	 */
	protected $configurationManager = NULL;
	
	/**
	 * IP repository
	 * 
	 * @var \Tollwerk\TwAntibot\Domain\Repository\IpRepository
	 * @inject
	 */
	protected $ipRepository = null;
	
	/**
	 * Email repository
	 * 
	 * @var \Tollwerk\TwAntibot\Domain\Repository\EmailRepository
	 * @inject
	 */
	protected $emailRepository = null;
	
	/**
	 * Configuration
	 * 
	 * @var array
	 */
	protected $_config = null;
	
	/**
	 * Import configuration
	 * 
	 * @var array
	 */
	protected $_importConfig = null;
	
	/**
	 * Storage pid
	 * 
	 * @var \integer
	 */
	protected $_storagePid = 0;
	
	/**
	 * Temporary files
	 * 
	 * @var \array
	 */
	protected $_tmpFiles = array();
	
	/**
	 * Column mapping
	 * 
	 * @var \array
	 */
	protected $_mapping = null;
	
	/**
	 * Archive imported files
	 * 
	 * @var \boolean
	 */
	protected $_archive = true;
	
	/**
	 * Current timestamp
	 * 
	 * @var \integer
	 */
	protected $_now = null;
	
	/**
	 * Statistics
	 * 
	 * @var \array
	 */
	protected $_stats = array(
		'file' => '',
		'log' => '',
		'documents' => array('created' => 0, 'updated' => 0, 'deleted' => 0, 'indexed' => 0),
		'facsimiles' => array('created' => 0, 'updated' => 0, 'deleted' => 0),
	);
	
	/**
	 * Running scheduler task
	 * 
	 * @var \TYPO3\CMS\Extbase\Scheduler\Task
	 */
	protected $_task = null;
	
	/**
	 * Log file resource
	 * 
	 * @var \resource
	 */
	protected $_log = null;
	
	/************************************************************************************************
	 * PUBLIC METHODS
	 ***********************************************************************************************/
	
	/**
	 * Constructor
	 */
	public function __construct() {
		$this->objectManager			= GeneralUtility::makeInstance('TYPO3\\CMS\Extbase\\Object\\ObjectManager');
		$this->ipRepository				= $this->objectManager->get('Tollwerk\\TwAntibot\\Domain\\Repository\\IpRepository');
		$this->emailRepository			= $this->objectManager->get('Tollwerk\\TwAntibot\\Domain\\Repository\\EmailRepository');
	}
	
	/**
	 * Garbage collection
	 * 
	 * @return \boolean				Success
	 */
	public function gcCommand() {
	    if ($this->_task === null) {
    		foreach (debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT) as $frame) {
    			if ($frame['class'] == 'TYPO3\CMS\Extbase\Scheduler\Task') {
    				$this->_task	=& $frame['object'];
    				break;
    			}
    		}
	    }
		
	    $this->_outputStatistics(array(
	    	\TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate('gc.ip', 'tw_antibot')			=> $this->ipRepository->collectGarbage(),
	    	\TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate('gc.email', 'tw_antibot')			=> $this->emailRepository->collectGarbage(),
	    ));
	    
	    return true;
	}

	/**
	 * Output statistical information
	 *
	 * @param \array $statistics      Statistical data
	 * @return void
	 */
	protected function _outputStatistics(array $statistics) {
		$maxlength				= max(array_map('strlen', array_keys($statistics)));
		$plain					= '';
		$html					= '<table>';
		foreach ($statistics as $label => $value) {
			$plain				.= str_pad($label, $maxlength + 3, ' ', STR_PAD_RIGHT).$value.PHP_EOL;
			$html				.= '<tr><th style="padding-right:2em">'.htmlspecialchars($label).'</th><td>'.htmlspecialchars($value).'</td></tr>';
		}
		$html				.= '</table>';
			
		// Output statistics as task message
		if ($this->_task !== null) {
			$this->_addMessage($html, \TYPO3\CMS\Core\Messaging\FlashMessage::INFO);
	
		// ... or as CLI message
		} else {
			echo str_pad('', 76, '-').PHP_EOL.$plain.str_pad('', 76, '-').PHP_EOL;
		}
	}
	
	/**
	 * Add a flash message
	 *
	 * @param \string $message		Message
	 * @param \integer $severity 	Severity
	 * @return void
	 */
	protected function _addMessage($message, $severity = \TYPO3\CMS\Core\Messaging\FlashMessage::OK) {
		/** @var $flashMessage \TYPO3\CMS\Core\Messaging\FlashMessage */
		$flashMessage = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessage', $message, \TYPO3\CMS\Extbase\Utility\LocalizationUtility::translate('gc', 'tw_antibot'), $severity);
		/** @var $flashMessageService \TYPO3\CMS\Core\Messaging\FlashMessageService */
		$flashMessageService = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance('TYPO3\\CMS\\Core\\Messaging\\FlashMessageService');
		/** @var $defaultFlashMessageQueue \TYPO3\CMS\Core\Messaging\FlashMessageQueue */
		$defaultFlashMessageQueue = $flashMessageService->getMessageQueueByIdentifier();
		$defaultFlashMessageQueue->enqueue($flashMessage);
	}
}
