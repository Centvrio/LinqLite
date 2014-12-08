<?php
namespace LinqLite\Expression;

class LinqExpression
{
    const FILTERING = 0;
    const PROJECTION = 1;
    const SEQUENCES = 2;
    const AGGREGATION = 3;

    /**
     * @var \Closure|null
     */
    public $closure = null;
    public $return;
} 