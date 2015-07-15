<?php
namespace LinqLite\Exception;

/**
 * Class ArgumentException
 * @package LinqLite
 * @subpackage Exception
 */
class ArgumentException extends \Exception {
    /**
     * Exception default message
     * @var string
     */
    protected $message = 'Argument must be array or ArrayObject.';
} 