<?php

/**
 * Bans: a client address or a value of a field, with the reason and the submission
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

use Tollwerk\TwAntibot\Store\BanStore;

$labels = 'LLL:EXT:tw_antibot/Resources/Private/Language/locallang_db.xlf:tx_twantibot_domain_model_blacklist';

return [
    'ctrl' => [
        'title' => $labels,
        'label' => 'value',
        'tstamp' => 'tstamp',
        'crdate' => 'crdate',
        'default_sortby' => 'crdate DESC',
        // A ban ends at its end time and lasts until someone deletes it if there is none
        'enablecolumns' => ['endtime' => 'endtime'],
        'typeicon_classes' => ['default' => 'tw-antibot-ban'],
    ],
    'types' => [
        '0' => [
            'showitem' => '--palette--;;subject, data, error,'
                . '--div--;core.form.tabs:access, endtime',
        ],
    ],
    'palettes' => [
        'subject' => ['showitem' => 'property, value'],
    ],
    'columns' => [
        'endtime' => [
            'label' => 'core.db.general:endtime',
            'config' => ['type' => 'datetime', 'default' => 0, 'searchable' => false],
        ],
        'property' => [
            'label' => $labels . '.property',
            'config' => [
                'type' => 'select',
                'renderType' => 'selectSingle',
                'items' => [
                    ['label' => $labels . '.property.address', 'value' => BanStore::ADDRESS],
                    ['label' => $labels . '.property.value', 'value' => BanStore::VALUE],
                ],
                'default' => BanStore::ADDRESS,
            ],
        ],
        'value' => [
            'label' => $labels . '.value',
            'config' => ['type' => 'input', 'size' => 30, 'max' => 255, 'required' => true, 'eval' => 'trim'],
        ],
        'data' => [
            'label' => $labels . '.data',
            'config' => ['type' => 'user', 'renderType' => 'antibotSubmission'],
        ],
        'error' => [
            'label' => $labels . '.error',
            'config' => ['type' => 'text', 'cols' => 40, 'rows' => 3, 'readOnly' => true],
        ],
    ],
];
