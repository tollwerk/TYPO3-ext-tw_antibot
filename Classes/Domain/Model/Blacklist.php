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
 * Antibot Blacklist Entry
 */
class Blacklist extends AbstractList
{
    /**
     * Submission data
     *
     * @var string
     */
    protected $data = '';

    /**
     * Error description
     *
     * @var string
     */
    protected $error = '';

    /**
     * Returns the data
     *
     * @return string $data
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Sets the data
     *
     * @param string $data
     *
     * @return void
     */
    public function setData($data)
    {
        $this->data = $data;
    }

    /**
     * Returns the error
     *
     * @return string $error
     */
    public function getError()
    {
        return $this->error;
    }

    /**
     * Sets the error
     *
     * @param string $error
     *
     * @return void
     */
    public function setError($error)
    {
        $this->error = $error;
    }
}
