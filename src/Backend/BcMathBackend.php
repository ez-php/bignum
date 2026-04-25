<?php

declare(strict_types=1);

namespace EzPhp\BigNum\Backend;

use EzPhp\BigNum\DivisionByZeroException;

/**
 * BCMath-based integer arithmetic backend.
 *
 * Uses PHP's bcmath extension as the computation engine. Requires ext-bcmath.
 * When both bcmath and gmp are available, BigInteger auto-selects GmpBackend
 * because GMP is faster; BcMathBackend can be forced via setDefaultBackend().
 *
 * @requires ext-bcmath
 */
final class BcMathBackend implements IntegerBackend
{
    /**
     * Assert that the string is numeric for static analysis.
     *
     * By class invariant, every string passed to this backend from BigInteger
     * is a normalized integer string matching /^-?[0-9]+$/, which satisfies
     * the numeric-string requirement of bcmath functions.
     *
     * @param string $v Normalized integer string
     * @return numeric-string
     */
    private static function n(string $v): string
    {
        assert(\is_numeric($v), "Expected numeric string, got: {$v}");

        return $v;
    }

    public function add(string $a, string $b): string
    {
        return \bcadd(self::n($a), self::n($b), 0);
    }

    public function subtract(string $a, string $b): string
    {
        return \bcsub(self::n($a), self::n($b), 0);
    }

    public function multiply(string $a, string $b): string
    {
        return \bcmul(self::n($a), self::n($b), 0);
    }

    public function divide(string $a, string $b): string
    {
        $nb = self::n($b);

        if (\bccomp($nb, '0', 0) === 0) {
            throw new DivisionByZeroException();
        }

        return \bcdiv(self::n($a), $nb, 0);
    }

    public function mod(string $a, string $b): string
    {
        $nb = self::n($b);

        if (\bccomp($nb, '0', 0) === 0) {
            throw new DivisionByZeroException();
        }

        return \bcmod(self::n($a), $nb);
    }

    public function pow(string $base, int $exponent): string
    {
        if ($exponent < 0) {
            throw new \InvalidArgumentException('Exponent must be non-negative, got ' . $exponent);
        }

        return \bcpow(self::n($base), (string) $exponent, 0);
    }

    public function abs(string $a): string
    {
        $na = self::n($a);

        return \bccomp($na, '0', 0) < 0 ? \bcsub('0', $na, 0) : $na;
    }

    public function negate(string $a): string
    {
        $na = self::n($a);

        if (\bccomp($na, '0', 0) === 0) {
            return '0';
        }

        return \bcsub('0', $na, 0);
    }

    public function gcd(string $a, string $b): string
    {
        // Work with absolute values as numeric-string from the start
        $na = self::n($a);
        $nb = self::n($b);
        $a = \bccomp($na, '0', 0) < 0 ? \bcsub('0', $na, 0) : $na;
        $b = \bccomp($nb, '0', 0) < 0 ? \bcsub('0', $nb, 0) : $nb;

        // Euclidean algorithm; reassign through self::n() to maintain numeric-string type
        while (\bccomp(self::n($b), '0', 0) !== 0) {
            $t = self::n($b);
            $b = \bcmod(self::n($a), $t);
            $a = $t;
        }

        return $a;
    }

    public function sqrt(string $a): string
    {
        $na = self::n($a);

        if (\bccomp($na, '0', 0) < 0) {
            throw new \InvalidArgumentException('Square root of a negative number is not defined');
        }

        if (\bccomp($na, '0', 0) === 0) {
            return '0';
        }

        // Compute with one extra decimal digit, then truncate to get floor
        $withDecimal = \bcsqrt($na, 1);

        return \bcdiv(self::n($withDecimal), '1', 0);
    }

    public function compare(string $a, string $b): int
    {
        return \bccomp(self::n($a), self::n($b), 0);
    }
}
