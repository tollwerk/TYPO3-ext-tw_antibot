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

namespace Tollwerk\TwAntibot\Domain\Model;

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * Abstract List
 *
 * @package    Tollwerk\Antibot
 * @subpackage Tollwerk\TwAntibot\Domain\Model
 */
abstract class AbstractList extends AbstractEntity
{
    /**
     * Property
     *
     * @var int
     * @validate NotEmpty
     */
    protected $property = 0;

    /**
     * Value
     *
     * @var string
     * @validate NotEmpty
     */
    protected $value = '';
    /**
     * IP Address
     *
     * @var int
     */
    const PROPERTY_IP = 0;
    /**
     * EMAIL Address
     *
     * @var int
     */
    const PROPERTY_EMAIL = 1;

    /**
     * Returns the property
     *
     * @return int $property
     */
    public function getProperty()
    {
        return $this->property;
    }

    /**
     * Sets the property
     *
     * @param int $property
     *
     * @return void
     */
    public function setProperty($property)
    {
        $this->property = $property;
    }

    /**
     * Returns the value
     *
     * @return string $value
     */
    public function getValue()
    {
        return $this->value;
    }

    /**
     * Sets the value
     *
     * @param string $value
     *
     * @return void
     */
    public function setValue($value)
    {
        $this->value = $value;
    }
}
