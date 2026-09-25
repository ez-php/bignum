<?php

declare(strict_types=1);

namespace EzPhp\BigNum;

use EzPhp\BigNum\Backend\BcMathBackend;
use EzPhp\BigNum\Backend\GmpBackend;
use EzPhp\BigNum\Backend\IntegerBackend;

/**
 * Immutable arbitrary-precision decimal value object.
 *
 * Internally stored as an unscaled integer string and a non-negative scale:
 *   value = unscaledValue / 10^scale
 *
 * Examples:
 *   "123.45"  → unscaled = "12345",  scale = 2
 *   "-0.005"  → unscaled = "-5",     scale = 3
 *   "100"     → unscaled = "100",    scale = 0
 *
 * All arithmetic operations return a new BigDecimal. Scale follows standard
 * rules (add/subtract → max scale; multiply → sum of scales).
 *
 * All internal integer arithmetic on the unscaled value is delegated to an
 * IntegerBackend (bcmath by default, gmp if available) — the same backend
 * abstraction BigInteger uses.
 *
 * @see BigInteger for integer-only arithmetic
 * @see RoundingMode for rounding strategies
 */
final class BigDecimal implements \Stringable
{
    private static ?IntegerBackend $defaultBackend = null;

    /**
     * @param string         $unscaledValue Normalized integer string (no leading zeros except "0")
     * @param int            $scale         Non-negative number of decimal places
     * @param IntegerBackend $backend       Arithmetic backend
     */
    private function __construct(
        private readonly string $unscaledValue,
        private readonly int $scale,
        private readonly IntegerBackend $backend,
    ) {
    }

    // -------------------------------------------------------------------------
    // Factory methods
    // -------------------------------------------------------------------------

    /**
     * Create a BigDecimal from an integer, decimal string, or float.
     *
     * Float values are converted via sprintf with 14 decimal places to avoid
     * floating-point representation noise. For exact decimal strings, pass a
     * string directly.
     *
     * @throws \InvalidArgumentException if the value is not a valid decimal
     */
    public static function of(int|string|float $value): self
    {
        if (\is_float($value)) {
            if (!\is_finite($value)) {
                throw new \InvalidArgumentException('Cannot create BigDecimal from non-finite float (INF or NAN)');
            }

            // Use sprintf to avoid scientific notation and trailing artifacts
            $str = \rtrim(\rtrim(\sprintf('%.14F', $value), '0'), '.');

            if ($str === '' || $str === '-') {
                $str = '0';
            }
        } else {
            $str = (string) $value;
        }

        [$unscaled, $scale] = self::parseDecimalString($str);

        return new self($unscaled, $scale, self::resolveBackend());
    }

    /**
     * Create a BigDecimal from an unscaled integer string and a scale.
     *
     * For example, ofUnscaledValue("12345", 2) creates 123.45.
     *
     * @throws \InvalidArgumentException if unscaledValue is not a valid integer or scale is negative
     */
    public static function ofUnscaledValue(string $unscaledValue, int $scale): self
    {
        if (!\preg_match('/^-?[0-9]+$/', $unscaledValue)) {
            throw new \InvalidArgumentException("Not a valid integer: \"{$unscaledValue}\"");
        }

        if ($scale < 0) {
            throw new \InvalidArgumentException('Scale cannot be negative, got ' . $scale);
        }

        return new self(self::normalizeUnscaled($unscaledValue), $scale, self::resolveBackend());
    }

    /**
     * Return BigDecimal zero (scale 0).
     */
    public static function zero(): self
    {
        return new self('0', 0, self::resolveBackend());
    }

    /**
     * Return BigDecimal one (scale 0).
     */
    public static function one(): self
    {
        return new self('1', 0, self::resolveBackend());
    }

    // -------------------------------------------------------------------------
    // Backend management
    // -------------------------------------------------------------------------

