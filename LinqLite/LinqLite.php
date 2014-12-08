<?php

namespace LinqLite;

use LinqLite\Comparer\ComparerParam;
use LinqLite\Comparer\DefaultComparer;
use LinqLite\Comparer\IComparer;
use LinqLite\Exception\ArgumentException;
use LinqLite\Exception\ArgumentNullException;
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
    private $containsCount = 0;

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
            if ($this->containsCount > 0) {
                $result = true;
                $this->containsCount = 0;
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
            if ($this->containsCount != count($array)) {
                $result = false;
                $this->containsCount = 0;
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
        if ($this->containsCount > 0) {
            $result = true;
            $this->containsCount = 0;
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
        if ($this->containsCount == count($array)) {
            $result = true;
            $this->containsCount = 0;
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
        if (count($this->expressions) > 0 && count($this->storage) > 0) {
            $iterator = new LinqIterator($this->storage, $this->expressions);
            while ($iterator->valid()) {
                $key = $iterator->key();
                $value = $iterator->current();
                if (!$value->filtered) {
                    if ($isDictionary) {
                        $result[$key] = $value->value;
                    } else {
                        $result[] = $value->value;
                    }
                    $this->containsCount += $value->containsCounter;
                }
                $iterator->next();
            }
        }
        return $result;
    }

    // endregion
}