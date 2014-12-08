<?php
namespace LinqLite\Exception;

/**
 * Class InvalidOperationException
 *
 * @package    Linq
 * @subpackage Exception
 */
class InvalidOperationException extends \Exception
{
    /**
     * Exception default message.
     * @var string
     */
    protected $message = 'No element satisfies the condition.';
}
