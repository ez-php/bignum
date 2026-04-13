# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^1.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| `ez-php/queue` | 3310 | 6381 |
| `ez-php/rate-limiter` | — | 6382 |
| **next free** | **3311** | **6383** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/bignum

Arbitrary-precision integer and decimal arithmetic. Immutable value objects backed by `ext-bcmath` (always required) and optionally `ext-gmp` for faster integer operations.

---

## Source Structure

```
src/
  BigInteger.php              — Immutable arbitrary-precision integer value object
  BigDecimal.php              — Immutable arbitrary-precision decimal value object
  RoundingMode.php            — Enum: UP, DOWN, CEILING, FLOOR, HALF_UP, HALF_DOWN, HALF_EVEN
  DivisionByZeroException.php — Extends \ArithmeticError; thrown on division by zero
  Backend/
    IntegerBackend.php        — Interface for pluggable integer arithmetic backends
    BcMathBackend.php         — Default backend using ext-bcmath
    GmpBackend.php            — Faster optional backend using ext-gmp
tests/
  TestCase.php                — Base test case (extends PHPUnit\Framework\TestCase directly)
  BigIntegerTest.php          — Full coverage of BigInteger operations
  BigDecimalTest.php          — Full coverage of BigDecimal operations and rounding
  RoundingModeTest.php        — Enum case assertions
  Backend/
    GmpBackendTest.php        — GmpBackend tests (requires ext-gmp)
```

---

## Key Classes and Responsibilities

### BigInteger (`src/BigInteger.php`)

Final, immutable value object representing an arbitrary-precision integer.

| Concern | Detail |
|---|---|
| Storage | `private readonly string $value` — normalized integer string (no leading zeros) |
| Backend | `private readonly IntegerBackend $backend` — resolved once at construction |
| Factory | `BigInteger::of(int\|string)` validates input, normalizes, returns instance |
| Default backend | Auto-selects `GmpBackend` when `ext-gmp` is loaded, otherwise `BcMathBackend` |
| Backend override | `BigInteger::setDefaultBackend(IntegerBackend)` — used in tests to force bcmath |

All arithmetic methods (`add`, `subtract`, `multiply`, `divide`, `mod`, `pow`, `abs`, `negate`, `gcd`, `sqrt`) delegate to the backend and return a new `BigInteger`. Results are re-normalized to strip any "-0" artifacts from bcmath.

### BigDecimal (`src/BigDecimal.php`)

Final, immutable value object representing an arbitrary-precision decimal.

| Concern | Detail |
|---|---|
| Storage | `$unscaledValue: string` (integer string) + `$scale: int` |
| Invariant | `value = unscaledValue / 10^scale` |
| Always bcmath | BigDecimal uses bcmath directly — no backend interface needed for decimal arithmetic |
| Scale propagation | add/subtract → max scale; multiply → sum of scales; dividedBy → explicit scale |

`dividedBy(divisor, scale, RoundingMode)` is the primary division method. It computes an integer quotient with one extra digit of precision (for rounding) via pure bcmath integer arithmetic, then applies `applyRounding()`.

`toScale(int, RoundingMode)` is the canonical implementation. `round()` is an alias.

### IntegerBackend (`src/Backend/IntegerBackend.php`)

Thin interface separating BigInteger from its arithmetic engine. Both backends accept and return normalized integer strings.

- `BcMathBackend` — always available, uses standard `bcadd`, `bcsub`, `bcmul`, `bcdiv`, `bcmod`, `bcpow`, `bcsqrt`
- `GmpBackend` — faster for large integers; uses `gmp_*` functions; `gmp_div_r(..., GMP_ROUND_ZERO)` matches bcmath's signed-remainder convention for `mod`

### RoundingMode (`src/RoundingMode.php`)

Pure enum with no methods. Seven cases as defined by standard decimal rounding conventions:

