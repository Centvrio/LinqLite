<?php
namespace LinqLite\Exception;

/**
 * Class InvalidOperationException
 *
 * @package Linq
 * @subpackage Exception
 */
class InvalidOperationException extends \Exception
{
    /**
     * @var string
     */
    protected $message = 'No element satisfies the condition.';
}
