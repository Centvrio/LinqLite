<?php
namespace LinqLite\Expression;

class LinqExpression
{
    const FILTERING = 0;
    const PROJECTION = 1;
    const SEQUENCES = 2;
    const AGGREGATION = 3;
    const SEARCHES = 4;
    const LOOKUP = 5;
    const JOINING = 6;

    /**
     * @var \Closure|\Closure[]|null
     */
    public $closure = null;
    public $params = [];
    public $return;
}
