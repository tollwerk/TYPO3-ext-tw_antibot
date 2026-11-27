<?php

/**
 * Services of tw-antibot that depend on an optional extension
 *
 * @category  Tollwerk
 * @package   Tollwerk\TwAntibot
 * @author    Joschi Kuphal <joschi@tollwerk.de>
 * @copyright 2026 tollwerk GmbH
 * @license   https://spdx.org/licenses/GPL-2.0-or-later.html GPL-2.0-or-later
 * @link      https://code.tollwerk.net/tollwerk/typo3/extensions/tw-antibot
 */

declare(strict_types=1);

use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

return static function (ContainerConfigurator $container): void {
    // The adapter for EXT:form, whose events exist only with EXT:form
    if (!class_exists(FormRuntime::class)) {
        return;
    }

    $container->services()
        ->defaults()->autowire()->autoconfigure()
        ->load('Tollwerk\\TwAntibot\\Form\\', '../Classes/Form/*');
};
