<?php

/**
 * Icons of the records of tw-antibot
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

use TYPO3\CMS\Core\Imaging\IconProvider\BitmapIconProvider;
use TYPO3\CMS\Core\Imaging\IconProvider\SvgIconProvider;

return [
    'tw-antibot-ban' => [
        'provider' => BitmapIconProvider::class,
        'source' => 'EXT:tw_antibot/Resources/Public/Icons/Ban.png',
    ],
    'tw-antibot-stopword' => [
        'provider' => SvgIconProvider::class,
        'source' => 'EXT:tw_antibot/Resources/Public/Icons/Stopword.svg',
    ],
];
