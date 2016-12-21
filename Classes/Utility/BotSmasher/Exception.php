<?php

namespace Tollwerk\TwAntibot\Utility\BotSmasher;

/***************************************************************
 *
 *  Copyright notice
 *
 *  (c) 2016 Joschi Kuphal <joschi@tollwerk.de>, tollwerk GmbH
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
 */
class Exception extends \Exception implements \Countable
{
    /**
     * Messages
     *
     * @var \array
     */
    protected $_messages = array();

    /**
     * Add a message
     *
     * @param \string $message Message
     * @param \number $error Error code
     */
    public function addMessage($message, $error = 0)
    {
        $this->_messages[] = (object)array('message' => $message, 'error' => $error);
    }

    /**
     * Return all registered messages
     *
     * @return \array                    Messages
     */
    public function getMessages()
    {
        return $this->_messages;
    }

    /**
     * Return the number of registered messages
     *
     * @return \int                        Number of messages
     */
    public function count()
    {
        return count($this->_messages);
    }
}
