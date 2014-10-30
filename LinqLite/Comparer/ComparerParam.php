<?php
namespace LinqLite\Comparer;

/**
 * Class ComparerParam
 *
 * @package Linq
 * @subpackage Comparer
 */
class ComparerParam
{
    /**
     * @var integer|string
     * Array element key
     */
    public $key;
    /**
     * @var mixed
     * Array element value
     */
    public $value;

    /**
     * Class constructor
     *
     * @param mixed          $value Array element value
     * @param integer|string $key   Array element key
     */
    public function __construct($value, $key)
    {
        $this->key = $key;
        $this->value = $value;
    }
}
