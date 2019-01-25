<?php

/**
 * tollwerk
 *
 * @category   Jkphl
 * @package    Jkphl\Antibot
 * @subpackage Tollwerk\TwAntibot\Domain\Model
 * @author     Joschi Kuphal <joschi@kuphal.net> / @jkphl
 * @copyright  Copyright © 2018 Joschi Kuphal <joschi@kuphal.net> / @jkphl
 * @license    http://opensource.org/licenses/MIT The MIT License (MIT)
 */

/***********************************************************************************
 *  The MIT License (MIT)
 *
 *  Copyright © 2018 Joschi Kuphal <joschi@kuphal.net>
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy of
 *  this software and associated documentation files (the "Software"), to deal in
 *  the Software without restriction, including without limitation the rights to
 *  use, copy, modify, merge, publish, distribute, sublicense, and/or sell copies of
 *  the Software, and to permit persons to whom the Software is furnished to do so,
 *  subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS
 *  FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR
 *  COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER
 *  IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN
 *  CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 ***********************************************************************************/

namespace Tollwerk\TwAntibot\Domain\Model\FormElements;

use Jkphl\Antibot\Infrastructure\Model\InputElement;
use Jkphl\Antibot\Ports\Antibot as AntibotCore;
use Tollwerk\TwAntibot\Domain\Model\AbstractList;
use Tollwerk\TwAntibot\Utility\Antibot as AntibotUtility;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Form\Domain\Model\Exception\FormDefinitionConsistencyException;

/**
 * Antibot Form Section
 *
 * @package    Tollwerk\Antibot
 * @subpackage Tollwerk\TwAntibot\Domain\Model
 */
class Antibot extends \TYPO3\CMS\Form\Domain\Model\FormElements\Section
{
    /**
     * Antibot instance
     *
     * @var AntibotCore
     */
    protected $antibot;

    /**
     * Current form validity
     *
     * @var null|bool
     */
    protected $valid = null;

    /**
     * Default Antibot configuration
     */
    const DEFAULT_CONFIG = [
        'blacklist' => [],
        'whitelist' => [],
        'honeypots' => [],
    ];

    /**
     * Valid input types
     */
    const INPUT_TYPES = [
        'checkbox' => 'Checkbox',
        'date'     => 'Date',
        'email'    => 'Email',
        'hidden'   => 'Hidden',
        'number'   => 'Number',
        'password' => 'Password',
        'radio'    => 'RadioButton',
        'tel'      => 'Telephone',
        'text'     => 'Text',
        'url'      => 'Url',
//        'color',
//        'datetime-local',
//        'month',
//        'range',
//        'search',
//        'time',
//        'week'
    ];

    /**
     * Instantiate and return an associate Antibot instance
     *
     * @return AntibotCore
     * @throws FormDefinitionConsistencyException
     */
    protected function getAntibot()
    {
        if ($this->antibot === null) {
            $this->antibot = GeneralUtility::makeInstance(AntibotUtility::class)->getAntibot(
                $this->getRootForm()->getIdentifier(),
                $this->getAntibotConfiguration()
            );
        }

        return $this->antibot;
    }

    /**
     * Validate the current request
     *
     * @param ServerRequest $request Current request
     *
     * @return bool Validates
     * @throws FormDefinitionConsistencyException
     */
    public function validate(ServerRequest $request): bool
    {
        // One time validation
        if ($this->valid === null) {
            $validationResult = $this->getAntibot()->validate($request);
            $this->valid      = !$validationResult->isFailed();
        }

        return $this->valid;
    }

    /**
     * Create and add the Antibot armor form elements
     *
     * @param ServerRequest $request Current request
     *
     * @throws \TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotFoundException
     * @throws \TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotValidException
     * @throws FormDefinitionConsistencyException
     */
    public function armor(ServerRequest $request): void
    {
        $this->validate($request);
        $armor = $this->getAntibot()->armorInputs($request);

        // Run through all armor input parameters
        /** @var InputElement $armorInput */
        foreach ($armor as $armorInput) {
            $armorInputAttributes = $armorInput->getAttributes();
            $identifier           = preg_replace('/\]?\[/', '.', rtrim($armorInputAttributes['name'], ']'));
            $input                = $this->createElement($identifier, 'AntibotField');
            $label                = explode(
                ' ',
                ucwords(trim(preg_replace('/\W+/', ' ', $armorInputAttributes['name'])))
            );
            $input->setDefaultValue($armorInputAttributes['value']);
            $input->setLabel(array_pop($label));
            $input->setRenderingOption('fluidAdditionalAttributes', [
                'autocomplete' => 'off',
                'aria-hidden'  => 'true',
            ]);
            $input->setRenderingOption('fluidType', $armorInputAttributes['type']);
        }
    }

    /**
     * Return a sanitized Antibot configuration
     *
     * @return array Antibot configuration
     */
    protected function getAntibotConfiguration(): array
    {
        $config = self::DEFAULT_CONFIG;
        $this->configureWhitelist($config);
        $this->configureBlacklist($config);
        $this->configureHoneypots($config);
        $this->configureMethodVector($config);
        $this->configureSubmissionTimes($config);

        return $config;
    }

    /**
     * Configure whitelist options
     *
     * @param array $config Antibot configuration
     */
    protected function configureWhitelist(array &$config): void
    {
        if (isset($this->renderingOptions['whitelist']) && is_array($this->renderingOptions['whitelist'])) {
            foreach (['ip' => AbstractList::PROPERTY_IP] as $property => $value) {
                if (!empty($this->renderingOptions['whitelist'][$property])) {
                    $config['whitelist'][] = $value;
                }
            }
        }
    }

    /**
     * Configure blacklist options
     *
     * @param array $config Antibot configuration
     */
    protected function configureBlacklist(array &$config): void
    {
        if (isset($this->renderingOptions['blacklist']) && is_array($this->renderingOptions['blacklist'])) {
            foreach (
                [
                    'ip'    => AbstractList::PROPERTY_IP,
                    'email' => AbstractList::PROPERTY_EMAIL
                ] as $property => $value
            ) {
                if (!empty($this->renderingOptions['blacklist'][$property])) {
                    $config['blacklist'][] = $value;
                }
            }
        }
    }

    /**
     * Configure the honeypots
     *
     * @param array $config Antibot configuration
     */
    protected function configureHoneypots(array &$config): void
    {
        if (!empty($this->renderingOptions['honeypots']) && is_array($this->renderingOptions['honeypots'])) {
            $config['honeypots'] = $this->filterHoneypotsRecursive($this->renderingOptions['honeypots']);
        }
    }

    /**
     * Recursively sanitize and filter a honeypot configuration and remove invalid entries
     *
     * @param array $honeypots Honeypot configuration
     *
     * @return array Filtered honeypot configuration
     */
    protected function filterHoneypotsRecursive(array $honeypots): array
    {
        $filtered = [];
        foreach ($honeypots as $name => $type) {
            if (is_array($type)) {
                $filtered[$name] = $this->filterHoneypotsRecursive($type);
                continue;
            }
            if (is_string($type)) {
                $type = strtolower($type);
                if (array_key_exists($type, self::INPUT_TYPES)) {
                    $filtered[$name] = $type;
                    continue;
                }
            }
        }

        return $filtered;
    }

    /**
     * Configure the expected request method vector
     *
     * @param array $config Antibot configuration
     */
    protected function configureMethodVector(array &$config): void
    {
        // TODO
    }

    /**
     * Configure the expected submission times
     *
     * @param array $config Antibot configuration
     */
    protected function configureSubmissionTimes(array &$config): void
    {
        // TODO
    }
}