    /**
     * Override the default backend for all subsequent BigDecimal instances.
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

    /**
     * Resolve the default backend: gmp if available, otherwise bcmath.
     */
    private static function resolveBackend(): IntegerBackend
    {
        if (self::$defaultBackend === null) {
            self::$defaultBackend = \extension_loaded('gmp') ? new GmpBackend() : new BcMathBackend();
        }

        return self::$defaultBackend;
    }

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Return the number of decimal places.
     */
    public function getScale(): int
    {
        return $this->scale;
    }

    /**
     * Return the unscaled integer string (e.g. "12345" for 123.45 with scale 2).
     */
    public function getUnscaledValue(): string
    {
        return $this->unscaledValue;
    }

    // -------------------------------------------------------------------------
    // Arithmetic
    // -------------------------------------------------------------------------

    /**
     * Add another decimal to this value. Result scale = max(this.scale, other.scale).
     */
    public function add(BigDecimal|BigInteger|int|string $other): self
    {
        $o = self::coerce($other);
        $maxScale = \max($this->scale, $o->scale);

        $a = $this->scaleUp($this->unscaledValue, $maxScale - $this->scale);
        $b = $this->scaleUp($o->unscaledValue, $maxScale - $o->scale);

        return new self(self::normalizeUnscaled($this->backend->add($a, $b)), $maxScale, $this->backend);
    }

    /**
     * Subtract another decimal from this value. Result scale = max(this.scale, other.scale).
     */
    public function subtract(BigDecimal|BigInteger|int|string $other): self
    {
        $o = self::coerce($other);
        $maxScale = \max($this->scale, $o->scale);

        $a = $this->scaleUp($this->unscaledValue, $maxScale - $this->scale);
        $b = $this->scaleUp($o->unscaledValue, $maxScale - $o->scale);

        return new self(self::normalizeUnscaled($this->backend->subtract($a, $b)), $maxScale, $this->backend);
    }

    /**
     * Multiply this value by another decimal. Result scale = this.scale + other.scale.
     */
    public function multiply(BigDecimal|BigInteger|int|string $other): self
    {
        $o = self::coerce($other);
        $scale = $this->scale + $o->scale;

        return new self(
            self::normalizeUnscaled($this->backend->multiply($this->unscaledValue, $o->unscaledValue)),
            $scale,
            $this->backend,
        );
    }

    /**
     * Truncated integer division. Returns a BigDecimal with scale 0.
     *
     * @throws DivisionByZeroException
     */
    public function divide(BigDecimal|BigInteger|int|string $divisor): self
    {
        return $this->dividedBy($divisor, 0, RoundingMode::DOWN);
    }

    /**
     * Divide this value by the divisor with explicit scale and rounding.
     *
     * @throws DivisionByZeroException
     * @throws \InvalidArgumentException if scale is negative
     */
    public function dividedBy(
        BigDecimal|BigInteger|int|string $divisor,
        int $scale,
        RoundingMode $roundingMode = RoundingMode::HALF_UP,
    ): self {
        if ($scale < 0) {
            throw new \InvalidArgumentException('Scale cannot be negative, got ' . $scale);
        }

        $d = self::coerce($divisor);

        if ($d->isZero()) {
            throw new DivisionByZeroException();
        }

        // We want: result = this / d with $scale decimal places.
        //
        // result.unscaled = this.unscaled * 10^(scale + 1 + d.scale - this.scale) / d.unscaled
        //
        // The extra digit (scale+1) is used to determine rounding direction.

        $exp = $scale + 1 + $d->scale - $this->scale;

        if ($exp >= 0) {
            $numerator = $this->backend->multiply($this->unscaledValue, $this->backend->pow('10', $exp));
            $denominator = $d->unscaledValue;
        } else {
            $numerator = $this->unscaledValue;
            $denominator = $this->backend->multiply($d->unscaledValue, $this->backend->pow('10', -$exp));
        }

        // Truncated integer division (towards zero)
        $quotient = $this->backend->divide($numerator, $denominator);
        $isNegative = $this->backend->compare($quotient, '0') < 0;
        $absQuotient = $this->backend->abs($quotient);

        $roundedAbs = $this->applyRounding($absQuotient, $isNegative, $roundingMode);

        $finalUnscaled = ($isNegative && $roundedAbs !== '0') ? '-' . $roundedAbs : $roundedAbs;

        return new self($finalUnscaled, $scale, $this->backend);
    }

