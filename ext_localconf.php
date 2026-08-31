<?php

defined('TYPO3_MODE') || die('Access denied.');

// Every route a form can take to the frontend ends in FluidFormRenderer::render(),
// which calls this hook before it resolves the template name. That makes it the one
// place where a form can be wrapped, whichever way it was embedded.
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['beforeRendering'][1546083658]
    = \Tollwerk\TwAntibot\Hooks\FormHook::class;

// The armor is added while the form is being built: the definition is complete and the
// runtime does not exist yet.
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterBuildingFinished'][1546083659]
    = \Tollwerk\TwAntibot\Hooks\FormHook::class;

// A cache for what is meant to expire — content fingerprints above all. Bans are not in
// here: they are decisions, they live in the database, and an editor revokes them.
$GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tw_antibot']
    = $GLOBALS['TYPO3_CONF_VARS']['SYS']['caching']['cacheConfigurations']['tw_antibot']
    ?? ['frontend' => \TYPO3\CMS\Core\Cache\Frontend\VariableFrontend::class];
