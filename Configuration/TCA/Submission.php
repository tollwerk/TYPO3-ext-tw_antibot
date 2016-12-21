<?php
if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

$GLOBALS['TCA']['tx_twantibot_domain_model_submission'] = array(
    'ctrl' => array(
        'title' => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_submission',
        'label' => 'reason',
        'label_alt' => 'ip,crdate',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'cruser_id' => 'cruser_id',
        'dividers2tabs' => true,

        'enablecolumns' => array(),
        'searchFields' => 'reason,ip',
        'dynamicConfigFile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath('tw_antibot').'Configuration/TCA/Submission.php',
        'iconfile' => \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extRelPath('tw_antibot').'Resources/Public/Icons/tx_twantibot_domain_model_submission.png'
    ),
    'interface' => array(
        'showRecordFieldList' => 'ip, reason, ip, settings, data, fields',
    ),
    'types' => array(
        '1' => array('showitem' => 'reason, ip, settings, data, fields'),
    ),
    'palettes' => array(
        '1' => array('showitem' => ''),
    ),
    'columns' => array(

        'crdate' => array(
            'exclude' => 1,
            'config' => array(
                'type' => 'input',
                'size' => 13,
                'max' => 20,
                'eval' => 'datetime',
                'checkbox' => 0,
                'default' => 0,
                'range' => array(
                    'lower' => mktime(0, 0, 0, date('m'), date('d'), date('Y'))
                ),
            ),
        ),

        'ip' => array(
            'exclude' => 0,
            'label' => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_submission.ip',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required',
                'readOnly' => true,
            ),
        ),

        'reason' => array(
            'exclude' => 0,
            'label' => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_submission.reason',
            'config' => array(
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required',
                'readOnly' => true,
            ),
        ),


        'settings' => array(
            'exclude' => 0,
            'label' => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_submission.settings',
            'config' => array(
                'type' => 'text',
                'rows' => 10,
                'readOnly' => true,
            ),
        ),

        'data' => array(
            'exclude' => 0,
            'label' => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_submission.settings',
            'config' => array(
                'type' => 'text',
                'rows' => 10,
                'readOnly' => true,
            ),
        ),

        'fields' => array(
            'exclude' => 0,
            'label' => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_submission.fields',
            'config' => array(
                'type' => 'text',
                'rows' => 10,
                'readOnly' => true,
            ),
        ),
    ),
);
