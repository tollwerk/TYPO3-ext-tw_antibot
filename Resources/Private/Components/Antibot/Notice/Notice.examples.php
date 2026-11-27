<?php

/**
 * Examples of the notice in place of a closed form
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

return [
    'examples' => [
        'contact' => [
            'label' => 'With a contact address',
            'arguments' => ['contact' => 'hello@example.org'],
        ],
        'plain' => ['label' => 'Without a contact address', 'arguments' => []],
    ],
];