    /**
     * Remainder of truncated division. Result scale = max(this.scale, other.scale).
     *
     * @throws DivisionByZeroException
     */
    public function mod(BigDecimal|BigInteger|int|string $divisor): self
    {
        $d = self::coerce($divisor);

        if ($d->isZero()) {
            throw new DivisionByZeroException();
        }

        // mod(a, b) = a - trunc(a/b) * b
        $quotient = $this->divide($d);
        $product = $quotient->multiply($d);

        return $this->subtract($product);
    }

    /**
     * Raise this value to a non-negative integer power.
     *
     * Result scale = this.scale * exponent.
     *
     * @throws \InvalidArgumentException if $exponent is negative
     */
    public function pow(int $exponent): self
    {
        if ($exponent < 0) {
            throw new \InvalidArgumentException('Exponent must be non-negative, got ' . $exponent);
        }

        if ($exponent === 0) {
            return self::one();
        }

        $newScale = $this->scale * $exponent;
        $newUnscaled = $this->backend->pow($this->unscaledValue, $exponent);

        return new self(self::normalizeUnscaled($newUnscaled), $newScale, $this->backend);
    }

    /**
     * Square root, rounded to the given scale.
     *
     * @throws \InvalidArgumentException if this value is negative or $scale is negative
     */
    public function sqrt(int $scale, RoundingMode $roundingMode = RoundingMode::HALF_UP): self
    {
        return $this->nthRoot(2, $scale, $roundingMode);
    }

    /**
     * N-th root, rounded to the given scale.
     *
     * @throws \InvalidArgumentException if this value is negative, $n is less than 1, or $scale is negative
     */
    public function nthRoot(int $n, int $scale, RoundingMode $roundingMode = RoundingMode::HALF_UP): self
    {
        if ($n < 1) {
            throw new \InvalidArgumentException('Root degree must be at least 1, got ' . $n);
        }

        if ($scale < 0) {
            throw new \InvalidArgumentException('Scale cannot be negative, got ' . $scale);
        }

        if ($this->isNegative()) {
            throw new \InvalidArgumentException('Cannot compute a root of a negative value');
        }

        if ($n === 1) {
            return $this->toScale($scale, $roundingMode);
        }

        if ($this->isZero()) {
            return new self('0', $scale, $this->backend);
        }

        // Root at a scale with at least one guard digit beyond the target
        // ($scale + 1) and enough digits to represent this value exactly
        // before rooting (ceil(this.scale / $n)) — the operand is only ever
        // scaled *up*, never truncated down before the root is taken; only
        // the final result is rounded, via the already-tested toScale().
        $minIntermediateScale = \intdiv($this->scale + $n - 1, $n);
        $intermediateScale = \max($scale + 1, $minIntermediateScale);
        $targetExp = $n * $intermediateScale - $this->scale;

        $operand = $targetExp === 0
            ? $this->unscaledValue
            : $this->backend->multiply($this->unscaledValue, $this->backend->pow('10', $targetExp));

        $rootUnscaled = $this->integerNthRoot($operand, $n);

        $intermediate = new self(self::normalizeUnscaled($rootUnscaled), $intermediateScale, $this->backend);

        return $intermediate->toScale($scale, $roundingMode);
    }

