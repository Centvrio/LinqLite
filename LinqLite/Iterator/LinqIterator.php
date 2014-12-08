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
        foreach ($this->expressions as $expression) {
            $closure = $expression->closure;
            $exprResult = $closure($value, $key);
            if ($expression->return == LinqExpression::FILTERING) {
                if ($exprResult === true) {
                    $result->filtered = false;
                    $result->containsCounter = 1;
                } else {
                    $result->filtered = true;
                    $result->containsCounter = 0;
                }
            }
            if ($expression->return == LinqExpression::PROJECTION) {
                $value = $exprResult;
            }
            if ($expression->return==LinqExpression::SEQUENCES) {
                if ($exprResult === true) {
                    $result->containsCounter = 1;
                } else {
                    $result->containsCounter = 0;
                }
            }
            $result->value = $value;
        }
        return $result;
    }

    public function next()
    {
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
        reset($this->storage);
    }

} 