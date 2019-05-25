<?php

if (!defined('TYPO3_MODE')) {
    die ('Access denied.');
}

$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterBuildingFinished'][1546083658] = \Tollwerk\TwAntibot\Utility\Antibot::class;
//$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterSubmit'][1546166516]                = \Tollwerk\TwAntibot\Utility\Antibot::class;
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['ext/form']['afterInitializeCurrentPage'][1546166516] = \Tollwerk\TwAntibot\Utility\Antibot::class;
