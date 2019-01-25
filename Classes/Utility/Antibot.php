<?php

/**
 * tollwerk
 *
 * @category   Jkphl
 * @package    Jkphl\Antibot
 * @subpackage ${NAMESPACE}
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

namespace Tollwerk\TwAntibot\Utility;

use Jkphl\Antibot\Ports\Antibot as AntibotCore;
use Jkphl\Antibot\Ports\Validators\HmacValidator;
use Jkphl\Antibot\Ports\Validators\HoneypotValidator;
use Jkphl\Antibot\Ports\Validators\IpBlacklistValidator;
use Jkphl\Antibot\Ports\Validators\IpWhitelistValidator;
use Psr\Log\LoggerAwareTrait;
use Tollwerk\TwAntibot\Domain\Model\AbstractList;
use Tollwerk\TwAntibot\Lookup\BlacklistLookupProxy;
use Tollwerk\TwAntibot\Lookup\WhitelistLookupProxy;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\SingletonInterface;
use TYPO3\CMS\Core\Utility\DebugUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Object\ObjectManager;
use TYPO3\CMS\Form\Domain\Model\Renderable\CompositeRenderableInterface;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;

/**
 * Antibot Hooks
 *
 * @package    Jkphl\Antibot
 * @subpackage Tollwerk\TwAntibot\Hooks
 */
class Antibot implements SingletonInterface
{
    /**
     * Instantiate a logger instance
     */
    use LoggerAwareTrait;
    /**
     * User Session
     *
     * @var string
     */
    protected $session;
    /**
     * Antibot register
     *
     * @var AntibotCore[]
     */
    protected $antibot = [];

    /**
     * Antibot constructor
     */
    public function __construct()
    {
        $this->session = $GLOBALS['TSFE']->fe_user->getSessionId();
        $this->logger  = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
    }

    /**
     * Instantiate and return a form specific Antibot instance
     *
     * @param string $prefix       Form prefix
     * @param array $configuration Antibot configuration
     *
     * @return AntibotCore Antibot instance
     */
    public function getAntibot($prefix, array $configuration): AntibotCore
    {
        $sessionPrefixHash = md5($this->session.':'.$prefix.':'.serialize($configuration));
        if (!array_key_exists($sessionPrefixHash, $this->antibot)) {
            $this->antibot[$sessionPrefixHash] = new AntibotCore($this->session, $prefix);
            $this->antibot[$sessionPrefixHash]->setParameterScope('tx_form_formframework', $prefix);
            $this->antibot[$sessionPrefixHash]->setLogger($this->logger);
            $this->addValidators($this->antibot[$sessionPrefixHash], $configuration);
        }

        return $this->antibot[$sessionPrefixHash];
    }

    /**
     * Finalize Antibot setup
     *
     * During this step, the current request is validated and Antibot's subelements are added to the form.
     *
     * @param RenderableInterface $renderable
     *
     * @throws \TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotFoundException
     * @throws \TYPO3\CMS\Form\Domain\Exception\TypeDefinitionNotValidException
     * @throws \TYPO3\CMS\Form\Domain\Model\Exception\FormDefinitionConsistencyException
     */
    public function afterBuildingFinished(RenderableInterface $renderable): void
    {
        // If this is an Antibot element
        if ($renderable instanceof \Tollwerk\TwAntibot\Domain\Model\FormElements\Antibot) {
            $request = $GLOBALS['TYPO3_REQUEST'];
            $renderable->armor($request);
        }
    }

    /**
     * Add Antibot validators
     *
     * @param AntibotCore $antibot Antibot instance
     * @param array $config        Antibot configuration
     */
    protected function addValidators(AntibotCore $antibot, array $config): void
    {
        $objectManager = GeneralUtility::makeInstance(ObjectManager::class);

        // Register whitelist validators
        foreach ($config['whitelist'] as $whitelist) {
            if ($whitelist == AbstractList::PROPERTY_IP) {
                $lookupProxy = $objectManager->get(WhitelistLookupProxy::class, $whitelist);
                $antibot->addValidator(new IpWhitelistValidator($lookupProxy));
            }
        }

        // Register blacklist validators
        foreach ($config['blacklist'] as $blacklist) {
            switch ($blacklist) {
                case AbstractList::PROPERTY_IP:
                    $lookupProxy = $objectManager->get(BlacklistLookupProxy::class, $blacklist);
                    $antibot->addValidator(new IpBlacklistValidator($lookupProxy));
                    break;
                case AbstractList::PROPERTY_EMAIL:
//                    $lookupProxy = new BlacklistLookupProxy($blacklist);
//                    $paramValidator = new ParameterBlacklistValidator($lookupProxy);
//                    $antibot->addValidator($paramValidator);
            }
        }

        // Add honeypots
        if (!empty($config['honeypots'])) {
            $antibot->addValidator(new HoneypotValidator($config['honeypots']));
        }

        $hmacValidator = new HmacValidator();

        $antibot->addValidator($hmacValidator);
    }

    /**
     * Hook after the current page initialization
     *
     * In this step, the form is reset to the last displayed page if the Antibot validation fails
     *
     * @param FormRuntime $formRuntime                       Form runtime
     * @param CompositeRenderableInterface|null $currentPage Current page (NULL when this is the final step)
     * @param CompositeRenderableInterface|null $lastPage    Last displayed page (NULL when this is the first step)
     * @param array $requestArguments                        Request arguments
     *
     * @return CompositeRenderableInterface|null             Current page
     * @throws \TYPO3\CMS\Form\Domain\Model\Exception\FormDefinitionConsistencyException
     */
    public function afterInitializeCurrentPage(
        FormRuntime $formRuntime,
        CompositeRenderableInterface $currentPage = null,
        CompositeRenderableInterface $lastPage = null,
        array $requestArguments = []
    ) {
        $valid          = true;
        $formDefinition = $formRuntime->getFormDefinition();
        foreach ($formDefinition->getRenderablesRecursively() as $renderable) {
            if ($renderable instanceof \Tollwerk\TwAntibot\Domain\Model\FormElements\Antibot) {
                $valid = $renderable->validate($GLOBALS['TYPO3_REQUEST']);
                break;
            }
        }

        return $valid ? $currentPage : $lastPage;
    }
}
