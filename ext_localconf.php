<?php

/**
 * Cache for fingerprints and the element that shows a banned submission
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

use Tollwerk\TwAntibot\Backend\SubmissionElement;
use TYPO3\CMS\Core\Cache\Frontend\VariableFrontend;

defined('TYPO3') || die();

// What is meant to expire: the fingerprints of accepted submissions. Bans are decisions and live in the database
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tw_antibot'] ??= [
    'frontend' => VariableFrontend::class,
];

// The submission of a ban as tables of fields
$GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1546083661] = [
    'nodeName' => 'antibotSubmission',
    'priority' => 40,
    'class' => SubmissionElement::class,
];
