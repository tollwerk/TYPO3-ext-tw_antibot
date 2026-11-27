<?php

/**
 * Fluid components of tw-antibot under the shared namespace
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

// Priority 30, see the README
return [
    'Tollwerk\\Components' => [
        'templatePaths' => [30 => 'EXT:tw_antibot/Resources/Private/Components'],
    ],
];
