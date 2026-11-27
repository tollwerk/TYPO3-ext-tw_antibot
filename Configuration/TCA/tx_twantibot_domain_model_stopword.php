<?php

/**
 * Stopwords: words or phrases that refuse a submission whose checked fields contain them
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

$labels = 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_stopword';

return [
    'ctrl' => [
        'title' => $labels,
        'label' => 'term',
        'label_alt' => 'note',
        'label_alt_force' => true,
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'delete' => 'deleted',
        'default_sortby' => 'term',
        // A disabled stopword takes no part, so that an editor can try out whether a term causes trouble
        'enablecolumns' => ['disabled' => 'hidden'],
        'typeicon_classes' => ['default' => 'tw-antibot-stopword'],
    ],
    'types' => [
        '0' => [
            'showitem' => 'term, note,'
                . '--div--;core.form.tabs:access, hidden',
        ],
    ],
    'columns' => [
        'hidden' => [
            'label' => 'core.db.general:enabled',
            'config' => [
                'type' => 'check',
                'renderType' => 'checkboxToggle',
                'items' => [['label' => '', 'invertStateDisplay' => true]],
            ],
        ],
        'term' => [
            'label' => $labels . '.term',
            'description' => $labels . '.term.description',
            'config' => ['type' => 'input', 'size' => 30, 'max' => 255, 'required' => true, 'eval' => 'trim,unique'],
        ],
        'note' => [
            'label' => $labels . '.note',
            'config' => ['type' => 'text', 'cols' => 40, 'rows' => 3, 'eval' => 'trim'],
        ],
    ],
];
