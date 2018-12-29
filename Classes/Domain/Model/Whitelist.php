<?php

namespace Tollwerk\TwAntibot\Domain\Model;


/***
 *
 * This file is part of the "tollwerk Anti-Spambot tools" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2018 Joschi Kuphal <joschi@tollwerk.de>, tollwerk GmbH
 *
 ***/

/**
 * Antibot Whitelist Entry
 */
class Whitelist extends AbstractList
{
    /**
     * note
     *
     * @var string
     */
    protected $note = '';

    /**
     * Returns the note
     *
     * @return string $note
     */
    public function getNote()
    {
        return $this->note;
    }

    /**
     * Sets the note
     *
     * @param string $note
     *
     * @return void
     */
    public function setNote($note)
    {
        $this->note = $note;
    }
}
