<?php

namespace Tollwerk\TwAntibot\ViewHelpers;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2017 Joschi Kuphal <joschi@tollwerk.de>, tollwerk GmbH
 *
 *  All rights reserved
 *
 *  This script is part of the TYPO3 project. The TYPO3 project is
 *  free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License as published by
 *  the Free Software Foundation; either version 3 of the License, or
 *  (at your option) any later version.
 *
 *  The GNU General Public License can be found at
 *  http://www.gnu.org/copyleft/gpl.html.
 *
 *  This script is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  This copyright notice MUST APPEAR in all copies of the script!
 ***************************************************************/

/**
 * Abstract antibot form access viewhelper base
 *
 * @package Tollwerk\TwAntibot\ViewHelpers
 */
abstract class AccessViewHelper extends AbstractAntibotViewHelper
{
    /**
     * Validate the current user
     *
     * @param \string $argument Form argument name
     * @return \boolean Successful validation
     */
    protected function _validate($argument)
    {
        $templateVariableContainer = $this->renderingContext->getTemplateVariableContainer();
        $request = $this->controllerContext->getRequest();
        $request = $request->getOriginalRequest() ?: $request;
        $arguments = $request->getArguments();

        return \Tollwerk\TwAntibot\Validation\Validator::validate($this->_extendedSettings,
            ($argument && array_key_exists($argument, $arguments)) ? $arguments[$argument] : null);
    }
}
