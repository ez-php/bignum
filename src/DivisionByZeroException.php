<?php

declare(strict_types=1);

namespace EzPhp\BigNum;

/**
 * Thrown when a division by zero is attempted.
 *
 * Extends \ArithmeticError to be consistent with PHP's built-in error hierarchy.
 */
final class DivisionByZeroException extends \ArithmeticError
{
    public function __construct(string $message = 'Division by zero')
    {
        parent::__construct($message);
    }
}
