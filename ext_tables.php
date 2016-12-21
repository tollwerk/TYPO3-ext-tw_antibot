<?php

if (!defined('TYPO3_MODE')) {
	die('Access denied.');
}

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile($_EXTKEY, 'Configuration/TypoScript', 'tollwerk Anti-Spambot tools');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_twantibot_domain_model_ip', 'EXT:tw_antibot/Resources/Private/Language/locallang_csh_tx_twantibot_domain_model_ip.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_twantibot_domain_model_ip');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_twantibot_domain_model_email', 'EXT:tw_antibot/Resources/Private/Language/locallang_csh_tx_twantibot_domain_model_email.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_twantibot_domain_model_email');

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr('tx_twantibot_domain_model_submission', 'EXT:tw_antibot/Resources/Private/Language/locallang_csh_tx_twantibot_domain_model_submission.xlf');
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_twantibot_domain_model_submission');
