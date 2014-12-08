<?php
namespace LinqLite;

use LinqLite\Comparer\DefaultComparer;
use LinqLite\Exception\ArgumentNullException;
use LinqLite\Exception\IndexOutOfRangeException;
use LinqLite\Exception\InvalidOperationException;
use LinqLite\Comparer\IComparer;
use LinqLite\Comparer\ComparerParam;

/**
 * Class Linq
 *
 * @version       1.4.6
 * @package       Linq
 * @property-read integer $rank Returns array rank
 */
class LinqLite2
{
    /**
     * Inner source array
     * @var array
     * @access protected
     */
    protected $inputArray = [];
    /**
     * Inner predicates collection
     * @var \Closure[]
     * @access protected
     */
    protected $predicates = [];

    /**
     * Set source array
     *
     * @param array $source Source array.
     *
     * @return LinqLite
     */
    public static function from(array $source)
    {
        $instance = new self();
        $instance->inputArray = $source;
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
        if (!is_null($predicate)) {
            $this->predicates[] = $predicate;
        }
        return $this;
    }

    /**
     * Filters the elements of an array on a specified type.
     *
     * @param string $type Type string name.
     *
     * @return LinqLite
     */
    public function ofType($type)
    {
        $this->predicates[] = function ($item) use ($type) {
            return $this->validateType($item, $type);
        };
        return $this;
    }

