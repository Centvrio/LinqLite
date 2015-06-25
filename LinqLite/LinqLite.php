<?php

namespace LinqLite;

use LinqLite\Comparer\ComparerParam;
use LinqLite\Comparer\DefaultComparer;
use LinqLite\Comparer\IComparer;
use LinqLite\Exception\ArgumentException;
use LinqLite\Exception\ArgumentNullException;
use LinqLite\Exception\IndexOutOfRangeException;
use LinqLite\Exception\InvalidOperationException;

/**
 * Class LinqLite
 *
 * @version 1.4.7
 * @package LinqLite
 * @property-read integer $rank Returns array rank
 */
class LinqLite
{
    /**
     * @var array
     */
    protected $storage = [];

    protected $isDictionary = null;

    private function __construct()
    {
    }

    /**
     * @param array|\ArrayObject $source
     *
     * @return LinqLite
     * @throws ArgumentException
     * @throws ArgumentNullException
     */
    public static function from($source)
    {
        $instance = new static();
        if (is_null($source)) {
            throw new ArgumentNullException('Source is null.');
        }
        if (is_array($source)) {
            $instance->storage = $source;
        } elseif ($source instanceof \ArrayObject) {
            $instance->storage = $source->getArrayCopy();
        } else {
            throw new ArgumentException();
        }

        return $instance;
    }

    // region Filtering

    public function where(callable $predicate)
    {
        $this->getWhere($predicate);

        return $this;
    }

    public function ofType($type)
    {
        $predicate = function ($value) use ($type) {
            if (is_object($value)) {
                return $value instanceof $type;
            } else {
                return gettype($value) === $type;
            }
        };
        $this->getWhere($predicate);

        return $this;
    }

    // endregion

    // region Projection

    public function select(\Closure $selector)
    {
        if (!is_null($selector)) {
            foreach ($this->storage as $key => $value) {
                $this->storage[$key] = $selector($value, $key);
            }
        }

        return $this;
    }

    // endregion

    // region Joining

    public function join(array $inner, \Closure $outerKeySelector, \Closure $innerKeySelector, \Closure $resultSelector)
    {
        $result = [];
        if (!is_null($outerKeySelector) && !is_null($innerKeySelector) && !is_null($resultSelector)) {
            $outer = $this->storage;
            if (count($outer) > 0 && count($inner) > 0) {
                foreach ($outer as $outerKey => $outerItem) {
                    $resultKey = $outerKeySelector($outerItem, $outerKey);
                    foreach ($inner as $innerKey => $innerItem) {
                        if ($resultKey == $innerKeySelector($innerItem, $innerKey)) {
                            $result[][$resultKey] = $resultSelector($outerItem, $innerItem);
                        }
                    }
                }
                $this->storage = $result;
            }
        }
        $this->isDictionary = false;

        return $this;
    }

    // endregion

    // region Grouping

    public function toLookup(\Closure $keySelector)
    {
        if (!is_null($keySelector) && count($this->storage) > 0) {
            $items = $this->storage;
            $lookup = [];
            foreach ($items as $key => $item) {
                $newKey = $keySelector($item, $key);
                $lookup[$newKey][] = $item;
            }
            $this->storage = $lookup;
        }
        $this->isDictionary = true;

        return $this;
    }

    public function groupBy(\Closure $keySelector, \Closure $selector)
    {
        if (!is_null($keySelector) && !is_null($selector) && count($this->storage) > 0) {
            $items = $this->storage;
            $grouped = [];
            foreach ($items as $key => $item) {
                $newKey = $keySelector($item, $key);
                $grouped[$newKey][] = $selector($item, $key);
            }
            $this->storage = $grouped;
        }
        $this->isDictionary = true;

        return $this;
    }

    public function groupJoin(
        array $inner,
        \Closure $outerKeySelector,
        \Closure $innerKeySelector,
        \Closure $resultSelector
    ) {
        $result = [];
        if (!is_null($outerKeySelector) && !is_null($innerKeySelector) && !is_null($resultSelector) && count($inner) > 0) {
            $outer = $this->storage;
            if (count($outer) > 0 && count($inner) > 0) {
                foreach ($outer as $outerKey => $outerItem) {
                    $resultKey = $outerKeySelector($outerItem, $outerKey);
                    $innerTemp = [];
                    foreach ($inner as $innerKey => $innerItem) {
                        if ($resultKey == $innerKeySelector($innerItem, $innerKey)) {
                            $innerTemp[$innerKey] = $innerItem;
                        }
                    }
                    $result[$resultKey] = $resultSelector($outerItem, $innerTemp);
                }
            }
            $this->storage = $result;
        }
        $this->isDictionary = true;

        return $this;
    }

