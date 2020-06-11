<?php

defined('TYPO3_MODE') || die('Access denied.');

call_user_func(
    function() {
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addStaticFile(
            'tw_antibot',
            'Configuration/TypoScript/Static',
            'tollwerk Antibot'
        );

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr(
            'tx_twantibot_domain_model_blacklist',
            'EXT:tw_antibot/Resources/Private/Language/locallang_csh_tx_twantibot_domain_model_blacklist.xlf'
        );
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_twantibot_domain_model_blacklist');

        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addLLrefForTCAdescr(
            'tx_twantibot_domain_model_whitelist',
            'EXT:tw_antibot/Resources/Private/Language/locallang_csh_tx_twantibot_domain_model_whitelist.xlf'
        );
        \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::allowTableOnStandardPages('tx_twantibot_domain_model_whitelist');
    }
);
