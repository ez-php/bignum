<?php

declare(strict_types=1);

namespace EzPhp\BigNum\Backend;

use EzPhp\BigNum\DivisionByZeroException;

/**
 * GMP-based integer arithmetic backend.
 *
 * Uses PHP's GMP extension as a faster alternative to bcmath for BigInteger
 * operations. GMP is not required — bcmath is used by default.
 *
 * @requires ext-gmp
 */
final class GmpBackend implements IntegerBackend
{
    public function add(string $a, string $b): string
    {
        return gmp_strval(gmp_add($a, $b));
    }

    public function subtract(string $a, string $b): string
    {
        return gmp_strval(gmp_sub($a, $b));
    }

    public function multiply(string $a, string $b): string
    {
        return gmp_strval(gmp_mul($a, $b));
    }

    public function divide(string $a, string $b): string
    {
        if (gmp_cmp($b, '0') === 0) {
            throw new DivisionByZeroException();
        }

        // GMP_ROUND_ZERO truncates towards zero (same as bcmath's bcdiv with scale=0)
        return gmp_strval(gmp_div_q($a, $b, GMP_ROUND_ZERO));
    }

    public function mod(string $a, string $b): string
    {
        if (gmp_cmp($b, '0') === 0) {
            throw new DivisionByZeroException();
        }

        // GMP_ROUND_ZERO: remainder has the same sign as the dividend (matches bcmod)
        return gmp_strval(gmp_div_r($a, $b, GMP_ROUND_ZERO));
    }

    public function pow(string $base, int $exponent): string
    {
        if ($exponent < 0) {
            throw new \InvalidArgumentException('Exponent must be non-negative, got ' . $exponent);
        }

        return gmp_strval(gmp_pow($base, $exponent));
    }

    public function abs(string $a): string
    {
        return gmp_strval(gmp_abs($a));
    }

    public function negate(string $a): string
    {
        return gmp_strval(gmp_neg($a));
    }

    public function gcd(string $a, string $b): string
    {
        return gmp_strval(gmp_gcd($a, $b));
    }

    public function sqrt(string $a): string
    {
        if (gmp_cmp($a, '0') < 0) {
            throw new \InvalidArgumentException('Square root of a negative number is not defined');
        }

        // gmp_sqrt returns floor(sqrt(n)) for non-negative integers
        return gmp_strval(gmp_sqrt($a));
    }

    public function compare(string $a, string $b): int
    {
        $cmp = gmp_cmp($a, $b);

        if ($cmp > 0) {
            return 1;
        }

        if ($cmp < 0) {
            return -1;
        }

        return 0;
    }
}
