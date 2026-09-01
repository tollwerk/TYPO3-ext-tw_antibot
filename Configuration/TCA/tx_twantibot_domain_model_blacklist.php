<?php
return [
    'ctrl'      => [
        'title'        => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_blacklist',
        'label'        => 'value',
        'tstamp'       => 'tstamp',
        'crdate'       => 'crdate',
        'cruser_id'    => 'cruser_id',
        'searchFields' => 'value,data,error',
        // A ban lifts itself once its end time has passed: TYPO3 hides the record from
        // every query that respects the enable fields, and the store asks the same
        // question of its own. An end time of zero stands for a ban that does not lift.
        'enablecolumns' => ['endtime' => 'endtime'],
        'iconfile'     => 'EXT:tw_antibot/Resources/Public/Icons/blacklist.png'
    ],
    'interface' => [
        'showRecordFieldList' => 'hidden, property, value, data, error',
    ],
    'types'     => [
        '1' => [
            'showitem' => '--palette--;;propertyvalue, --palette--;;dataerror,'
                . '--div--;LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.access,'
                . '--palette--;;access',
        ],
    ],
    'palettes'  => [
        'propertyvalue' => ['showitem' => 'property, value', 'canNotCollapse' => true],
        'dataerror'     => ['showitem' => 'data, error', 'canNotCollapse' => true],
        'access'        => ['showitem' => 'endtime', 'canNotCollapse' => true],
    ],
    'columns'   => [
        'endtime'  => [
            'exclude' => true,
            'label'   => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.endtime',
            'config'  => [
                'type'       => 'input',
                'renderType' => 'inputDateTime',
                'eval'       => 'datetime,int',
                'default'    => 0,
                'range'      => ['upper' => mktime(0, 0, 0, 1, 1, 2038)],
            ],
        ],
        'property' => [
            'exclude' => false,
            'label'   => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_blacklist.property',
            'config'  => [
                'type'       => 'select',
                'renderType' => 'selectSingle',
                'items'      => [
                    [
                        'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_list.property.ip',
                        \Tollwerk\TwAntibot\Domain\Model\AbstractList::PROPERTY_IP
                    ],
                    [
                        'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_list.property.email',
                        \Tollwerk\TwAntibot\Domain\Model\AbstractList::PROPERTY_EMAIL
                    ],
                ],
                'size'       => 1,
                'maxitems'   => 1,
                'eval'       => 'required'
            ],
        ],
        'value'    => [
            'exclude' => false,
            'label'   => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_blacklist.value',
            'config'  => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required'
            ],
        ],
        'data'     => [
            'exclude' => false,
            'label'   => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_blacklist.data',
            'config'  => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 15,
                'eval' => 'trim'
            ]
        ],
        'error'    => [
            'exclude' => false,
            'label'   => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_blacklist.error',
            'config'  => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 4,
                'eval' => 'trim'
            ]
        ],
    ],
];
