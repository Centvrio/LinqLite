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
    private $aggregate = null;

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
        if ($this->position == 0) {
            $this->aggregate = $value;
        }
        foreach ($this->expressions as $expression) {
            $closure = $expression->closure;
            $exprResult = $expression->return == LinqExpression::AGGREGATION ? null : $closure($value, $key);
            if ($expression->return == LinqExpression::FILTERING) {
                if ($exprResult === true) {
                    $result->filtered = false;
                } else {
                    $result->filtered = true;
                    --$this->position;
                }
            }
            if ($expression->return == LinqExpression::PROJECTION) {
                $value = $exprResult;
            }
            if ($expression->return == LinqExpression::SEQUENCES) {
                if ($exprResult === true) {
                    $result->containsCounter = 1;
                } else {
                    $result->containsCounter = 0;
                }
            }
            if ($expression->return == LinqExpression::AGGREGATION && !$result->filtered) {
                if ($this->position > 0) {
                    $this->aggregate = $closure($this->aggregate, $value);
                }
                $result->accumulate = $this->aggregate;
            }
            $result->value = $value;
        }
        var_dump($result);
        return $result;
    }

    public function next()
    {
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

} 