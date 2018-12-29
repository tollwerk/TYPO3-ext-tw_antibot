<?php
return [
    'ctrl'      => [
        'title'        => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_whitelist',
        'label'        => 'property',
        'tstamp'       => 'tstamp',
        'crdate'       => 'crdate',
        'cruser_id'    => 'cruser_id',
        'searchFields' => 'value,note',
        'iconfile'     => 'EXT:tw_antibot/Resources/Public/Icons/whitelist.png'
    ],
    'interface' => [
        'showRecordFieldList' => 'property, value, note',
    ],
    'types'     => [
        '1' => ['showitem' => '--palette--;;propertyvalue, note'],
    ],
    'palettes'     => [
        'propertyvalue' => ['showitem' => 'property, value', 'canNotCollapse' => true],
    ],
    'columns'   => [
        'property' => [
            'exclude' => false,
            'label'   => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_whitelist.property',
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
            'label'   => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_whitelist.value',
            'config'  => [
                'type' => 'input',
                'size' => 30,
                'eval' => 'trim,required'
            ],
        ],
        'note'     => [
            'exclude' => false,
            'label'   => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_whitelist.note',
            'config'  => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 15,
                'eval' => 'trim'
            ]
        ],

    ],
];
