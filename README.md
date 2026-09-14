# ez-php/bignum

Arbitrary-precision integer and decimal arithmetic for PHP 8.5+.

Provides immutable `BigInteger` and `BigDecimal` value objects backed by PHP's `bcmath` extension — no external Composer dependencies. Both classes share a pluggable `IntegerBackend` that prefers `gmp` (faster) and falls back to `bcmath` automatically when `gmp` isn't loaded.

## Installation

```bash
composer require ez-php/bignum
```

Requires `ext-bcmath`. Optionally install `ext-gmp` for faster arithmetic — it's auto-selected when loaded (see [Backend Selection](#backend-selection)). Neither extension ships with PHP by default; both need `--enable-bcmath` / `--with-gmp` at build time or the matching OS package. See [Docker](#docker) below for a copy-pasteable install snippet.

## Quick Start

```php
use EzPhp\BigNum\BigDecimal;
use EzPhp\BigNum\BigInteger;
use EzPhp\BigNum\RoundingMode;

// BigInteger — arbitrary-precision integers
$a = BigInteger::of('99999999999999999999999999999999');
$b = BigInteger::of('1');
echo $a->add($b); // 100000000000000000000000000000000

// BigDecimal — exact decimal arithmetic (no floating-point errors)
$price  = BigDecimal::of('0.1');
$tax    = BigDecimal::of('0.2');
echo $price->add($tax); // 0.3  (not 0.30000000000000004)

// Division with explicit scale and rounding
$result = BigDecimal::of('10')->dividedBy('3', 4, RoundingMode::HALF_UP);
echo $result; // 3.3333

// Banker's rounding (HALF_EVEN)
echo BigDecimal::of('2.45')->round(1, RoundingMode::HALF_EVEN); // 2.4
echo BigDecimal::of('2.55')->round(1, RoundingMode::HALF_EVEN); // 2.6
```

## API

### BigInteger

```php
BigInteger::of(int|string $value): self
BigInteger::zero(): self
BigInteger::one(): self

// Arithmetic (all return new BigInteger)
->add(BigInteger|int|string $other): self
->subtract(BigInteger|int|string $other): self
->multiply(BigInteger|int|string $other): self
->divide(BigInteger|int|string $divisor): self      // integer division, truncates towards zero
->mod(BigInteger|int|string $divisor): self
->pow(int $exponent): self                           // exponent >= 0
->abs(): self
->negate(): self
->gcd(BigInteger|int|string $other): self
->sqrt(): self                                       // floor(sqrt(n))

// Comparison
->compareTo(BigInteger|int|string $other): int       // -1, 0, 1
->isEqualTo(...)  ->isLessThan(...)  ->isGreaterThan(...)
->isLessThanOrEqualTo(...)  ->isGreaterThanOrEqualTo(...)
->isZero(): bool  ->isPositive(): bool  ->isNegative(): bool

// Conversion
->toInt(): int           // throws OverflowException if too large
->toFloat(): float
->toString(): string
->toBigDecimal(): BigDecimal
```

### BigDecimal

```php
BigDecimal::of(int|string|float $value): self
BigDecimal::ofUnscaledValue(string $unscaledValue, int $scale): self
BigDecimal::zero(): self
BigDecimal::one(): self

// Accessors
->getScale(): int
->getUnscaledValue(): string

// Arithmetic (all return new BigDecimal)
->add(BigDecimal|BigInteger|int|string $other): self
->subtract(BigDecimal|BigInteger|int|string $other): self
->multiply(BigDecimal|BigInteger|int|string $other): self
->divide(BigDecimal|BigInteger|int|string $divisor): self       // integer division (scale 0)
->dividedBy($divisor, int $scale, RoundingMode $mode): self     // explicit scale + rounding
->mod(BigDecimal|BigInteger|int|string $divisor): self
->pow(int $exponent): self                                      // exponent >= 0
->sqrt(int $scale, RoundingMode $mode = HALF_UP): self          // requires a non-negative value
->nthRoot(int $n, int $scale, RoundingMode $mode = HALF_UP): self          // requires a non-negative value; n >= 1
->powRational(int $numerator, int $denominator, int $scale, RoundingMode $mode = HALF_UP): self
                                                                 // this ** (numerator/denominator), via nthRoot(denominator) of pow(numerator);
                                                                 // exact (no rounding) when denominator divides numerator; requires a
                                                                 // non-negative value unless the fraction reduces to an integer exponent
->abs(): self
->negate(): self

// Scale and rounding
->round(int $scale, RoundingMode $mode = HALF_UP): self
->toScale(int $scale, RoundingMode $mode = HALF_UP): self

// Comparison (scale-independent)
->compareTo(...)  ->isEqualTo(...)  ->isLessThan(...)  ->isGreaterThan(...)
->isLessThanOrEqualTo(...)  ->isGreaterThanOrEqualTo(...)
->isZero(): bool  ->isPositive(): bool  ->isNegative(): bool

// Conversion
->toInt(): int           // truncates fractional part; throws OverflowException if too large
->toFloat(): float
->toString(): string
->toBigInteger(): BigInteger
->toScientific(): string // e.g. "1.23456E+2"

// Backend management (see Backend Selection below)
BigDecimal::setDefaultBackend(IntegerBackend $backend): void
BigDecimal::getDefaultBackend(): IntegerBackend
```

### RoundingMode

| Mode | Behaviour |
|---|---|
| `UP` | Round away from zero |
| `DOWN` | Round towards zero (truncate) |
| `CEILING` | Round towards positive infinity |
| `FLOOR` | Round towards negative infinity |
| `HALF_UP` | Round half away from zero (standard) |
| `HALF_DOWN` | Round half towards zero |
| `HALF_EVEN` | Round half to nearest even digit (banker's rounding) |

### DivisionByZeroException

Thrown by `divide`, `dividedBy`, and `mod` when the divisor is zero. Extends `\ArithmeticError`.

## Backend Selection

Both `BigInteger` and `BigDecimal` auto-select the best available `IntegerBackend`, independently of each other:

1. **GmpBackend** — used when `ext-gmp` is loaded (faster for large integers)
2. **BcMathBackend** — always available fallback

To force a specific backend:

```php
use EzPhp\BigNum\Backend\BcMathBackend;

BigInteger::setDefaultBackend(new BcMathBackend());
BigDecimal::setDefaultBackend(new BcMathBackend());
```

Each class holds its own default — forcing one does not affect the other.

## Docker

Neither `gmp` nor `bcmath` ships in a stock PHP image. Add these lines to your `Dockerfile` (taken from this monorepo's own root `docker/app/Dockerfile`, which builds both for the same reason):

```dockerfile
RUN apt-get update && apt-get install -y libgmp-dev \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-install bcmath gmp
```

## Design

- **Zero framework coupling** — usable standalone without bootstrapping the application
- **Immutable** — all operations return new instances; the original is never modified
- **No `mixed`** — all parameters and return values are strictly typed
- **No external dependencies** — only `ext-bcmath` (required, always-available backend) and `ext-gmp` (optional, faster backend for both `BigInteger` and `BigDecimal`)

## License

MIT
