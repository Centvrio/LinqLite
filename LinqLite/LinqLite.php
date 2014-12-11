<?php

namespace LinqLite;

use LinqLite\Comparer\ComparerParam;
use LinqLite\Comparer\DefaultComparer;
use LinqLite\Comparer\IComparer;
use LinqLite\Exception\ArgumentException;
use LinqLite\Exception\ArgumentNullException;
use LinqLite\Exception\IndexOutOfRangeException;
use LinqLite\Exception\InvalidOperationException;
use LinqLite\Expression\LinqExpression;
use LinqLite\Iterator\LinqIterator;

/**
 * Class LinqLite
 *
 * @version 2.0.0
 * @package LinqLite
 * @property-read integer $rank Returns array rank
 */
class LinqLite
{
    /**
     * @var array
     */
    protected $storage = [];

    /**
     * @var LinqExpression[]
     */
    protected $expressions = [];


    /**
     * @var integer
     */
    private $contains = 0;
    private $aggregateResult = null;
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
        $instance = new self();
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

    public function where(\Closure $predicate)
    {
        if (!is_null($predicate)) {
            $expression = new LinqExpression();
            $expression->closure = $predicate;
            $expression->return = LinqExpression::FILTERING;
            $this->expressions[] = $expression;
        }
        return $this;
    }

    public function ofType($type)
    {
        $expression = new LinqExpression();
        $expression->closure = function ($value) use ($type) {
            if (is_object($value)) {
                return $value instanceof $type;
            } else {
                return gettype($value) === $type;
            }
        };
        $expression->return = LinqExpression::FILTERING;
        $this->expressions[] = $expression;
        return $this;
    }

    // endregion

    // region Projection

    public function select(\Closure $selector)
    {
        if (!is_null($selector)) {
            $expression = new LinqExpression();
            $expression->closure = $selector;
            $expression->return = LinqExpression::PROJECTION;
            $this->expressions[] = $expression;
        }
        return $this;
    }

    // endregion

    // region Joining

    public function join(array $inner, \Closure $outerKeySelector, \Closure $innerKeySelector, \Closure $resultSelector)
    {
        if (!is_null($outerKeySelector) && !is_null($innerKeySelector) && !is_null($resultSelector) && count($inner) > 0) {
            $expression = new LinqExpression();
            $expression->closure[] = $outerKeySelector;
            $expression->closure[] = $innerKeySelector;
            $expression->closure[] = $resultSelector;
            $expression->params[] = $inner;
            $expression->return = LinqExpression::JOINING;
            $this->expressions[] = $expression;
        }
        $this->isDictionary = false;
        return $this;
    }

    // endregion

    // region Grouping

    public function toLookup(\Closure $keySelector)
    {
        if (!is_null($keySelector)) {
            $expression = new LinqExpression();
            $expression->closure = $keySelector;
            $expression->return = LinqExpression::LOOKUP;
            $this->expressions[] = $expression;
        }
        $this->isDictionary = true;
        return $this;
    }

    public function groupBy(\Closure $keySelector, \Closure $selector)
    {
        if (!is_null($keySelector) && !is_null($selector)) {
            $expression = new LinqExpression();
            $expression->closure[] = $keySelector;
            $expression->closure[] = $selector;
            $expression->return = LinqExpression::GROUPING;
            $this->expressions[] = $expression;
        }
        $this->isDictionary = true;
        return $this;
    }

    public function groupJoin(array $inner, \Closure $outerKeySelector, \Closure $innerKeySelector, \Closure $resultSelector)
    {
        if (!is_null($outerKeySelector) && !is_null($innerKeySelector) && !is_null($resultSelector) && count($inner) > 0) {
            $expression = new LinqExpression();
            $expression->closure[] = $outerKeySelector;
            $expression->closure[] = $innerKeySelector;
            $expression->closure[] = $resultSelector;
            $expression->params[] = $inner;
            $expression->return = LinqExpression::JOINING | LinqExpression::GROUPING;
            $this->expressions[] = $expression;
        }
        $this->isDictionary = true;
        return $this;
    }

    public function zip(array $second, \Closure $resultSelector)
    {
        if (is_null($resultSelector)) {
            throw new ArgumentNullException();
        }
        $expression = new LinqExpression();
        $expression->closure = $resultSelector;
        $expression->return = LinqExpression::ZIP;
        $expression->params[0] = new \ArrayObject($second);
        $this->expressions[] = $expression;
        return $this;
    }

    // endregion

    // region Pagination

