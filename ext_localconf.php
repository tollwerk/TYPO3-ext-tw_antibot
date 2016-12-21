<?php

if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

// Command controller registration
$GLOBALS['TYPO3_CONF_VARS']['SC_OPTIONS']['extbase']['commandControllers'][] = 'Tollwerk\\TwAntibot\\Command\\AntibotCommandController';
