<?php

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function() {
        ExtensionManagementUtility::addStaticFile('tw_antibot', 'Configuration/TypoScript',
            'tollwerk Antibot');

        ExtensionManagementUtility::addLLrefForTCAdescr('tx_twantibot_domain_model_blacklist',
            'EXT:tw_antibot/Resources/Private/Language/locallang_csh_tx_twantibot_domain_model_blacklist.xlf');
        ExtensionManagementUtility::allowTableOnStandardPages('tx_twantibot_domain_model_blacklist');

        ExtensionManagementUtility::addLLrefForTCAdescr('tx_twantibot_domain_model_whitelist',
            'EXT:tw_antibot/Resources/Private/Language/locallang_csh_tx_twantibot_domain_model_whitelist.xlf');
        ExtensionManagementUtility::allowTableOnStandardPages('tx_twantibot_domain_model_whitelist');
    }
);
