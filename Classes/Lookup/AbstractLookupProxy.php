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

namespace Tollwerk\TwAntibot\Lookup;

use Jkphl\Antibot\Domain\Antibot;
use Jkphl\Antibot\Ports\Contract\LookupStrategyInterface;
use TYPO3\CMS\Core\Resource\AbstractRepository;

/**
 * Abstract Lookup Proxy
 *
 * @package    Tollwerk\TwAntibot
 * @subpackage Tollwerk\TwAntibot\Utility
 */
class AbstractLookupProxy implements LookupStrategyInterface
{
    /**
     * Repository
     *
     * @var AbstractRepository
     */
    protected $repository;
    /**
     * Property type
     *
     * @var int
     */
    protected $property;

    /**
     * Abstract Lookup Proxy constructor
     *
     * @param int $property Property type
     */
    public function __construct(int $property)
    {
        $this->property = $property;
    }

    /**
     * Test whether the value in question can be found in the lookup pool
     *
     * @param string $value Value
     *
     * @return bool Value is contained in the pool
     */
    public function lookup(string $value): bool
    {
        $query       = $this->repository->createQuery();
        $constraints = [
            $query->equals('property', $this->property),
            $query->equals('value', $value),
        ];
        $query->matching($query->logicalAnd($constraints));
        $count = $query->count();

        return $count > 0;
    }
}
