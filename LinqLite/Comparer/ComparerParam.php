<?php
namespace LinqLite\Comparer;

/**
 * Class ComparerParam
 *
 * @package    Linq
 * @subpackage Comparer
 */
class ComparerParam
{
    /**
     * Array element key
     * @var integer|string
     */
    public $key;
    /**
     * Array element value
     * @var mixed
     */
    public $value;

    /**
     * Class constructor
     *
     * @param mixed          $value Array element value.
     * @param integer|string $key   Array element key.
     */
    public function __construct($value, $key)
    {
        $this->key = $key;
        $this->value = $value;
    }
}