    /**
     * Raise this value to a rational power (numerator/denominator), rounded to the given scale.
     *
     * Computed as the $denominator-th root of `$this ** $numerator` — the
     * fraction is reduced first, so an exact result (denominator divides
     * numerator) is returned via the exact integer pow() without rounding.
     *
     * @throws \InvalidArgumentException if $numerator is negative, $denominator is less than 1,
     *                                    $scale is negative, or this value is negative with a
     *                                    non-integer exponent
     */
    public function powRational(
        int $numerator,
        int $denominator,
        int $scale,
        RoundingMode $roundingMode = RoundingMode::HALF_UP,
    ): self {
        if ($numerator < 0) {
            throw new \InvalidArgumentException('Numerator must be non-negative, got ' . $numerator);
        }

        if ($denominator < 1) {
            throw new \InvalidArgumentException('Denominator must be at least 1, got ' . $denominator);
        }

        if ($scale < 0) {
            throw new \InvalidArgumentException('Scale cannot be negative, got ' . $scale);
        }

        $divisor = self::gcd($numerator, $denominator);
        $reducedNumerator = \intdiv($numerator, $divisor);
        $reducedDenominator = \intdiv($denominator, $divisor);

        if ($reducedDenominator === 1) {
            return $this->pow($reducedNumerator);
        }

        if ($this->isNegative()) {
            throw new \InvalidArgumentException('Cannot compute a fractional power of a negative value');
        }

        return $this->pow($reducedNumerator)->nthRoot($reducedDenominator, $scale, $roundingMode);
    }

    /**
     * Absolute value.
     */
    public function abs(): self
    {
        if (!\str_starts_with($this->unscaledValue, '-')) {
            return $this;
        }

        return new self(\substr($this->unscaledValue, 1), $this->scale, $this->backend);
    }

    /**
     * Negate this value.
     */
    public function negate(): self
    {
        if ($this->isZero()) {
            return $this;
        }

        if (\str_starts_with($this->unscaledValue, '-')) {
            return new self(\substr($this->unscaledValue, 1), $this->scale, $this->backend);
        }

        return new self('-' . $this->unscaledValue, $this->scale, $this->backend);
    }

    // -------------------------------------------------------------------------
    // Scale and rounding
    // -------------------------------------------------------------------------

    /**
     * Round this value to the given number of decimal places.
     *
     * Alias for toScale(). Provided for readability in financial contexts.
     */
    public function round(int $scale, RoundingMode $roundingMode = RoundingMode::HALF_UP): self
    {
        return $this->toScale($scale, $roundingMode);
    }

    /**
     * Return a new BigDecimal with a different scale.
     *
     * When $scale > this.scale the value is extended with trailing zeros.
     * When $scale < this.scale the value is rounded using $roundingMode.
     */
    public function toScale(int $scale, RoundingMode $roundingMode = RoundingMode::HALF_UP): self
    {
        if ($scale < 0) {
            throw new \InvalidArgumentException('Scale cannot be negative, got ' . $scale);
        }

        if ($scale === $this->scale) {
            return $this;
        }

        if ($scale > $this->scale) {
            $diff = $scale - $this->scale;
            $newUnscaled = $this->backend->multiply($this->unscaledValue, $this->backend->pow('10', $diff));

            return new self($newUnscaled, $scale, $this->backend);
        }

        // Reduce scale with rounding: build an integer with 1 extra digit beyond
        // the target scale, then apply the rounding rule.
        $diff = $this->scale - $scale;
        $isNegative = \str_starts_with($this->unscaledValue, '-');
        $abs = $isNegative ? \substr($this->unscaledValue, 1) : $this->unscaledValue;

        // abs * 10 / 10^diff  →  1 extra digit for rounding
        $dividend = $this->backend->multiply($abs, '10');
        $divisor = $this->backend->pow('10', $diff);
        $withExtraDigit = $this->backend->divide($dividend, $divisor);

        $roundedAbs = $this->applyRounding($withExtraDigit, $isNegative, $roundingMode);
        $result = ($isNegative && $roundedAbs !== '0') ? '-' . $roundedAbs : $roundedAbs;

        return new self($result, $scale, $this->backend);
    }

