<?php

/**
 * Examples of the protective fields of a form
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

$prefix = 'tx_twaccounts_registration[registration_3f2c9a41d0b7e85c6a19f4e2b7d03c58a9e1f6b2]';

return [
    'examples' => [
        'default' => [
            'label' => 'Hidden field and honeypots',
            'arguments' => [
                'fields' => [
                    ['name' => $prefix . '[email]', 'value' => '', 'type' => 'email'],
                    ['name' => $prefix . '[homepage]', 'value' => '', 'type' => 'url'],
                    ['name' => $prefix . '[hmac]', 'value' => '9c1f2e7a4b6d8035e1a2c3f4b5d6e7f8091a2b3c', 'type' => 'hidden'],
                    ['name' => $prefix . '[ts]', 'value' => '1795000000', 'type' => 'hidden'],
                ],
            ],
        ],
    ],
];
