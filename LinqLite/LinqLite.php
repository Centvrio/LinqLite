<?php

namespace LinqLite;

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
    protected $storage = null;

    /**
     * @var LinqExpression[]
     */
    protected $expressions = [];
    private $sequences = 0;

    private function __construct()
    {
    }

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

    public function any(\Closure $predicate = null)
    {
        $result = false;
        if (!is_null($predicate)) {
            $expression = new LinqExpression();
            $expression->closure = $predicate;
            $expression->return = LinqExpression::SEQUENCES;
            $this->expressions[] = $expression;
            $this->getResult();
            if ($this->sequences > 0) {
                $result = true;
                $this->sequences = 0;
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
            var_dump($this->sequences);
            if ($this->sequences != count($array)) {
                $result = false;
                $this->sequences = 0;
            }
        }
        return $result;
    }

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


    private function getResult($isDictionary = false)
    {
        $result = [];
        if (count($this->expressions) > 0 && count($this->storage) > 0) {
            $iterator = new LinqIterator($this->storage, $this->expressions);
            while ($iterator->valid()) {
                $key = $iterator->key();
                $value = $iterator->current();
                if (!$value->skipped) {
                    if ($isDictionary) {
                        $result[$key] = $value->value;
                    } else {
                        $result[] = $value->value;
                    }
                    $this->sequences = $value->sequences;
                }
                $iterator->next();
                print("<pre>");
                print_r($value);
                print("</pre>");
            }
        }
        return $result;
    }
}