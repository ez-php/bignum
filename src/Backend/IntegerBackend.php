<?php

declare(strict_types=1);

namespace EzPhp\BigNum\Backend;

use EzPhp\BigNum\DivisionByZeroException;

/**
 * Backend interface for arbitrary-precision integer arithmetic.
 *
 * Implementations provide the raw integer operations used by BigInteger.
 * All inputs and outputs are normalized integer strings (e.g. "-42", "0", "100").
 * By contract, all string parameters match /^-?[0-9]+$/ and are never empty.
 */
interface IntegerBackend
{
    /**
     * Add two integers.
     */
    public function add(string $a, string $b): string;

    /**
     * Subtract $b from $a.
     */
    public function subtract(string $a, string $b): string;

    /**
     * Multiply two integers.
     */
    public function multiply(string $a, string $b): string;

    /**
     * Truncated integer division (towards zero).
     *
     * @throws DivisionByZeroException
     */
    public function divide(string $a, string $b): string;

    /**
     * Remainder of truncated division. Result sign follows the dividend.
     *
     * @throws DivisionByZeroException
     */
    public function mod(string $a, string $b): string;

    /**
     * Raise $base to a non-negative integer power.
     *
     * @throws \InvalidArgumentException if $exponent is negative
     */
    public function pow(string $base, int $exponent): string;

    /**
     * Absolute value.
     */
    public function abs(string $a): string;

    /**
     * Negate the value.
     */
    public function negate(string $a): string;

    /**
     * Greatest common divisor (always non-negative).
     */
    public function gcd(string $a, string $b): string;

    /**
     * Integer square root (floor).
     *
     * @throws \InvalidArgumentException if $a is negative
     */
    public function sqrt(string $a): string;

    /**
     * Compare $a to $b. Returns -1, 0, or 1.
     */
    public function compare(string $a, string $b): int;
}
