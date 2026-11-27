<?php

/**
 * Middleware of tw-antibot in the frontend
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

use Tollwerk\TwAntibot\Http\KeepProtectedPagesPrivate;

return [
    'frontend' => [
        // Outside the rendering of the page, so that its headers come last
        'tollwerk/tw-antibot/keep-protected-pages-private' => [
            'target' => KeepProtectedPagesPrivate::class,
            'after' => ['typo3/cms-frontend/site'],
        ],
    ],
];
