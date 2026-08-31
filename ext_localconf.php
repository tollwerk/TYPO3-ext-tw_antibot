<?php

defined('TYPO3_MODE') || die('Access denied.');

(function () {
    // Every route a form can take to the frontend ends in FluidFormRenderer::render(),
    // which calls this hook before it resolves the template name. That makes it the one
    // place where a form can be wrapped, whichever way it was embedded.
    $GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['beforeRendering'][1546083658]
        = \Tollwerk\TwAntibot\Hooks\FormHook::class;

    // ── Why this is set here and not in a static template ────────────────────────
    //
    // A static template only takes effect once an integrator includes it, and an armor
    // that silently does not apply is worse than none. Both lines below are conditions
    // for the protection to work at all, so they come with the extension itself.
    //
    // The USER_INT is the answer to a trap: EXT:form declares only `perform` as
    // non-cacheable, so the initial rendering of the form content element is cached —
    // and with it the armor of whoever requested the page first. Session-bound field
    // names and a timestamp would be handed to everyone else. USER_INT re-renders the
    // form on every request and leaves the rest of the page in the cache.
    //
    // Plugins that bring their own controller are already covered: Extbase converts
    // their USER object at runtime as soon as the action is registered as
    // non-cacheable (FrontendRequestHandler::handleRequest).
    \TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTypoScriptSetup(
        'plugin.tx_form.settings.yamlConfigurations.1560 = EXT:tw_antibot/Configuration/Yaml/CustomFormSetup.yaml' . LF .
        'module.tx_form.settings.yamlConfigurations.1560 = EXT:tw_antibot/Configuration/Yaml/CustomFormSetup.yaml' . LF .
        'tt_content.form_formframework.20 = USER_INT'
    );
})();
