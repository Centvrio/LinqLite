<?php
namespace LinqLite\Exception;

/**
 * Class ArgumentNullException
 *
 * @package    LinqLite
 * @subpackage Exception
 */
class ArgumentNullException extends \Exception
{
    /**
     * Exception default message
     * @var string
     */
    protected $message = 'Source or predicate is null.';
}