    // endregion Projection, filtering

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
            $this->predicates[] = $selector;
        }
        return $this;
    }

    // endregion

    // region Joining

    /**
     * Correlates the elements of two arrays based on matching keys.
     *
     * @param array    $inner          The sequence to join to the source array.
     * @param \Closure $outerSelector  A function to extract the join key from each element of the source array.
     * @param \Closure $innerSelector  A function to extract the join key from each element of the second array.
     * @param \Closure $resultSelector A transform function to apply to each element.
     *
     * @return array
     */
    public function join(array $inner, \Closure $outerSelector, \Closure $innerSelector, \Closure $resultSelector)
    {
        $result = [];
        $outer = $this->predicateCalculate(true);
        if (count($outer) > 0 && count($inner) > 0) {
            foreach ($outer as $outerKey => $outerItem) {
                $resultKey = $outerSelector($outerItem, $outerKey);
                foreach ($inner as $innerKey => $innerItem) {
                    if ($resultKey == $innerSelector($innerItem, $innerKey)) {
                        $result[][$resultKey] = $resultSelector($outerItem, $innerItem);
                    }
                }
            }
        }
        return $result;
    }

    // endregion

    // region Grouping

    /**
     * Creates new array from source array according to a specified key selector function.
     *
     * @param \Closure $keySelector A function to extract a key from each element.
     *
     * @return array
     */
    public function toLookup(\Closure $keySelector)
    {
        $items = $this->predicateCalculate(true);
        $result = $items;
        if (!is_null($keySelector) && count($items) > 0) {
            $lookup = [];
            foreach ($items as $key => $item) {
                $newKey = $keySelector($item, $key);
                $lookup[$newKey][] = $item;
            }
            $result = $lookup;
        }
        return $result;
    }

    /**
     * Groups the elements of an array according to a specified key selector function and projects the elements for each group by using a specified function.
     *
     * @param \Closure $keySelector A function to extract the key for each element.
     * @param \Closure $selector    A transform function to apply to each element.
     *
     * @return array
     */
    public function groupBy(\Closure $keySelector, \Closure $selector)
    {
        $items = $this->predicateCalculate(true);
        $result = $items;
        if (!is_null($keySelector) && !is_null($selector) && count($items) > 0) {
            $grouped = [];
            foreach ($items as $key => $item) {
                $newKey = $keySelector($item, $key);
                $grouped[$newKey][] = $selector($item, $key);
            }
            $result = $grouped;
        }
        return $result;
    }

    /**
     * Correlates the elements of two arrays based on key equality, and groups the results.
     *
     * @param array    $inner          The sequence to join to the source array.
     * @param \Closure $outerSelector  A function to extract the join key from each element of the source array.
     * @param \Closure $innerSelector  A function to extract the join key from each element of the second array.
     * @param \Closure $resultSelector A transform function to apply to each element.
     *
     * @return array
     */
    public function groupJoin(array $inner, \Closure $outerSelector, \Closure $innerSelector, \Closure $resultSelector)
    {
        $result = [];
        $outer = $this->predicateCalculate(true);
        if (count($outer) > 0 && count($inner) > 0) {
            foreach ($outer as $outerKey => $outerItem) {
                $resultKey = $outerSelector($outerItem, $outerKey);
                $innerTemp = [];
                foreach ($inner as $innerKey => $innerItem) {
                    if ($resultKey == $innerSelector($innerItem, $innerKey)) {
                        $innerTemp[$innerKey] = $innerItem;
                    }
                }
                $result[$resultKey] = $resultSelector($outerItem, $innerTemp);
            }
        }
        return $result;
    }

    /**
     * Merges two arrays by using the specified predicate function.
     *
     * @param array    $second         The second array to merge.
     * @param \Closure $resultSelector A function that specifies how to merge the elements from the two arrays.
     *
     * @throws ArgumentNullException Second is null.
     *
     * @return array
     */
    public function zip(array $second, \Closure $resultSelector)
    {
        if (is_null($resultSelector)) {
            throw new ArgumentNullException();
        }
        $result = [];
        $first = $this->predicateCalculate(true);
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
        }
        return $result;
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
        if (!is_null($predicate)) {
            $this->predicates[] = $predicate;
        }
        $items = $this->predicateCalculate();
        if (count($items) > 0) {
            reset($items);
            $item = current($items);
        } else {
            if (!is_null($predicate)) {
                throw new InvalidOperationException();
            }
            throw new InvalidOperationException('The source array is empty.');
        }
        return $item;
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
        if (!is_null($predicate)) {
            $this->predicates[] = $predicate;
        }
        $items = $this->predicateCalculate();
        if (count($items) > 0) {
            reset($items);
            $item = end($items);
        } else {
            if (!is_null($predicate)) {
                throw new InvalidOperationException();
            }
            throw new InvalidOperationException('The source array is empty.');
        }
        return $item;
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
        if (!is_null($predicate)) {
            $this->predicates[] = $predicate;
        }
        $items = $this->predicateCalculate();
        if (count($items) > 0) {
            if (count($items) == 1) {
                return array_values($items)[0];
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
     * Returns the element at a specified index in an array.
     *
     * @param integer $index The zero-based index of the element to retrieve.
     *
     * @throws IndexOutOfRangeException Index is less than 0 or greater than or equal to the number of elements in array.
     * @throws InvalidOperationException The source array is empty.
     *
     * @return mixed
     */
    public function elementAt($index)
    {
        $array = $this->predicateCalculate();
        if (count($array) > 0) {
            $array = array_values($array);
            if ($index > (count($array) - 1)) {
                throw new IndexOutOfRangeException();
            }
            $result = $array[$index];
        } else {
            throw new InvalidOperationException('The source array is empty.');
        }
        return $result;
    }

    /**
     * Searches for the specified object and returns the key of the first occurrence within the range of elements in the array that starts at the specified index and contains the specified number of elements.
     *
     * @param mixed        $value Value to locate in the array.
     * @param integer|null $start Starting position of the search.
     * @param integer|null $count The number of elements within a range in which to search.
     *
     * @return int|null|string
     */
    public function indexOf($value, $start = null, $count = null)
    {
        $result = null;
        $array = $this->predicateCalculate();
        if (count($array) > 0) {
            $start = is_null($start) || $start < 0 ? 0 : $start;
            $count = is_null($count) ? 0 : $count + $start;
            $count = is_null($count) || $count == 0 ? count($array) : $count;
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
     * Searches for the specified object and returns the key of the last occurrence within the range of elements in the array that starts at the specified index and contains the specified number of elements.
     *
     * @param mixed        $value Value to locate in the array.
     * @param integer|null $start Starting position of the search.
     * @param integer|null $count The number of elements within a range in which to search.
     *
     * @return int|null|string
     */
    public function lastIndexOf($value, $start = null, $count = null)
    {
        $result = null;
        $keys = [];
        $array = $this->predicateCalculate();
        if (count($array) > 0) {
            $start = is_null($start) || $start < 0 ? 0 : $start;
            $count = is_null($count) ? 0 : $count + $start;
            $count = is_null($count) || $count == 0 ? count($array) : $count;
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
     * @param ComparerParam|mixed $value    The value to locate in the sequence.
     * @param IComparer           $comparer An equality comparer to compare values.
     *
     * @return boolean
     */
    public function contains($value, IComparer $comparer = null)
    {
        $result = false;
        $array = $this->predicateCalculate();
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
        $array = $this->predicateCalculate();
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
        $array = $this->predicateCalculate();
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
        $first = $this->predicateCalculate();
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

    // region Conversion

    /**
     * Returns an array of processed
     *
     * @return array
     */
    public function toArray()
    {
        return $this->predicateCalculate();
    }

    /**
     * Returns an associative array of processed
     *
     * @return array
     */
    public function toList()
    {
        return $this->predicateCalculate(true);
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
        $array = $this->predicateCalculate();
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
        $array = $this->predicateCalculate();
        if (count($array) > 0) {
            $result = array_sum($array) / count($array);
        }
        return $result;
    }

    // endregion

    // region Private methods

    /**
     * Calculate all predicates
     *
     * @param boolean $isList Is true create associative array.
     *
     * @return array
     */
    private function predicateCalculate($isList = false)
    {
        $result = [];
        if (count($this->inputArray) > 0) {
            foreach ($this->inputArray as $key => $item) {
                $predicate = true;
                if (count($this->predicates) > 0) {
                    foreach ($this->predicates as $callback) {
                        $callResult = $callback($item, $key);
                        if (!is_bool($callResult)) {
                            if (!is_null($callResult)) {
                                $item = $callResult;
                            }
                            $callResult = true;
                        }
                        $predicate = $predicate && $callResult;
                    }
                }
                if ($predicate) {
                    if ($isList) {
                        $result[$key] = $item;
                    } else {
                        $result[] = $item;
                    }
                }
            }
        }
        return $result;
    }

    /**
     * Validate variable type
     *
     * @param mixed  $value Source variable.
     * @param string $type  String type name.
     *
     * @return boolean
     */
    private function validateType($value, $type)
    {
        if (is_object($value)) {
            return $value instanceof $type;
        } else {
            return gettype($value) === $type;
        }
    }

    /**
     * Calculate array rank
     *
     * @param array   $array Source rank.
     * @param integer $rank  Rank.
     *
     * @return float|int
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
        $array = $this->predicateCalculate();
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