    public function zip(array $second, \Closure $resultSelector)
    {
        if (is_null($resultSelector)) {
            throw new ArgumentNullException();
        }
        $result = [];
        $first = $this->storage;
        reset($first);
        reset($second);
        $countFirst = count($first);
        $countSecond = count($second);
        $count = $countFirst != $countSecond ? ($countFirst < $countSecond ? $countFirst : $countSecond) : $countFirst;
        if ($count > 0) {
            for ($i = 0; $i < $count; $i++) {
                $firstItem = current($first);
                $secondItem = current($second);
                $result[] = $resultSelector($firstItem, $secondItem);
                next($first);
                next($second);
            }
            $this->storage = $result;
        }
        $this->isDictionary = false;

        return $this;
    }

    // endregion

    // region Pagination

    public function first(\Closure $predicate = null)
    {
        $this->getWhere($predicate);
        $array = $this->getResult();
        if (count($array) > 0) {
            $result = $array[0];
        } else {
            if (!is_null($predicate)) {
                throw new InvalidOperationException();
            }
            throw new InvalidOperationException('The source array is empty.');
        }

        return $result;
    }

    public function firstOrDefault(\Closure $predicate = null, $defaultValue = null)
    {
        $this->getWhere($predicate);
        $array = $this->getResult();
        if (count($array) > 0) {
            $result = $array[0];
        } else {
            $result = $defaultValue;
        }

        return $result;
    }

    public function last(\Closure $predicate = null)
    {
        $this->getWhere($predicate);
        $array = $this->getResult();
        $count = count($array);
        if ($count > 0) {
            $result = $array[$count - 1];
        } else {
            if (!is_null($predicate)) {
                throw new InvalidOperationException();
            }
            throw new InvalidOperationException('The source array is empty.');
        }

        return $result;
    }

    public function lastOrDefault(\Closure $predicate = null, $defaultValue = null)
    {
        $this->getWhere($predicate);
        $array = $this->getResult();
        $count = count($array);
        if ($count > 0) {
            $result = $array[$count - 1];
        } else {
            $result = $defaultValue;
        }

        return $result;
    }

    public function single(\Closure $predicate = null)
    {
        $this->getWhere($predicate);
        $array = $this->getResult();
        $count = count($array);
        if ($count > 0) {
            if ($count == 1) {
                return $array[0];
            } else {
                if (!is_null($predicate)) {
                    throw new InvalidOperationException('More than one element satisfies the condition.');
                }
                throw new InvalidOperationException('The input array contains more than one element.');
            }
        } else {
            if (!is_null($predicate)) {
                throw new InvalidOperationException();
            }
            throw new InvalidOperationException('The source array is empty.');
        }
    }

    public function singleOrDefault(\Closure $predicate = null, $defaultValue = null)
    {
        $this->getWhere($predicate);
        $array = $this->getResult();
        $count = count($array);
        if ($count > 0) {
            if ($count == 1) {
                return $array[0];
            } else {
                if (!is_null($predicate)) {
                    throw new InvalidOperationException('More than one element satisfies the condition.');
                }
                throw new InvalidOperationException('The input array contains more than one element.');
            }
        } else {
            return $defaultValue;
        }
    }

    public function elementAt($index)
    {
        $array = $this->getResult();
        $count = count($array);
        if ($count > 0) {
            if ($index > ($count - 1) || $index < 0) {
                throw new IndexOutOfRangeException();
            }
            $result = $array[$index];
        } else {
            throw new InvalidOperationException('The source array is empty.');
        }

        return $result;
    }

    public function elementAtOrDefault($index, $defaultValue = null)
    {
        $array = $this->getResult();
        $count = count($array);
        if ($index > ($count - 1) || $index < 0) {
            return $defaultValue;
        }

        return $array[$index];
    }