| Case | Behaviour |
|---|---|
| `UP` | Away from zero |
| `DOWN` | Towards zero (truncate) |
| `CEILING` | Towards +∞ |
| `FLOOR` | Towards −∞ |
| `HALF_UP` | Half away from zero |
| `HALF_DOWN` | Half towards zero |
| `HALF_EVEN` | Half to nearest even digit (banker's rounding) |

---

## Design Decisions and Constraints

- **BigDecimal internal representation** — Storing `(unscaledValue, scale)` rather than a decimal string keeps all arithmetic in the integer domain. It avoids repeated string parsing and makes operations like scale alignment (`scaleUp`) trivially correct with bcmath.

- **applyRounding is private** — Rounding logic is internal to `BigDecimal`. The algorithm: compute the quotient with one extra digit via `bcdiv(..., $scale + 1 digits)`, extract the last digit, apply the rounding rule, and add 1 to the truncated result if needed. Negative values are handled by working on the absolute value and re-applying the sign — this simplifies the match statement in `applyRounding`.

- **GmpBackend auto-detection** — `BigInteger::resolveBackend()` checks `extension_loaded('gmp')` once and caches the result in the static `$defaultBackend` field. This means the backend is transparent to callers unless overridden via `setDefaultBackend()`. Tests always call `setDefaultBackend(new BcMathBackend())` in `setUp()` to ensure deterministic backend selection.

- **No `UNNECESSARY` rounding mode** — Unlike brick/math, this library does not have an `UNNECESSARY` mode (which throws if rounding would occur). Adding it would complicate the `divide` convenience method and is an edge case. Users who need exact division should structure the call scale correctly before calling `dividedBy`.

- **`divide` on BigDecimal is integer division** — `BigDecimal::divide()` delegates to `dividedBy($other, 0, RoundingMode::DOWN)`, returning a scale-0 result. This mirrors the BigInteger `divide` semantics and avoids ambiguity about what "default scale" means for division.

- **Float input in `BigDecimal::of(float)`** — Floats are converted via `sprintf('%.14F', $value)` to avoid scientific notation and obtain a decimal string with 14 fractional digits, then trailing zeros are stripped. This provides practical precision for financial and scientific inputs without silently accepting floating-point rounding artifacts. For exact decimal input, always pass a string.

- **Normalization invariant** — `unscaledValue` never has leading zeros (except the string `"0"`). All factory methods and arithmetic operations enforce this via `normalizeUnscaled()`. This ensures that unscaled-value equality implies decimal equality at the same scale.

- **Zero framework coupling** — No container, no service providers, no framework imports. The package is usable as a standalone Composer dependency.

---

## Testing Approach

- **No external infrastructure required** — All tests are in-process, pure PHP. No database, no Redis, no Docker needed to run the test suite locally via `vendor/bin/phpunit`.
- **Force bcmath in BigInteger tests** — Each `BigIntegerTest` calls `BigInteger::setDefaultBackend(new BcMathBackend())` in `setUp()` to avoid non-determinism from auto-detected backends.
- **GmpBackend tested separately** — `tests/Backend/GmpBackendTest.php` carries `#[RequiresPhpExtension('gmp')]` and is skipped automatically when GMP is not installed.
- **Rounding mode coverage** — `BigDecimalTest::testRoundingModes()` uses a data provider covering all seven modes for both positive and negative inputs, including the HALF_EVEN (banker's rounding) edge cases.
- **Immutability** — Each test that chains operations also asserts the original instance is unchanged.
- **`#[UsesClass]`** — Required because PHPUnit is configured with `beStrictAboutCoverageMetadata=false` globally, but individual modules may tighten this. Declare all indirectly used classes.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| Currency / money representation | `ez-php/money` (composes `ez-php/bignum`) |
| Locale-aware number formatting | `ez-php/money` or application layer |
| Exchange-rate conversion | `ez-php/exchange` |
| Matrix / vector algebra | Separate dedicated package |
| Statistical functions | Separate dedicated package |
| Cryptographic key generation | Security module |
| Trigonometric / transcendental functions | Out of scope (no bcmath support) |
