<?php
if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

// Registering and including classes and namespaces from external libraries (chromephp etc.).
require_once (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY, 'Resources/Private/Libraries/autoload.php'));

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'tollwerk Anti-Spambot tools');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_twantibot_domain_model_ip', 'EXT:tw_antibot/Resources/Private/Language/locallang_csh_tx_twantibot_domain_model_ip.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_twantibot_domain_model_ip');
$GLOBALS['TCA']['tx_twantibot_domain_model_ip'] = array(
	'ctrl' => array(
		'title'	=> 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_ip',
		'label' => 'ip',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'dividers2tabs' => TRUE,

		'enablecolumns' => array(
			'disabled' => 'hidden',
			'endtime' => 'endtime',
		),
		'searchFields' => 'ip,',
		'dynamicConfigFile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Configuration/TCA/Ip.php',
		'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'Resources/Public/Icons/tx_twantibot_domain_model_ip.png'
	),
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_twantibot_domain_model_email', 'EXT:tw_antibot/Resources/Private/Language/locallang_csh_tx_twantibot_domain_model_email.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_twantibot_domain_model_email');
$GLOBALS['TCA']['tx_twantibot_domain_model_email'] = array(
	'ctrl' => array(
		'title'	=> 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_email',
		'label' => 'email',
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'dividers2tabs' => TRUE,

		'enablecolumns' => array(
			'disabled' => 'hidden',
			'endtime' => 'endtime',
		),
		'searchFields' => 'email,',
		'dynamicConfigFile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Configuration/TCA/Email.php',
		'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'Resources/Public/Icons/tx_twantibot_domain_model_email.png'
	),
);

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_twantibot_domain_model_submission', 'EXT:tw_antibot/Resources/Private/Language/locallang_csh_tx_twantibot_domain_model_submission.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_twantibot_domain_model_submission');
$GLOBALS['TCA']['tx_twantibot_domain_model_submission'] = array(
	'ctrl' => array(
		'title'	=> 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_submission',
		'label' => 'reason',
		'label_alt' => 'ip,crdate',
		'label_alt_force' => true,
		'tstamp' => 'tstamp',
		'crdate' => 'crdate',
		'cruser_id' => 'cruser_id',
		'dividers2tabs' => TRUE,

		'enablecolumns' => array(
		),
		'searchFields' => 'reason,ip',
		'dynamicConfigFile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY) . 'Configuration/TCA/Submission.php',
		'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath($_EXTKEY) . 'Resources/Public/Icons/tx_twantibot_domain_model_submission.png'
	),
);
