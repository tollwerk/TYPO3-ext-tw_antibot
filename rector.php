<?php

declare(strict_types=1);

use Rector\Privatization\Rector\Property\PrivatizeFinalClassPropertyRector;
use Ssch\TYPO3Rector\Set\Typo3SetList;
use Tollwerk\Guidelines\Rector\ConfigFactory;

return ConfigFactory::create(__DIR__, ['Classes', 'Configuration', 'tests'])
    ->withSets([Typo3SetList::CODE_QUALITY, Typo3SetList::GENERAL, Typo3SetList::TYPO3_14])
    // Extbase sets the properties of a model from AbstractDomainObject, which cannot reach private ones
    ->withSkip([PrivatizeFinalClassPropertyRector::class => ['*/Classes/Domain/Model/*']]);
