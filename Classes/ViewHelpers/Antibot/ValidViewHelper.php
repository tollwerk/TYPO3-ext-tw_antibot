<?php

/**
 * tollwerk
 *
 * @category   Jkphl
 * @package    Jkphl\Antibot
 * @subpackage Tollwerk\TwAntibot\ViewHelpers\Antibot
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

namespace Tollwerk\TwAntibot\ViewHelpers\Antibot;

use Jkphl\Antibot\Domain\Contract\AntibotException;
use Jkphl\Antibot\Domain\Exceptions\ErrorException;
use Jkphl\Antibot\Domain\Model\ValidationResult;
use Jkphl\Antibot\Ports\Exceptions\InvalidArgumentException;
use Tollwerk\TwAntibot\Domain\Model\Blacklist;
use Tollwerk\TwAntibot\Domain\Model\FormElements\Antibot;
use Tollwerk\TwAntibot\Domain\Repository\BlacklistRepository;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Configuration\ConfigurationManager;
use TYPO3\CMS\Extbase\Configuration\Exception\InvalidConfigurationTypeException;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Extbase\Persistence\Exception\IllegalObjectTypeException;
use TYPO3\CMS\Form\Domain\Model\Exception\FormDefinitionConsistencyException;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3Fluid\Fluid\Core\Rendering\RenderingContextInterface;
use TYPO3Fluid\Fluid\Core\ViewHelper\AbstractConditionViewHelper;

/**
 * Antibot validation viewhelper
 *
 * @package    Tollwerk\TwAntibot
 * @subpackage Tollwerk\TwAntibot\ViewHelpers\Antibot
 */
class ValidViewHelper extends AbstractConditionViewHelper
{
    /**
     * Don't escape the output of this viewhelper
     *
     * @var boolean
     */
    protected $escapeOutput = false;

    /**
     * Initializes the "then" and "else" arguments
     */
    public function initializeArguments(): void
    {
        $this->registerArgument(
            'form',
            'TYPO3\\CMS\\Form\\Domain\\Runtime\\FormRuntime',
            'Form to be validated',
            true
        );
//        $this->registerArgument('then', 'mixed', 'Form to be rendered if Antibot validates the request.', false);
        $this->registerArgument(
            'else',
            'mixed',
            'Value to be returned if Antibot doesn\'t validate the request.',
            false
        );
    }

    /**
     * Static method which can be overridden by subclasses. If a subclass
     * requires a different (or faster) decision then this method is the one
     * to override and implement.
     *
     * @param array $arguments
     * @param RenderingContextInterface $renderingContext
     *
     * @return bool
     * @throws FormDefinitionConsistencyException
     * @throws IllegalObjectTypeException
     * @throws InvalidConfigurationTypeException
     */
    public static function verdict(array $arguments, RenderingContextInterface $renderingContext)
    {
        /** @var FormRuntime $formRuntime */
        $formRuntime    = $arguments['form'];
        $formDefinition = $formRuntime->getFormDefinition();
        foreach ($formDefinition->getRenderablesRecursively() as $renderable) {
            if ($renderable instanceof Antibot) {
                /**
                 * @var ServerRequest $request
                 * @var ValidationResult $validationResult
                 */
                $request          = $GLOBALS['TYPO3_REQUEST'];
                $validationResult = null;
                $valid            = $renderable->validate($request, $validationResult);
                if (!$valid) {
                    $renderingContext->getVariableProvider()->add('antibot', $validationResult);

                    // Blacklist if this is the first time the IP address failed
                    if (!$validationResult->isBlacklisted()) {
                        $serverParams         = $request->getServerParams();
                        $objectManager        = GeneralUtility::makeInstance(ObjectManager::class);
                        $configurationManager = $objectManager->get(ConfigurationManager::class);
                        $settings             = $configurationManager->getConfiguration(
                            ConfigurationManager::CONFIGURATION_TYPE_SETTINGS,
                            'TwAntibot'
                        );
                        $blacklistRepository  = $objectManager->get(BlacklistRepository::class);
                        $blacklistEntry       = GeneralUtility::makeInstance(Blacklist::class);
                        $blacklistEntry->setPid($settings['storagePid']);
                        $blacklistEntry->setProperty(Blacklist::PROPERTY_IP);
                        $blacklistEntry->setValue($serverParams['REMOTE_ADDR']);
                        $blacklistEntry->setError(json_encode(array_map(function(ErrorException $exception) {
                            return $exception->getMessage();
                        }, $validationResult->getErrors())));
                        $blacklistEntry->setData(json_encode(array_merge(
                            (array)$request->getQueryParams(),
                            (array)$request->getParsedBody()
                        )));
                        $blacklistRepository->add($blacklistEntry);
                    }
                }

                return $valid;
            }
        }

        return true;
    }

    /**
     * @param array $arguments
     * @param \Closure $renderChildrenClosure
     * @param RenderingContextInterface $renderingContext
     *
     * @return mixed
     */
    public static function renderStatic(
        array $arguments,
        \Closure $renderChildrenClosure,
        RenderingContextInterface $renderingContext
    ) {
        $result = parent::renderStatic($arguments, $renderChildrenClosure, $renderingContext);

        return $result;
    }

    /**
     * Renders <f:then> child if $condition is true, otherwise renders <f:else> child.
     *
     * @return string the rendered string
     * @throws \Exception If an unknown error occurs
     * @api
     */
    public function render()
    {
        try {
            if (!self::verdict($this->arguments, $this->renderingContext)) {
                throw new InvalidArgumentException();
            }

            return $this->renderThenChild();
        } catch (\Exception $e) {
            if ($e instanceof AntibotException) {
                return $this->renderElseChild();
            }
            throw $e;
        }
    }
}
