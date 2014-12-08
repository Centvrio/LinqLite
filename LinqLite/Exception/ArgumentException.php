<?php
/**
 * Created by PhpStorm.
 * User: Centvrio
 * Date: 08.12.2014
 * Time: 14:57
 */

namespace LinqLite\Exception;


class ArgumentException extends \Exception {
    /**
     * Exception default message
     * @var string
     */
    protected $message = 'Argument must be array or ArrayObject.';
} 