    // -------------------------------------------------------------------------
    // Comparison
    // -------------------------------------------------------------------------

    /**
     * Compare this value to another. Returns -1, 0, or 1.
     */
    public function compareTo(BigDecimal|BigInteger|int|string $other): int
    {
        $o = self::coerce($other);
        $maxScale = \max($this->scale, $o->scale);

        $a = $this->scaleUp($this->unscaledValue, $maxScale - $this->scale);
        $b = $this->scaleUp($o->unscaledValue, $maxScale - $o->scale);

        return $this->backend->compare($a, $b);
    }

    /**
     * Return true if this value equals the other (scale-independent).
     */
    public function isEqualTo(BigDecimal|BigInteger|int|string $other): bool
    {
        return $this->compareTo($other) === 0;
    }

    /**
     * Return true if this value is less than the other.
     */
    public function isLessThan(BigDecimal|BigInteger|int|string $other): bool
    {
        return $this->compareTo($other) < 0;
    }

    /**
     * Return true if this value is greater than the other.
     */
    public function isGreaterThan(BigDecimal|BigInteger|int|string $other): bool
    {
        return $this->compareTo($other) > 0;
    }

    /**
     * Return true if this value is less than or equal to the other.
     */
    public function isLessThanOrEqualTo(BigDecimal|BigInteger|int|string $other): bool
    {
        return $this->compareTo($other) <= 0;
    }

    /**
     * Return true if this value is greater than or equal to the other.
     */
    public function isGreaterThanOrEqualTo(BigDecimal|BigInteger|int|string $other): bool
    {
        return $this->compareTo($other) >= 0;
    }

    /**
     * Return true if this value is zero (regardless of scale).
     */
    public function isZero(): bool
    {
        return $this->unscaledValue === '0';
    }

    /**
     * Return true if this value is strictly positive.
     */
    public function isPositive(): bool
    {
        return !$this->isZero() && !\str_starts_with($this->unscaledValue, '-');
    }

    /**
     * Return true if this value is strictly negative.
     */
    public function isNegative(): bool
    {
        return \str_starts_with($this->unscaledValue, '-');
    }

    // -------------------------------------------------------------------------
    // Conversion
    // -------------------------------------------------------------------------

    /**
     * Convert to a native PHP int, truncating any fractional part.
     *
     * @throws \OverflowException if the integer part exceeds PHP_INT_MAX or PHP_INT_MIN
     */
    public function toInt(): int
    {
        // Compute integer part: unscaled / 10^scale (truncate towards zero)
        if ($this->scale === 0) {
            $intStr = $this->unscaledValue;
        } else {
            $intStr = $this->backend->divide($this->unscaledValue, $this->backend->pow('10', $this->scale));
        }

        if (
            $this->backend->compare($intStr, (string) \PHP_INT_MAX) > 0
            || $this->backend->compare($intStr, (string) \PHP_INT_MIN) < 0
        ) {
            throw new \OverflowException("Value {$this->unscaledValue} does not fit in a native int");
        }

        return (int) $intStr;
    }

    /**
     * Convert to a native PHP float. Precision may be lost.
     */
    public function toFloat(): float
    {
        return (float) $this->toDecimalString();
    }

    /**
     * Return the decimal string representation (e.g. "123.45", "-0.005").
     */
    public function toString(): string
    {
        return $this->toDecimalString();
    }

    /**
     * Return the plain decimal string representation.
     */
    public function __toString(): string
    {
        return $this->toDecimalString();
    }

    /**
     * Convert to BigInteger by truncating any fractional part.
     */
    public function toBigInteger(): BigInteger
    {
        if ($this->scale === 0) {
            return BigInteger::of($this->unscaledValue);
        }

        $intStr = $this->backend->divide($this->unscaledValue, $this->backend->pow('10', $this->scale));

        return BigInteger::of($intStr);
    }

