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
     * Default Antibot configuration
     */
    const DEFAULT_CONFIG = [
        'blacklist' => [],
        'whitelist' => []
    ];

    /**
     * Instantiate and return an associate Antibot instance
     *
     * @return AntibotCore
     * @throws \TYPO3\CMS\Form\Domain\Model\Exception\FormDefinitionConsistencyException
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
     * @throws \TYPO3\CMS\Form\Domain\Model\Exception\FormDefinitionConsistencyException
     */
    public function validate(ServerRequest $request): void
    {
        $validationResult = $this->getAntibot()->validate($request);
        print_r($validationResult);
    }

    /**
     * Create and add the Antibot armor form elements
     *
     * @param ServerRequest $request Current request
     *
     * @throws \TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotFoundException
     * @throws \TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotValidException
     * @throws \TYPO3\CMS\Form\Domain\Model\Exception\FormDefinitionConsistencyException
     */
    public function armor(ServerRequest $request): void
    {
        $armor = $this->getAntibot()->armorInputs($request);

        // Run through all armor input parameters
        /** @var InputElement $armorInput */
        foreach ($armor as $armorInput) {
            $armorInputAttributes = $armorInput->getAttributes();

            switch ($armorInputAttributes['type']) {
                case 'hidden':
                    $this->createElement($armorInputAttributes['name'], 'Hidden')
                         ->setDefaultValue($armorInputAttributes['value']);
                    break;
            }
        }
    }

    /**
     * Return a sanitized Antibot configuration
     *
     * @return array Antibot configuration
     */
    protected
    function getAntibotConfiguration(): array
    {
        $config = self::DEFAULT_CONFIG;
        $this->configureWhitelist($config);
        $this->configureBlacklist($config);
        $this->configureMethodVector($config);
        $this->configureSubmissionTimes($config);

        return $config;
    }

    /**
     * Configure whitelist options
     *
     * @param array $config Antibot configuration
     */
    protected
    function configureWhitelist(
        array &$config
    ): void {
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
    protected
    function configureBlacklist(
        array &$config
    ): void {
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
     * Configure the expected request method vector
     *
     * @param array $config Antibot configuration
     */
    protected
    function configureMethodVector(
        array &$config
    ): void {
        // TODO
    }

    /**
     * Configure the expected submission times
     *
     * @param array $config Antibot configuration
     */
    protected
    function configureSubmissionTimes(
        array &$config
    ): void {
        // TODO
    }
}
