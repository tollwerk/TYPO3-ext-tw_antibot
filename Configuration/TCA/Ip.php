<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

$GLOBALS['TCA']['tx_twantibot_domain_model_ip'] = array(
	'ctrl' => $GLOBALS['TCA']['tx_twantibot_domain_model_ip']['ctrl'],
	'interface' => array(
		'showRecordFieldList' => 'hidden, ip, endtime',
	),
	'types' => array(
		'1' => array('showitem' => 'hidden;;1, ip, --div--;LLL:EXT:cms/locallang_ttc.xlf:tabs.access, endtime'),
	),
	'palettes' => array(
		'1' => array('showitem' => ''),
	),
	'columns' => array(
	
		'hidden' => array(
			'exclude' => 1,
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.hidden',
			'config' => array(
				'type' => 'check',
			),
		),
		'endtime' => array(
			'exclude' => 1,
			'l10n_mode' => 'mergeIfNotBlank',
			'label' => 'LLL:EXT:lang/locallang_general.xlf:LGL.endtime',
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
			'label' => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_ip.ip',
			'config' => array(
				'type' => 'input',
				'size' => 30,
				'eval' => 'trim,required'
			),
		),

		'submission' => array(
			'exclude' => 0,
			'label' => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_ip.submission',
			'config' => array(
				'type' => 'select',
				'foreign_table' => 'xlf:tx_twantibot_domain_model_submission',
				'maxitems'      => 1,
				'minitems'      => 1,
				'size' 		    => 1,
			),
		),
	),
);