    public function indexOf($value, $start = null, $count = null)
    {
        $result = null;
        $array = $this->getResult(true);
        if (count($array) > 0) {
            $start = is_null($start) || $start < 0 ? 0 : $start;
            $count = is_null($count) ? count($array) : $count + $start;
            reset($array);
            for ($i = 0; $i < $count; $i++) {
                if ($i < $start) {
                    next($array);
                    continue;
                }
                $item = current($array);
                $key = key($array);
                if ($item === $value) {
                    $result = $key;
                    break;
                }
                next($array);
            }
        }

        return $result;
    }

    public function lastIndexOf($value, $start = null, $count = null)
    {
        $result = null;
        $keys = [];
        $array = $this->getResult(true);
        if (count($array) > 0) {
            $start = is_null($start) || $start < 0 ? 0 : $start;
            $count = is_null($count) ? count($array) : $count + $start;
            reset($array);
            for ($i = 0; $i < $count; $i++) {
                if ($i < $start) {
                    next($array);
                    continue;
                }
                $item = current($array);
                $key = key($array);
                if ($item === $value) {
                    $keys[] = $key;
                }
                next($array);
            }
            if (count($keys) > 0) {
                $result = end($keys);
            }
        }

        return $result;
    }

    // endregion

    // region Sequences

    /**
     * Determines whether an array contains a specified element by using a specified IComparer.
     *
     * @param ComparerParam|mixed $value The value to locate in the sequence.
     * @param IComparer $comparer An equality comparer to compare values.
     *
     * @return boolean
     */
    public function contains($value, IComparer $comparer = null)
    {
        $result = false;
        $array = $this->getResult(true);
        if (is_null($comparer)) {
            $comparer = new DefaultComparer();
        }
        foreach ($array as $key => $item) {
            if (!($value instanceof ComparerParam)) {
                $value = new ComparerParam($value, $key);
            }
            $equals = $comparer->equals(new ComparerParam($item, $key), $value);
            if ($equals) {
                $result = true;
                break;
            }
        }

        return $result;
    }