    /**
     * Return the value in scientific notation (e.g. "1.23456E+2", "1.23E-3").
     */
    public function toScientific(): string
    {
        if ($this->isZero()) {
            return '0E+0';
        }

        $isNegative = \str_starts_with($this->unscaledValue, '-');
        $abs = $isNegative ? \substr($this->unscaledValue, 1) : $this->unscaledValue;

        // Normalized digits (leading zeros already removed by our invariant)
        $digits = \ltrim($abs, '0');

        if ($digits === '') {
            return '0E+0';
        }

        // Exponent: for "123456" with scale=3, exp = 6-3-1 = 2  → 1.23456E+2
        //           for "123"   with scale=5, exp = 3-5-1 = -3  → 1.23E-3
        $exponent = \strlen($digits) - $this->scale - 1;

        if (\strlen($digits) === 1) {
            $mantissa = $digits;
        } else {
            $raw = $digits[0] . '.' . \substr($digits, 1);
            $mantissa = \rtrim(\rtrim($raw, '0'), '.');
        }

        $sign = $isNegative ? '-' : '';
        $expSign = $exponent >= 0 ? '+' : '';

        return "{$sign}{$mantissa}E{$expSign}{$exponent}";
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Build the decimal string representation from unscaled value and scale.
     */
    private function toDecimalString(): string
    {
        $isNegative = \str_starts_with($this->unscaledValue, '-');
        $digits = $isNegative ? \substr($this->unscaledValue, 1) : $this->unscaledValue;

        if ($this->scale === 0) {
            return $isNegative ? '-' . $digits : $digits;
        }

        $len = \strlen($digits);

        if ($len <= $this->scale) {
            // Pad with leading zeros to fill the fractional part
            $padded = \str_pad($digits, $this->scale, '0', STR_PAD_LEFT);
            $result = '0.' . $padded;
        } else {
            $intPart = \substr($digits, 0, $len - $this->scale);
            $fracPart = \substr($digits, $len - $this->scale);
            $result = $intPart . '.' . $fracPart;
        }

        return $isNegative ? '-' . $result : $result;
    }

    /**
     * Parse a decimal string into [unscaledValue, scale].
     *
     * @return array{string, int}
     * @throws \InvalidArgumentException
     */
    private static function parseDecimalString(string $value): array
    {
        if (!\preg_match('/^-?[0-9]+(?:\.[0-9]+)?$/', $value)) {
            throw new \InvalidArgumentException("Not a valid decimal: \"{$value}\"");
        }

        $negative = \str_starts_with($value, '-');
        $abs = $negative ? \substr($value, 1) : $value;

        $dotPos = \strpos($abs, '.');

        if ($dotPos === false) {
            $intDigits = $abs;
            $fracDigits = '';
            $scale = 0;
        } else {
            $intDigits = \substr($abs, 0, $dotPos);
            $fracDigits = \substr($abs, $dotPos + 1);
            $scale = \strlen($fracDigits);
        }

        $combined = $intDigits . $fracDigits;
        $normalized = \ltrim($combined, '0');

        if ($normalized === '') {
            // Value is zero (e.g. "0", "0.000")
            return ['0', $scale];
        }

        $unscaled = $negative ? '-' . $normalized : $normalized;

        return [$unscaled, $scale];
    }

    /**
     * Normalize an unscaled integer string (remove leading zeros, handle -0).
     */
    private static function normalizeUnscaled(string $value): string
    {
        $negative = \str_starts_with($value, '-');
        $abs = $negative ? \substr($value, 1) : $value;
        $stripped = \ltrim($abs, '0');

        if ($stripped === '') {
            return '0';
        }

        return $negative ? '-' . $stripped : $stripped;
    }

    /**
     * Multiply an unscaled integer by 10^$places using this instance's backend.
     */
    private function scaleUp(string $unscaled, int $places): string
    {
        if ($places === 0) {
            return $unscaled;
        }

        return $this->backend->multiply($unscaled, $this->backend->pow('10', $places));
    }

    /**
     * Apply a rounding mode to an absolute-value quotient that has 1 extra digit.
     *
     * The last digit of $absQuotient is the first discarded digit. The result
     * has that digit removed and is rounded according to $mode.
     *
     * @param string       $absQuotient Absolute value integer string with 1 extra digit
     * @param bool         $isNegative  Whether the original value is negative
     * @param RoundingMode $mode        Rounding strategy
     */
    private function applyRounding(
        string $absQuotient,
        bool $isNegative,
        RoundingMode $mode,
    ): string {
        if ($absQuotient === '0') {
            return '0';
        }

        $lastDigit = (int) \substr($absQuotient, -1);
        // substr with 0..-1 on a non-empty string always returns string (never false)
        $truncatedRaw = \substr($absQuotient, 0, -1);
        // When absQuotient is a single digit, truncated is empty — treat as "0"
        $truncated = $truncatedRaw === '' ? '0' : $truncatedRaw;

        if ($lastDigit === 0) {
            // Exact — no rounding needed
            return $truncated;
        }

        $lastTruncatedDigit = (int) \substr($truncated, -1);

        $roundUp = match ($mode) {
            RoundingMode::DOWN => false,
            RoundingMode::UP => true,
            RoundingMode::CEILING => !$isNegative,
            RoundingMode::FLOOR => $isNegative,
            RoundingMode::HALF_UP => $lastDigit >= 5,
            RoundingMode::HALF_DOWN => $lastDigit > 5,
            RoundingMode::HALF_EVEN => $lastDigit > 5
                || ($lastDigit === 5 && $lastTruncatedDigit % 2 !== 0),
        };

        if (!$roundUp) {
            return $truncated;
        }

        return $this->backend->add($truncated, '1');
    }

    /**
     * Floor of the n-th root of a non-negative integer string ($n >= 2).
     *
     * Computed via Newton's method using only the primitives already on
     * IntegerBackend (no backend-specific nth-root function is required),
     * with a final direct-comparison correction pass that guarantees an
     * exact floor regardless of Newton's off-by-one edge cases.
     */
    private function integerNthRoot(string $a, int $n): string
    {
        if ($a === '0') {
            return '0';
        }

        // 10^ceil(digits(a) / n) always overestimates the true root: a has
        // fewer digits than 10^(n * ceil(digits(a)/n)), so a^(1/n) is
        // strictly less than 10^ceil(digits(a)/n).
        $guessExponent = \intdiv(\strlen($a) + $n - 1, $n);
        $x = $this->backend->pow('10', $guessExponent);

        while (true) {
            $xToNMinus1 = $this->backend->pow($x, $n - 1);
            $next = $this->backend->divide(
                $this->backend->add(
                    $this->backend->multiply((string) ($n - 1), $x),
                    $this->backend->divide($a, $xToNMinus1),
                ),
                (string) $n,
            );

            if ($this->backend->compare($next, $x) >= 0) {
                break;
            }

            $x = $next;
        }

        while ($this->backend->compare($this->backend->pow($this->backend->add($x, '1'), $n), $a) <= 0) {
            $x = $this->backend->add($x, '1');
        }

        while ($this->backend->compare($this->backend->pow($x, $n), $a) > 0) {
            $x = $this->backend->subtract($x, '1');
        }

        return $x;
    }

    /**
     * Greatest common divisor of two non-negative PHP integers (at least one non-zero).
     */
    private static function gcd(int $a, int $b): int
    {
        while ($b !== 0) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }

    /**
     * Coerce a value to BigDecimal.
     */
    private static function coerce(BigDecimal|BigInteger|int|string $value): self
    {
        if ($value instanceof self) {
            return $value;
        }

        if ($value instanceof BigInteger) {
            return $value->toBigDecimal();
        }

        return self::of($value);
    }
}
