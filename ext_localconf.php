<?php
if (!defined ('TYPO3_MODE')) {
	die ('Access denied.');
}

// Registering and including classes and namespaces from external libraries (chromephp etc.).
require_once (\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::extPath($_EXTKEY, 'Resources/Private/Libraries/autoload.php'));