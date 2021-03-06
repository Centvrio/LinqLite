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
     * @access protected
     */
    protected $storage = [];

    /**
     * @var boolean|null
     * @access protected
     */
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
    /**
     * Filters a array of values based on a predicate.
     *
     * @param \Closure $predicate A function to test each element for a condition.
     *
     * @return LinqLite
     */
    public function where(\Closure $predicate)
    {
        $this->getWhere($predicate);

        return $this;
    }

    /**
     * Filters the elements of an array on a specified type.
     *
     * @param string $type Type string name
     *
     * @return LinqLite
     */
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
    /**
     * Projects each element of a array into a new form.
     *
     * @param \Closure $selector A transform function to apply to each element.
     *
     * @return LinqLite
     */
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

    /**
     * Correlates the elements of two arrays based on matching keys.
     *
     * @param array    $inner
     *
     * @param \Closure $outerKeySelector A function to extract the join key from each element of the source array.
     * @param \Closure $innerKeySelector A function to extract the join key from each element of the second array.
     * @param \Closure $resultSelector   A transform function to apply to each element.
     *
     * @return LinqLite
     */
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
    /**
     * Creates new array from source array according to a specified key selector function.
     *
     * @param \Closure $keySelector A function to extract a key from each element.
     *
     * @return LinqLite
     */
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

    /**
     * Groups the elements of an array according to a specified key selector function and projects the elements for
     * each group by using a specified function.
     *
     * @param \Closure $keySelector A function to extract the key for each element.
     * @param \Closure $selector    A transform function to apply to each element.
     *
     * @return LinqLite
     */
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

    /**
     * Correlates the elements of two arrays based on key equality, and groups the results.
     *
     * @param array    $inner            The sequence to join to the source array.
     * @param \Closure $outerKeySelector A function to extract the join key from each element of the source array.
     * @param \Closure $innerKeySelector A function to extract the join key from each element of the second array.
     * @param \Closure $resultSelector   A transform function to apply to each element.
     *
     * @return LinqLite
     */
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

    /**
     * Merges two arrays by using the specified predicate function.
     *
     * @param array    $second         The second array to merge.
     * @param \Closure $resultSelector A function that specifies how to merge the elements from the two arrays.
     *
     * @throws ArgumentNullException
     * Second is null.
     *
     * @return LinqLite
     */
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
    /**
     * Returns the first element of an array.
     *
     * @param \Closure $predicate A function to test each element for a condition.
     *
     * @throws InvalidOperationException The source array is empty.
     *
     * @return mixed
     */
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

    /**
     * Returns the first element of an array, or a default value if no element is found.
     *
     * @param \Closure $predicate    A function to test each element for a condition.
     * @param mixed    $defaultValue Default value.
     *
     * @return mixed
     */
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

    /**
     * Returns the last element of an array.
     *
     * @param \Closure $predicate A function to test each element for a condition.
     *
     * @throws InvalidOperationException The source array is empty.
     *
     * @return mixed
     */
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

    /**
     * Returns the last element of an array, or a default value if no element is found.
     *
     * @param \Closure $predicate    A function to test each element for a condition.
     * @param mixed    $defaultValue Default value.
     *
     * @return mixed
     */
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

    /**
     * Returns a single, specific element of an array.
     *
     * @param \Closure $predicate A function to test each element for a condition.
     *
     * @throws InvalidOperationException More than one element satisfies the condition.
     *                                   The input array contains more than one element.
     *
     * @return mixed
     */
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

    /**
     * Returns a single, specific element of an array, or a default value if that element is not found.
     *
     * @param \Closure|null $predicate    A function to test each element for a condition.
     * @param mixed         $defaultValue Default value.
     *
     * @throws InvalidOperationException
     *
     * @return mixed
     */
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

    /**
     * Returns the element at a specified index in an array.
     *
     * @param integer $index The zero-based index of the element to retrieve.
     *
     * @throws IndexOutOfRangeException Index is less than 0 or greater than or equal to the number of elements in
     *                                  array.
     * @throws InvalidOperationException The source array is empty.
     *
     * @return mixed
     */
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

    /**
     * Returns the element at a specified index in an array or a default value if the index is out of range.
     *
     * @param integer $index        The zero-based index of the element to retrieve.
     * @param mixed   $defaultValue Default value.
     *
     * @return mixed
     */
    public function elementAtOrDefault($index, $defaultValue = null)
    {
        $array = $this->getResult();
        $count = count($array);
        if ($index > ($count - 1) || $index < 0) {
            return $defaultValue;
        }

        return $array[$index];
    }

    /**
     * Searches for the specified object and returns the key of the first occurrence within the range of elements in
     * the array that starts at the specified index and contains the specified number of elements.
     *
     * @param mixed        $value Value to locate in the array.
     * @param integer|null $start Starting position of the search.
     * @param integer|null $count The number of elements within a range in which to search.
     *
     * @return integer|null|string
     */
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

    /**
     * Searches for the specified object and returns the key of the last occurrence within the range of elements in the
     * array that starts at the specified index and contains the specified number of elements.
     *
     * @param mixed        $value Value to locate in the array.
     * @param integer|null $start Starting position of the search.
     * @param integer|null $count The number of elements within a range in which to search.
     *
     * @return integer|null|string
     */
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

    /**
     * Returns a specified number of contiguous elements from the start of an array.
     *
     * @param integer $count The number of elements to return.
     *
     * @throws ArgumentException The count of element is not numeric.
     *
     * @return LinqLite
     */
    public function take($count)
    {
        return $this->getSlice($count);
    }

    /**
     * Bypasses a specified number of elements in an array and then returns the remaining elements.
     *
     * @param integer $count The number of elements to skip before returning the remaining elements.
     *
     * @throws ArgumentException The count of element is not numeric.
     *
     * @return LinqLite
     */
    public function skip($count)
    {
        return $this->getSlice($count, true);
    }
    // endregion

    // region Sequences

    /**
     * Determines whether an array contains a specified element by using a specified IComparer.
     *
     * @param ComparerParam|mixed $value    The value to locate in the sequence.
     * @param IComparer           $comparer An equality comparer to compare values.
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
     * @param array     $second   An array to compare to the source array.
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
            foreach ($first as $xKey => $xValue) {
                $yValue = current($second);
                $yKey = key($second);
                $equals = $equals && $comparer->equals(new ComparerParam($xValue, $xKey), new ComparerParam($yValue, $yKey));
                next($second);
            }
            $result = $equals;
        }

        return $result;
    }


    /**
     * Returns a number that represents how many elements in the array satisfy a condition.
     *
     * @param \Closure $predicate A function to test each element for a condition.
     *
     * @return integer
     */
    public function count(\Closure $predicate = null)
    {
        $array = $this->getResult(true);
        $result = count($array);
        if (!is_null($predicate)) {
            $this->getWhere($predicate);
            $result = count($this->storage);
        }

        return $result;
    }

    // endregion

    // region Sorting

    /**
     * Sorts the elements of an array in ascending order.
     *
     * @param \Closure $keySelector A function to extract a key from an element.
     *
     * @return LinqLite
     * @experimental
     */
    public function orderBy(\Closure $keySelector)
    {
        if (!is_null($keySelector)) {
            $this->doSort($keySelector);
        }

        return $this;
    }

    /**
     * Sorts the elements of an array in descending order.
     *
     * @param \Closure $keySelector A function to extract a key from an element.
     *
     * @return LinqLite
     * @experimental
     */
    public function orderByDescending(\Closure $keySelector)
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
     * @since Version 1.4.7
     *
     * @return array
     */
    public function toDictionary()
    {
        return $this->getResult(true);
    }

    // endregion

    // region Private Methods

    private function getWhere(\Closure $predicate = null)
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

    private function doSort(\Closure $keySelector, $byDescending = false)
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

        for ($oldIndex = 1; $oldIndex < $count; $oldIndex++) {
            $key = $sortObjects[$oldIndex];
            $newIndex = $oldIndex;
            if ($byDescending) {
                while ($newIndex > 0 && $sortObjects[$newIndex - 1][0] < $key[0]) {
                    $sortObjects[$newIndex] = $sortObjects[$newIndex - 1];
                    $newIndex--;
                }
            } else {
                while ($newIndex > 0 && $sortObjects[$newIndex - 1][0] > $key[0]) {
                    $sortObjects[$newIndex] = $sortObjects[$newIndex - 1];
                    $newIndex--;
                }
            }
            $sortObjects[$newIndex] = $key;
        }
        for ($index = 0; $index < $count; $index++) {
            $resultKey = $sortObjects[$index][1];
            $result[$resultKey] = $items[$resultKey];
        }
        $this->storage = $result;
    }

    private function getSlice($count, $isSkip = false)
    {
        $array = $this->getResult(true);
        if (is_numeric($count)) {
            $count = intval($count);
            $arrayCount = count($array);
            if ($arrayCount < $count) {
                $count = count($array);
            }
            if ($arrayCount > 0) {
                if ($isSkip) {
                    $this->storage = array_slice($array, $count, null, true);
                } else {
                    $this->storage = array_slice($array, 0, $count, true);
                }
            }
        } else {
            throw new ArgumentException('Argument must be numeric.');
        }
        return $this;
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
     * @param array   $array Source rank.
     * @param integer $rank  Rank.
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
