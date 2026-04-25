<?php

declare(strict_types=1);

namespace EzPhp\BigNum;

use EzPhp\BigNum\Backend\BcMathBackend;
use EzPhp\BigNum\Backend\GmpBackend;
use EzPhp\BigNum\Backend\IntegerBackend;

/**
 * Immutable arbitrary-precision integer value object.
 *
 * All arithmetic operations return a new BigInteger instance.
 * Internally uses an IntegerBackend (bcmath by default, gmp if available).
 *
 * @see BigDecimal for decimal arithmetic
 */
final class BigInteger implements \Stringable
{
    private static ?IntegerBackend $defaultBackend = null;

    /**
     * @param string          $value   Normalized integer string (e.g. "-42", "0", "100")
     * @param IntegerBackend  $backend Arithmetic backend
     */
    private function __construct(
        private readonly string $value,
        private readonly IntegerBackend $backend,
    ) {
    }

    // -------------------------------------------------------------------------
    // Factory methods
    // -------------------------------------------------------------------------

    /**
     * Create a BigInteger from an integer or decimal string.
     *
     * @throws \InvalidArgumentException if the value is not a valid integer
     */
    public static function of(int|string $value): self
    {
        $str = (string) $value;

        if (!preg_match('/^-?[0-9]+$/', $str)) {
            throw new \InvalidArgumentException("Not a valid integer: \"{$str}\"");
        }

        return new self(self::normalize($str), self::resolveBackend());
    }

    /**
     * Return BigInteger zero.
     */
    public static function zero(): self
    {
        return new self('0', self::resolveBackend());
    }

    /**
     * Return BigInteger one.
     */
    public static function one(): self
    {
        return new self('1', self::resolveBackend());
    }

    // -------------------------------------------------------------------------
    // Backend management
    // -------------------------------------------------------------------------

    /**
     * Override the default backend for all subsequent BigInteger instances.
     *
     * Useful for forcing bcmath or gmp in tests or specific contexts.
     */
    public static function setDefaultBackend(IntegerBackend $backend): void
    {
        self::$defaultBackend = $backend;
    }

    /**
     * Return the default backend, auto-selecting gmp when available.
     */
    public static function getDefaultBackend(): IntegerBackend
    {
        return self::resolveBackend();
    }

    // -------------------------------------------------------------------------
    // Arithmetic
    // -------------------------------------------------------------------------

    /**
     * Add another integer to this value.
     */
    public function add(BigInteger|int|string $other): self
    {
        $o = $this->coerce($other);

        return new self(self::normalizeResult($this->backend->add($this->value, $o->value)), $this->backend);
    }

    /**
     * Subtract another integer from this value.
     */
    public function subtract(BigInteger|int|string $other): self
    {
        $o = $this->coerce($other);

        return new self(self::normalizeResult($this->backend->subtract($this->value, $o->value)), $this->backend);
    }

    /**
     * Multiply this value by another integer.
     */
    public function multiply(BigInteger|int|string $other): self
    {
        $o = $this->coerce($other);

        return new self(self::normalizeResult($this->backend->multiply($this->value, $o->value)), $this->backend);
    }

    /**
     * Truncated integer division (towards zero).
     *
     * @throws DivisionByZeroException
     */
    public function divide(BigInteger|int|string $divisor): self
    {
        $d = $this->coerce($divisor);

        return new self(self::normalizeResult($this->backend->divide($this->value, $d->value)), $this->backend);
    }

    /**
     * Remainder of truncated division. Result sign follows the dividend.
     *
     * @throws DivisionByZeroException
     */
    public function mod(BigInteger|int|string $divisor): self
    {
        $d = $this->coerce($divisor);

        return new self(self::normalizeResult($this->backend->mod($this->value, $d->value)), $this->backend);
    }

    /**
     * Raise this value to a non-negative integer power.
     *
     * @throws \InvalidArgumentException if $exponent is negative
     */
    public function pow(int $exponent): self
    {
        return new self(self::normalizeResult($this->backend->pow($this->value, $exponent)), $this->backend);
    }

    /**
     * Absolute value.
     */
    public function abs(): self
    {
        return new self($this->backend->abs($this->value), $this->backend);
    }

