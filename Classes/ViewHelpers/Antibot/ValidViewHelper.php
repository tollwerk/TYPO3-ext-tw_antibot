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
use Tollwerk\TwAntibot\Domain\Model\FormElements\Antibot;
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
     * Note: method signature does not type-hint that an array is desired,
     * and as such, *appears* to accept any input type. There is no type hint
     * here for legacy reasons - the signature is kept compatible with third
     * party packages which depending on PHP version would error out if this
     * signature was not compatible with that of existing and in-production
     * subclasses that will be using this base class in the future. Let this
     * be a warning if someone considers changing this method signature!
     *
     * @param array|NULL $arguments
     *
     * @return boolean
     * @throws \TYPO3\CMS\Form\Domain\Model\Exception\FormDefinitionConsistencyException
     * @api
     */
    protected static function evaluateCondition($arguments = null)
    {
        /** @var FormRuntime $formRuntime */
        $formRuntime    = $arguments['form'];
        $formDefinition = $formRuntime->getFormDefinition();
        foreach ($formDefinition->getRenderablesRecursively() as $renderable) {
            if ($renderable instanceof Antibot) {
                return $renderable->validate($GLOBALS['TYPO3_REQUEST']);
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
            $thenChild = $this->renderThenChild();

            return $thenChild;
        } catch (\Exception $e) {
            if ($e instanceof AntibotException) {
                return $this->renderElseChild();
            }
            throw $e;
        }
    }
}
