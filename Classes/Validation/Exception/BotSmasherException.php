<?php

namespace Tollwerk\TwAntibot\Validation\Exception;

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
 * BotSmasher exception
 *
 */
class BotSmasherException extends \Tollwerk\TwAntibot\Validation\Exception {
	/**
	 * Return if an IP address match was found
	 *
	 * @return \boolean				Positive IP address match
	 */
	public function ipMatch() {
		return !!($this->getCode() & \Tollwerk\TwAntibot\Utility\BotSmasherClient::STATUS_IP);
	}
	/**
	 * Return if an email address match was found
	 *
	 * @return \boolean				Positive email address match
	 */
	public function emailMatch() {
		return !!($this->getCode() & \Tollwerk\TwAntibot\Utility\BotSmasherClient::STATUS_EMAIL);
	}
	/**
	 * Return if a name match was found
	 *
	 * @return \boolean				Positive name match
	 */
	public function nameMatch() {
		return !!($this->getCode() & \Tollwerk\TwAntibot\Utility\BotSmasherClient::STATUS_NAME);
	}
}
