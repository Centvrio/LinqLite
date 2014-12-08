<?php
namespace LinqLite\Iterator;


use LinqLite\Expression\LinqExpression;

final class LinqIterator implements \Iterator
{
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

    public function current()
    {
        $key = key($this->storage);
        $value = current($this->storage);
        $result = new \StdClass();
        $result->skipped = false;
        $result->sequences = 0;
        foreach ($this->expressions as $expression) {
            $closure = $expression->closure;
            $exprResult = $closure($value, $key);
            if (($expression->return == LinqExpression::FILTERING || $expression->return == LinqExpression::SEQUENCES) && !$result->skipped) {
                if ($exprResult === true) {
                    $result->skipped = false;
                    ++$result->sequences;
                } else {
                    $result->skipped = true;
                }
            }
            if ($expression->return == LinqExpression::PROJECTION) {
                $value = $exprResult;
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