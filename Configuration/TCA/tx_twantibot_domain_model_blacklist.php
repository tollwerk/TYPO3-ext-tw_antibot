<?php
return [
    'ctrl'      => [
        'title'        => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_blacklist',
        'label'        => 'property',
        'tstamp'       => 'tstamp',
        'crdate'       => 'crdate',
        'cruser_id'    => 'cruser_id',
        'searchFields' => 'value,data,error',
        'iconfile'     => 'EXT:tw_antibot/Resources/Public/Icons/blacklist.png'
    ],
    'interface' => [
        'showRecordFieldList' => 'hidden, property, value, data, error',
    ],
    'types'     => [
        '1' => ['showitem' => '\'--palette--;;propertyvalue, --palette--;;dataerror'],
    ],
    'palettes'  => [
        'propertyvalue' => ['showitem' => 'property, value', 'canNotCollapse' => true],
        'dataerror' => ['showitem' => 'data, error', 'canNotCollapse' => true],
    ],
    'columns'   => [
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