    /**
     * Negate this value.
     */
    public function negate(): self
    {
        return new self($this->backend->negate($this->value), $this->backend);
    }

    /**
     * Greatest common divisor (always non-negative).
     */
    public function gcd(BigInteger|int|string $other): self
    {
        $o = $this->coerce($other);

        return new self($this->backend->gcd($this->value, $o->value), $this->backend);
    }

    /**
     * Floor integer square root.
     *
     * @throws \InvalidArgumentException if this value is negative
     */
    public function sqrt(): self
    {
        return new self($this->backend->sqrt($this->value), $this->backend);
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    /**
     * Compare this value to another integer. Returns -1, 0, or 1.
     */
    public function compareTo(BigInteger|int|string $other): int
    {
        $o = $this->coerce($other);

        return $this->backend->compare($this->value, $o->value);
    }

    /**
     * Return true if this value equals the other.
     */
    public function isEqualTo(BigInteger|int|string $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    /**
     * Return true if this value is less than the other.
     */
    public function isLessThan(BigInteger|int|string $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    /**
     * Return true if this value is greater than the other.
     */
    public function isGreaterThan(BigInteger|int|string $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /**
     * Return true if this value is less than or equal to the other.
     */
    public function isLessThanOrEqualTo(BigInteger|int|string $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    /**
     * Return true if this value is greater than or equal to the other.
     */
    public function isGreaterThanOrEqualTo(BigInteger|int|string $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    /**
     * Return true if this value is zero.
     */
    public function isZero(): bool
    {
        return $this->value === '0';
    }

    /**
     * Return true if this value is strictly positive.
     */
    public function isPositive(): bool
    {
        return $this->backend->compare($this->value, '0') > 0;
    }

    /**
     * Return true if this value is strictly negative.
     */
    public function isNegative(): bool
    {
        return $this->backend->compare($this->value, '0') < 0;
    }

    // -------------------------------------------------------------------------
    // Conversion
    // -------------------------------------------------------------------------

    /**
     * Convert to a native PHP int.
     *
     * @throws \OverflowException if the value exceeds PHP_INT_MAX or PHP_INT_MIN
     */
    public function toInt(): int
    {
        if (
            $this->backend->compare($this->value, (string) PHP_INT_MAX) > 0
            || $this->backend->compare($this->value, (string) PHP_INT_MIN) < 0
        ) {
            throw new \OverflowException("Value {$this->value} does not fit in a native int");
        }

        return (int) $this->value;
    }

    /**
     * Convert to a native PHP float. Precision may be lost for very large values.
     */
    public function toFloat(): float
    {
        return (float) $this->value;
    }

    /**
     * Return the integer as a decimal string.
     */
    public function toString(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Convert this integer to a BigDecimal with scale 0.
     */
    public function toBigDecimal(): BigDecimal
    {
        return BigDecimal::ofUnscaledValue($this->value, 0);
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Normalize an integer string: remove leading zeros, handle -0.
     */
    private static function normalize(string $value): string
    {
        $negative = str_starts_with($value, '-');
        $digits = $negative ? substr($value, 1) : $value;
        $stripped = ltrim($digits, '0');

        if ($stripped === '') {
            return '0';
        }

        return $negative ? '-' . $stripped : $stripped;
    }

    /**
     * Normalize a result string returned by the backend (bcmath may produce "-0").
     */
    private static function normalizeResult(string $value): string
    {
        return self::normalize($value);
    }

    /**
     * Coerce a value to BigInteger using the current instance's backend.
     */
    private function coerce(BigInteger|int|string $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        $str = (string) $value;

        if (!preg_match('/^-?[0-9]+$/', $str)) {
            throw new \InvalidArgumentException("Not a valid integer: \"{$str}\"");
        }

        return new self(self::normalize($str), $this->backend);
    }

    /**
     * Resolve the default backend: gmp if available, otherwise bcmath.
     */
    private static function resolveBackend(): IntegerBackend
    {
        if (self::$defaultBackend === null) {
            self::$defaultBackend = extension_loaded('gmp') ? new GmpBackend() : new BcMathBackend();
        }

        return self::$defaultBackend;
    }
}
