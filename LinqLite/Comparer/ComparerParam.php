<?php
namespace LinqLite\Comparer;

/**
 * Class ComparerParam
 * @package Linq
 * @subpackage Comparer
 */
class ComparerParam
{
    /**
     * @var integer|string
     */
    public $key;
    /**
     * @var mixed
     */
    public $value;

    /**
     * @param mixed $value
     * @param integer|string $key
     */
    public function __construct($value, $key)
    {
        $this->key = $key;
        $this->value = $value;
    }
}
