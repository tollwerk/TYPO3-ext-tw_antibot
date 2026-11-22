<?php
return [
    'ctrl'      => [
        'title'           => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_stopword',
        'label'           => 'term',
        'label_alt'       => 'note',
        'label_alt_force' => true,
        'tstamp'          => 'tstamp',
        'crdate'          => 'crdate',
        'cruser_id'       => 'cruser_id',
        'default_sortby'  => 'term',
        'searchFields'    => 'term,note',
        // A term that is switched off takes no part in the check. That makes it possible
        // to try out whether a term causes trouble without losing it.
        'enablecolumns'   => ['disabled' => 'hidden'],
        'iconfile'        => 'EXT:tw_antibot/Resources/Public/Icons/stopword.svg'
    ],
    'types'     => [
        '1' => [
            'showitem' => 'term, note,'
                . '--div--;LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.access,'
                . 'hidden',
        ],
    ],
    'columns'   => [
        'hidden' => [
            'exclude' => true,
            'label'   => 'LLL:EXT:core/Resources/Private/Language/locallang_general.xlf:LGL.enabled',
            'config'  => [
                'type'       => 'check',
                'renderType' => 'checkboxToggle',
                'items'      => [
                    [
                        0                    => '',
                        1                    => '',
                        'invertStateDisplay' => true,
                    ],
                ],
            ],
        ],
        'term'   => [
            'exclude' => false,
            'label'   => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_stopword.term',
            'description' => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_stopword.term.description',
            'config'  => [
                'type' => 'input',
                'size' => 30,
                'max'  => 255,
                'eval' => 'trim,required,unique'
            ],
        ],
        'note'   => [
            'exclude' => false,
            'label'   => 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_stopword.note',
            'config'  => [
                'type' => 'text',
                'cols' => 40,
                'rows' => 4,
                'eval' => 'trim'
            ]
        ],
    ],
];
