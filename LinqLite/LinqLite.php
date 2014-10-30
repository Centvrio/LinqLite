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
 * @version 1.4.1
 * @package Linq
 * @property-read integer $rank
 */
class LinqLite
{
    /**
     * @var array
     */
    protected $inputArray = [];
    /**
     * @var \Closure[]
     */
    protected $predicates = [];

    /**
     * Set array
     *
     * @param array $array
     * @return LinqLite
     */
    public static function from(array $array)
    {
        $instance = new self();
        $instance->inputArray = $array;
        return $instance;
    }

    /**
     * Filters a array of values based on a predicate.
     *
     * @param \Closure $callback
     * @return LinqLite
     */
    public function where(\Closure $callback)
    {
        if (!is_null($callback)) {
            $this->predicates[] = $callback;
        }
        return $this;
    }

    /**
     * Projects each element of a array into a new form.
     *
     * @param \Closure $selector
     * @return LinqLite
     */
    public function select(\Closure $selector)
    {
        if (!is_null($selector)) {
            $this->predicates[] = $selector;
        }
        return $this;
    }

    /**
     * Returns the first element of an array.
     *
     * @param \Closure $predicate
     * @return mixed
     * @throws InvalidOperationException
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
            throw new InvalidOperationException("The source array is empty.");
        }
        return $item;
    }

    /**
     * Returns the last element of an array.
     *
     * @param \Closure $predicate
     * @return mixed
     * @throws InvalidOperationException
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
            throw new InvalidOperationException("The source array is empty.");
        }
        return $item;
    }

    /**
     * Returns a single, specific element of an array.
     *
     * @param \Closure $predicate
     * @return mixed
     * @throws InvalidOperationException
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
                    throw new InvalidOperationException("More than one element satisfies the condition.");
                }
                throw new InvalidOperationException("The input array contains more than one element.");
            }
        } else {
            if (!is_null($predicate)) {
                throw new InvalidOperationException();
            }
            throw new InvalidOperationException("The source array is empty.");
        }
    }

    /**
     * Filters the elements of an array on a specified type.
     *
     * @param string $type
     * @return LinqLite
     */
    public function ofType($type)
    {
        $this->predicates[] = function ($item) use ($type) {
            return $this->validateType($item, $type);
        };
        return $this;
    }

    /**
     * Returns the element at a specified index in an array.
     *
     * @param integer $index
     * @return mixed
     * @throws IndexOutOfRangeException
     * @throws InvalidOperationException
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
            throw new InvalidOperationException("The source array is empty.");
        }
        return $result;
    }

    /**
     * Determines whether an array contains a specified element by using a specified IComparer.
     *
     * @param ComparerParam|mixed $value
     * @param IComparer $comparer
     * @return bool
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
     * @param \Closure $predicate
     * @return bool
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
     * @param \Closure $predicate
     * @return bool
     * @throws ArgumentNullException
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
     * Return result array
     *
     * @return array
     */
    public function toArray()
    {
        return $this->predicateCalculate();
    }

    /**
     * Determines whether two arrays are equal by comparing their elements by using a specified IComparer.
     *
     * @param array $second
     * @param IComparer $comparer
     * @return bool
     */
    public function sequenceEqual(array $second, IComparer $comparer = null)
    {
        $result = false;
        $first = $this->predicateCalculate();
        if ((count($first) == count($second)) && count($first) > 0 && count($second) > 0) {
            if (!is_null($comparer)) {
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
            } else {
                $result = ($first === $second);
            }
        }
        return $result;
    }

    /**
     * @param \Closure $keySelector
     * @return array
     */
    public function toLookup(\Closure $keySelector)
    {
        $items = $this->predicateCalculate();
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
     * @param \Closure $keySelector
     * @param \Closure $selector
     * @return array
     */
    public function groupBy(\Closure $keySelector, \Closure $selector)
    {
        $items = $this->predicateCalculate();
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
     * @param array $inner
     * @param \Closure $outerSelector
     * @param \Closure $innerSelector
     * @param \Closure $resultSelector
     * @return array
     */
    public function groupJoin(array $inner, \Closure $outerSelector, \Closure $innerSelector, \Closure $resultSelector)
    {
        $result = [];
        $outer = $this->predicateCalculate();
        if (count($outer) > 0) {
            foreach ($outer as $outerKey => $outerItem) {
                $resultKey = $outerSelector($outerItem, $outerKey);
                if (count($inner) > 0) {
                    foreach ($inner as $innerKey => $innerItem) {
                        if ($resultKey == $innerSelector($innerItem, $innerKey)) {
                            $result[$resultKey][] = $resultSelector($innerItem, $innerKey);
                        }
                    }
                }
                if (empty($result[$resultKey])) {
                    $result[$resultKey] = [];
                }
            }
        }
        return $result;
    }

    /**
     * Merges two arrays by using the specified predicate function.
     *
     * @param array $second
     * @param callable $resultSelector
     * @return array
     * @throws ArgumentNullException
     */
    public function zip(array $second, \Closure $resultSelector)
    {
        if (is_null($resultSelector)) {
            throw new ArgumentNullException();
        }
        $result = [];
        $first = $this->predicateCalculate();
        reset($first);
        reset($second);
        $countFirst = count($first);
        $countSecond = count($second);
        $count = $countFirst != $countSecond ? ($countFirst < $countSecond ? $countFirst : $countSecond) : $countFirst;
        if ($count>0) {
            for($i=0;$i<$count;$i++) {
                $firstItem=current($first);
                $secondItem=current($second);
                $result[]=$resultSelector($firstItem,$secondItem);
                next($first);
                next($second);
            }
        }
        return $result;
    }

    /**
     * @return array
     */
    private function predicateCalculate()
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
                    $result[$key] = $item;
                }
            }
        }
        return $result;
    }

    /**
     * @param mixed $value
     * @param string $type
     * @return bool
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
     * @param array $array
     * @param integer $rank
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
        $counter += ($rank / count($array));
        return $counter;
    }

    /**
     * @return integer
     */
    private function getRank()
    {
        $array = $this->predicateCalculate();
        if (count($array) > 0) {
            return intval(ceil($this->recursiveRank($array) + 1));
        } else {
            return 0;
        }
    }

    public function __get($name)
    {
        $methodName = 'get' . $name;
        if (method_exists($this, $methodName)) {
            return $this->{$methodName}();
        } else {
            throw new \Exception('Undefined property "' . $name . '" referenced.');
        }
    }
}