<?php
namespace LinqLite\Expression;

class LinqExpression
{
    const FILTERING = 1;
    const PROJECTION = 2;
    const SEQUENCES = 4;
    const AGGREGATION = 8;
    const SEARCHES = 16;
    const LOOKUP = 32;
    const JOINING = 64;
    const GROUPING = 128;
    const ZIP = 256;

    /**
     * @var \Closure|\Closure[]|null
     */
    public $closure = null;
    public $params = [];
    public $return;
}