    /**
     * Determines whether any element of an array exists or satisfies a condition.
     *
     * @param \Closure $predicate A function to test each element for a condition.
     *
     * @return boolean
     */
    public function any(\Closure $predicate = null)
    {
        $array = $this->getResult(true);
        $result = false;
        if (is_null($predicate)) {
            $result = (count($array) > 0);
        } else {
            foreach ($array as $key => $item) {
                if ($predicate($item, $key)) {
                    $result = true;
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Determines whether all elements of an array satisfy a condition.
     *
     * @param \Closure $predicate A function to test each element for a condition.
     *
     * @throws ArgumentNullException Predicate is null.
     *
     * @return boolean
     */
    public function all(\Closure $predicate)
    {
        $array = $this->getResult(true);
        $result = true;
        if (is_null($predicate)) {
            throw new ArgumentNullException();
        } else {
            if (count($array) > 0) {
                foreach ($array as $key => $item) {
                    if (!$predicate($item, $key)) {
                        $result = false;
                        break;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Determines whether two arrays are equal by comparing their elements by using a specified IComparer.
     *
     * @param array $second An array to compare to the source array.
     * @param IComparer $comparer An equality comparer to compare values.
     *
     * @return boolean
     */
    public function sequenceEqual(array $second, IComparer $comparer = null)
    {
        $result = false;
        $first = $this->getResult(true);
        if ((count($first) == count($second)) && count($first) > 0 && count($second) > 0) {
            if (is_null($comparer)) {
                $comparer = new DefaultComparer();
            }
            reset($first);
            reset($second);
            $equals = true;
            foreach ($first as $xKey => $x) {
                $y = current($second);
                $yKey = key($second);
                $equals = $equals && $comparer->equals(new ComparerParam($x, $xKey), new ComparerParam($y, $yKey));
                next($second);
            }
            $result = $equals;
        }

        return $result;
    }

    // endregion

    // region Sorting

    public function orderBy(callable $keySelector)
    {
        if (!is_null($keySelector)) {
            $this->doSort($keySelector);
        }

        return $this;
    }

    public function orderByDescending(callable $keySelector)
    {
        if (!is_null($keySelector)) {
            $this->doSort($keySelector, true);
        }

        return $this;
    }

    // endregion

    // region Aggregation

    /**
     * Applies an accumulator function over an array.
     *
     * @param \Closure $func An accumulator function to be invoked on each element.
     *
     * @return mixed|null
     */
    public function aggregate(\Closure $func)
    {
        $result = null;
        $array = $this->getResult();
        if (count($array) > 0) {
            $accumulate = current($array);
            while (next($array)) {
                $accumulate = $func($accumulate, current($array));
            }
            $result = $accumulate;
        }

        return $result;
    }

    /**
     * Computes the average of an array.
     *
     * @return float|null
     */
    public function average()
    {
        $result = null;
        $array = $this->getResult();
        if (count($array) > 0) {
            $result = array_sum($array) / count($array);
        }

        return $result;
    }

    // endregion

    // region Conversion

    /**
     * @return array
     */
    public function toArray()
    {
        return $this->getResult();
    }

    /**
     * @deprecated deprecated since version 1.5.0
     *
     * @return array
     */
    public function toList()
    {
        return $this->getResult(true);
    }

    /**
     * @since Version 2.0.0
     *
     * @return array
     */
    public function toDictionary()
    {
        return $this->getResult(true);
    }

    // endregion

    // region Private Methods

    private function getWhere(callable $predicate)
    {
        $result = [];
        if (!is_null($predicate)) {
            foreach ($this->storage as $key => $value) {
                $predicateResult = $predicate($value, $key);
                if ($predicateResult) {
                    $result[$key] = $value;
                }
            }
            $this->storage = $result;
        }
    }

    private function doSort(callable $keySelector, $byDescending = false)
    {
        $result = [];
        $sortObjects = [];
        $items = $this->storage;
        foreach ($items as $key => $item) {
            $sortKey = $keySelector($item, $key);
            $this->validateSortKey($sortKey);
            $sortObjects[] = [$sortKey, $key];
        }
        $count = count($sortObjects);
        for ($i = 0; $i < $count; $i++) {
            for ($j = 1; $j < $count; $j++) {
                $compare = $sortObjects[$j][0] < $sortObjects[$j - 1][0];
                if ($byDescending) {
                    $compare = $sortObjects[$j][0] > $sortObjects[$j - 1][0];
                }
                if ($compare) {
                    $tempObj = $sortObjects[$j];
                    $sortObjects[$j] = $sortObjects[$j - 1];
                    $sortObjects[$j - 1] = $tempObj;
                }
            }
            $resultKey = $sortObjects[$i][1];
            $result[$resultKey] = $items[$resultKey];
        }
        $this->storage = $result;
    }

    private function validateSortKey($sortKey)
    {
        if (!is_scalar($sortKey)) {
            throw new InvalidOperationException('Can not be used as the sort key is not a scalar type');
        }
    }

    private function getResult($isDictionary = false)
    {
        $result = [];
        if (count($this->storage) > 0) {
            if (!is_null($this->isDictionary)) {
                $isDictionary = $this->isDictionary;
            }
            $result = $this->storage;
            if (!$isDictionary) {
                $result = array_values($result);
            }
        }
        $this->isDictionary = null;

        return $result;
    }

    /**
     * Calculate array rank
     *
     * @param array $array Source rank.
     * @param integer $rank Rank.
     *
     * @return float|integer
     */
    private function recursiveRank(array $array, $rank = 0)
    {
        $counter = 0;
        foreach ($array as $item) {
            if (is_array($item)) {
                $rank++;
                $rank = $this->recursiveRank($item, $rank);
            }
        }
        $count = count($array) > 0 ? count($array) : 1;
        $counter += ($rank / $count);

        return $counter;
    }

    /**
     * Property rank getter
     *
     * @return integer
     */
    protected function getRank()
    {
        $array = $this->getResult();
        if (count($array) > 0) {
            return intval(ceil($this->recursiveRank($array) + 1));
        } else {
            return 0;
        }
    }

    // endregion

    // region Magical

    /**
     * Magical method
     *
     * @param string $name Property name.
     *
     * @throws \Exception Undefined property referenced.
     *
     * @return mixed
     */
    public function __get($name)
    {
        $methodName = 'get' . $name;
        if (method_exists($this, $methodName)) {
            return $this->{$methodName}();
        } else {
            throw new \Exception('Undefined property "' . $name . '" referenced.');
        }
    }

    // endregion
}
