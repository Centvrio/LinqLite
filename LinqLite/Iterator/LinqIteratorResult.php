<?php
namespace LinqLite\Iterator;


class LinqIteratorResult
{
    public $key;
    public $value;
    public $filtered = false;
    public $contains = true;
    public $index = 0;
    public $aggregate = null;
}