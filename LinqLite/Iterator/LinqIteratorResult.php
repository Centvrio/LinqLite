<?php
namespace LinqLite\Iterator;


class LinqIteratorResult
{
    public $value;
    public $filtered = false;
    public $containsCounter = 0;
    public $accumulate = null;
}