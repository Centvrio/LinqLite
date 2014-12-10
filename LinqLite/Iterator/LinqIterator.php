<?php
namespace LinqLite\Iterator;


use LinqLite\Expression\LinqExpression;

final class LinqIterator implements \Iterator
{
    /**
     * @var array
     */
    private $storage = [];
    /**
     * @var LinqExpression[]
     */
    private $expressions = [];
    private $position = 0;
    private $index = 0;
    private $searchInvoked = 0;
    private $accumulate = null;

    public function __construct(array $storage, $expressions)
    {
        $this->storage = $storage;
        $this->expressions = $expressions;
    }

    /**
     * @return LinqIteratorResult
     */
    public function current()
    {
        $key = key($this->storage);
        $value = current($this->storage);
        $result = new LinqIteratorResult();
        $result->value = $value;
        $result->key = $key;
        $result->index = $this->index;
        if (count($this->expressions) > 0) {
            foreach ($this->expressions as $expression) {
                switch ($expression->return) {
                    case LinqExpression::FILTERING:
                        $this->filtered($expression, $result);
                        break;
                    case LinqExpression::PROJECTION:
                        $this->projection($expression, $result);
                        break;
                    case LinqExpression::SEQUENCES:
                        $this->sequences($expression, $result);
                        break;
                    case LinqExpression::AGGREGATION:
                        $this->aggregation($expression, $result);
                        break;
                    case LinqExpression::SEARCHES:
                        $this->searches($expression, $result);
                        break;
                }
            }
        }
        if (!$result->filtered) {
            var_dump($result);
        }
        return $result;
    }

    public function next()
    {
        ++$this->index;
        ++$this->position;
        next($this->storage);
    }

    public function key()
    {
        return key($this->storage);
    }

    public function valid()
    {
        return key($this->storage) !== null;
    }

    public function rewind()
    {
        $this->position = 0;
        reset($this->storage);
    }

    private function filtered(LinqExpression $expr, LinqIteratorResult $result)
    {
        if (!$result->filtered) {
            $closure = $expr->closure;
            $returnValue = $closure($result->value, $result->key);
            if ($returnValue === true) {
                $result->filtered = false;
            } else {
                $result->filtered = true;
                --$this->index;
            }
        }
        return $result;
    }

    private function projection(LinqExpression $expr, LinqIteratorResult $result)
    {
        if (!$result->filtered) {
            $closure = $expr->closure;
            $result->value = $closure($result->value, $result->key);
        }
        return $result;
    }

    private function sequences(LinqExpression $expr, LinqIteratorResult $result)
    {
        if ($result->filtered) {
            $result->contains = false;
        } else {
            $closure = $expr->closure;
            $returnValue = $closure($result->value, $result->key);
            if ($returnValue === true) {
                $result->contains = true;
            } else {
                $result->contains = false;
            }
        }
        return $result;
    }

    private function aggregation(LinqExpression $expr, LinqIteratorResult $result)
    {
        if ($result->filtered) {
            $this->position = 0;
        } else {
            if ($this->position == 0) {
                $this->accumulate = $result->value;
            } else {
                $closure = $expr->closure;
                $this->accumulate = $closure($this->accumulate, $result->value);
                $result->aggregate = $this->accumulate;
            }
        }
        return $result;
    }

    private function searches(LinqExpression $expr, LinqIteratorResult $result)
    {
        if (!$result->filtered) {
            $closure = $expr->closure;
            $this->searchInvoked++;
            $returnValue = $closure($result->value, $result->index, $this->searchInvoked);
            if ($returnValue === true) {
                $result->filtered = false;
            } else {
                $result->filtered = true;
            }
        }
        return $result;
    }

} 