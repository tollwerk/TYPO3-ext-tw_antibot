<?php

namespace Tollwerk\TwAntibot\ViewHelpers\Access;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2015 Joschi Kuphal <joschi@tollwerk.de>, tollwerk GmbH
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
 * Antibot form block viewhelper
 * 
 * @package Tollwerk\TwAntibot\ViewHelpers
 */
class BlockedViewHelper extends \Tollwerk\TwAntibot\ViewHelpers\AccessViewHelper {
	/**
	 * Test if the current user is blocked access to the current form
	 * 
	 * @return \boolean				Blocked
	 */
	public function render() {
		return !$this->_validate();
	}
}