    public function first(\Closure $predicate = null)
    {
        if (!is_null($predicate)) {
            $expression = new LinqExpression();
            $expression->closure = $predicate;
            $expression->return = LinqExpression::FILTERING;
            $this->expressions[] = $expression;
        }
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
        if (!is_null($predicate)) {
            $expression = new LinqExpression();
            $expression->closure = $predicate;
            $expression->return = LinqExpression::FILTERING;
            $this->expressions[] = $expression;
        }
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
        if (!is_null($predicate)) {
            $expression = new LinqExpression();
            $expression->closure = $predicate;
            $expression->return = LinqExpression::FILTERING;
            $this->expressions[] = $expression;
        }
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
        if (!is_null($predicate)) {
            $expression = new LinqExpression();
            $expression->closure = $predicate;
            $expression->return = LinqExpression::FILTERING;
            $this->expressions[] = $expression;
        }
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
        if (!is_null($predicate)) {
            $expression = new LinqExpression();
            $expression->closure = $predicate;
            $expression->return = LinqExpression::FILTERING;
            $this->expressions[] = $expression;
        }
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
        if (!is_null($predicate)) {
            $expression = new LinqExpression();
            $expression->closure = $predicate;
            $expression->return = LinqExpression::FILTERING;
            $this->expressions[] = $expression;
        }
        $array = $this->getResult();
        $count = count($array);
        if ($count > 0) {
            return $array[0];
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
        $expression = new LinqExpression();
        $expression->closure = function ($v, $i, $c) use ($value, $start, $count) {
            $start = is_null($start) || $start < 0 ? 0 : $start;
            $count = is_null($count) || $count < 0 ? true : ($c <= $count);
            return $v === $value && $i >= ($start - 1) && $count;
        };
        $expression->return = LinqExpression::SEARCHES;
        $this->expressions[] = $expression;
        $array = $this->getResult(true);
        $result = key($array);
        return $result;
    }

    public function lastIndexOf($value, $start = null, $count = null)
    {
        $result = null;
        $expression = new LinqExpression();
        $expression->closure = function ($v, $i, $c) use ($value, $start, $count) {
            $start = is_null($start) || $start < 0 ? 0 : $start;
            $count = is_null($count) || $count < 0 ? true : ($c <= $count);
            return $v === $value && $i >= ($start - 1) && $count;
        };
        $expression->return = LinqExpression::SEARCHES;
        $this->expressions[] = $expression;
        $array = $this->getResult(true);
        end($array);
        $result = key($array);
        return $result;
    }

    // endregion

    // region Sequences

    public function any(\Closure $predicate = null)
    {
        $result = false;
        if (!is_null($predicate)) {
            $expression = new LinqExpression();
            $expression->closure = $predicate;
            $expression->return = LinqExpression::SEQUENCES;
            $this->expressions[] = $expression;
            $this->getResult();
            if ($this->contains > 0) {
                $result = true;
                $this->contains = 0;
            }
        }
        return $result;
    }

    public function all(\Closure $predicate)
    {
        $result = true;
        if (is_null($predicate)) {
            throw new ArgumentNullException();
        } else {
            $expression = new LinqExpression();
            $expression->closure = $predicate;
            $expression->return = LinqExpression::SEQUENCES;
            $this->expressions[] = $expression;
            $array = $this->getResult();
            if ($this->contains != count($array)) {
                $result = false;
                $this->contains = 0;
            }
        }
        return $result;
    }

    public function contains($value, IComparer $comparer = null)
    {
        $result = false;
        if (is_null($comparer)) {
            $comparer = new DefaultComparer();
        }
        $expression = new LinqExpression();
        $expression->closure = function ($item, $key) use ($value, $comparer) {
            if (!($value instanceof ComparerParam)) {
                $value = new ComparerParam($value, $key);
            }
            return $comparer->equals(new ComparerParam($item, $key), $value);
        };
        $expression->return = LinqExpression::SEQUENCES;
        $this->expressions[] = $expression;
        $this->getResult();
        if ($this->contains > 0) {
            $result = true;
            $this->contains = 0;
        }
        return $result;
    }


    public function sequenceEqual(array $second, IComparer $comparer = null)
    {
        $result = false;
        if (is_null($comparer)) {
            $comparer = new DefaultComparer();
        }
        reset($second);
        $expression = new LinqExpression();
        $expression->closure = function ($x, $xKey) use (&$second, $comparer) {
            $y = current($second);
            $yKey = key($second);
            $equals = $comparer->equals(new ComparerParam($x, $xKey), new ComparerParam($y, $yKey));
            next($second);
            return $equals;
        };
        $expression->return = LinqExpression::SEQUENCES;
        $this->expressions[] = $expression;
        $array = $this->getResult();
        if ($this->contains == count($array)) {
            $result = true;
            $this->contains = 0;
        }
        return $result;
    }

    // endregion

    // region Aggregation

    public function aggregate(\Closure $func)
    {
        $expression = new LinqExpression();
        $expression->closure = $func;
        $expression->return = LinqExpression::AGGREGATION;
        $this->expressions[] = $expression;
        $this->getResult();
        return $this->aggregateResult;
    }

    public function average()
    {
        $result = null;
        $expression = new LinqExpression();
        $expression->closure = function ($value) {
            return is_numeric($value);
        };
        $expression->return = LinqExpression::FILTERING;
        $this->expressions[] = $expression;
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
     * @deprecated deprecated since version 2.0.0
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

    private function getResult($isDictionary = false)
    {
        $result = [];
        if (count($this->storage) > 0) {
            $iterator = new LinqIterator($this->storage, $this->expressions);
            $value = null;
            if (!is_null($this->isDictionary)) {
                $isDictionary = $this->isDictionary;
            }
            while ($iterator->valid()) {
                $value = $iterator->current();
                if (!$value->filtered) {
                    if ($isDictionary) {
                        if ($value->isGrouping) {
                            $result[$value->key][] = $value->value;
                        } else {
                            $result[$value->key] = $value->value;
                        }
                    } else {
                        if ($value->isJoining) {
                            $result = array_merge($value->value, $result);
                        } else {
                            $result[] = $value->value;
                        }
                    }
                    $this->contains += abs(intval($value->contains));
                    $this->aggregateResult = $value->aggregate;
                }
                $iterator->next();
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
