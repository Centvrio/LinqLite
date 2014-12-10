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
        $result=key($array);
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
        $result=key($array);
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
            while ($iterator->valid()) {
                $key = $iterator->key();
                $value = $iterator->current();
                if (!$value->filtered) {
                    if ($isDictionary) {
                        $result[$key] = $value->value;
                    } else {
                        $result[] = $value->value;
                    }
                    $this->contains += abs(intval($value->contains));
                    $this->aggregateResult = $value->aggregate;
                }
                $iterator->next();
            }
        }
        return $result;
    }

    // endregion
}