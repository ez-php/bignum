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
1. `sync_guidelines.php --check` — fails if any `CLAUDE.md` has drifted from this file
2. `check_test_classes.php` — fails on a duplicate test class name (all packages share the `Tests\` namespace, so a collision is a fatal error in the aggregated run, not a test failure)
3. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
4. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
   *(Note: `@PHP85Migration` does not exist yet in php-cs-fixer; `@PHP83Migration` is the highest available and is used intentionally even though the project targets PHP 8.5)*
5. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse             # PHPStan only
composer cs                  # CS Fixer only
composer test                # PHPUnit only
composer guidelines:check    # CLAUDE.md drift only
composer test-classes:check  # duplicate test class names only
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
- Concrete classes are `final` — extend behavior through composition, not inheritance. Exception-hierarchy base classes (e.g. `EzPhpException`, `HttpException`, `CacheException`) are one carve-out, since they exist specifically to be extended. A documented template-method-style base class (e.g. `Mailable`, meant to be configured via constructor-time subclassing) is the other — the owning module's `CLAUDE.md` must record it under Design Decisions.

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
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
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

**Do not edit part 1 by hand.** It is generated from `CODING_GUIDELINES.md` by
`sync_guidelines.php` at the project root:

```
php sync_guidelines.php            # rewrite every out-of-sync CLAUDE.md
php sync_guidelines.php --check    # report drift, exit 1 if any (CI / pre-commit)
```

Edit `CODING_GUIDELINES.md`, then run the script — it replaces everything before the
`# Package:` / `# Directory:` / `# Project:` heading and preserves the hand-written
section below it byte-for-byte. Editing a single copy only creates drift; before this
script existed, all 40 copies had diverged.

### 3 — Scaffolding a new module

`make_module.php` at the project root writes the required-file set and the monorepo
wiring in one step, wrapping `docker-init` for the Docker subset:

```
composer module:make <name> -- --description="..."
php make_module.php <name> --description="..." --services=mysql,redis
```

`<name>` is the kebab-case package name; the namespace is derived as
`EzPhp\<PascalCase>` unless `--namespace=` overrides it (`bignum` → `BigNum`,
`opcache` → `OPCache`, and `dotenv` → `Env` are existing exceptions the guess
gets wrong).

To bring in a module whose code already lives in its own repository instead of
generating a fresh skeleton, pass `--repo=` with a git URL:

```
php make_module.php <name> --repo=<git-url> [--namespace=Foo]
```

This runs `git submodule add <url> modules/<name>` instead of writing package
files, then applies the same monorepo wiring below. It is mutually exclusive
with `--services` and `--description` — a submodule brings its own Docker
scaffold (if any) and its own `composer.json` description. A minimal `CLAUDE.md`
stub is written only if the submodule doesn't already ship one, so
`composer guidelines:sync` has a `# Package:` heading to anchor part 1 against.

It writes `modules/<name>/` and registers the module in the four places the monorepo
needs it — root `composer.json` (`autoload.psr-4`), `phpstan.neon`, `phpunit.xml`
(test suite **and** coverage source), and `packages.sh` (alphabetical position).

Two things stay manual on purpose:

- **`CLAUDE.md` part 1** — only the `# Package:` section is generated. Run
  `composer guidelines:sync` afterwards; baking a guidelines copy into the generator
  would recreate the drift the sync script exists to prevent.
- **The host-port table below** (`--services` only) — editing it marks every
  `CLAUDE.md` copy as drifted at once, so the next `composer full` would fail for
  a brand-new module. The generator prints which ports to claim instead.

### 4 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^2.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

Pass `--services` to merge MySQL/Redis/Meilisearch service definitions directly into `docker-compose.yml` and uncomment the matching sections in `.env.example`, instead of adapting them by hand afterward:

```
vendor/bin/docker-init --services=mysql
vendor/bin/docker-init --services=redis
vendor/bin/docker-init --services=meilisearch
vendor/bin/docker-init --services=mysql,redis
```

Pass `--extensions` to merge PHP extension install blocks (apt packages plus `docker-php-ext-install`/`pecl` lines) directly into `docker/app/Dockerfile`, instead of hand-editing it afterward — supported extensions: `bcmath`, `gmp`, `gd`, `imagick`:

```
vendor/bin/docker-init --extensions=gmp,bcmath
vendor/bin/docker-init --extensions=gd,imagick
```

When run from a module directory inside this monorepo, any requested extension not already present is also merged into the shared root `docker/app/Dockerfile` — the container `composer full` at the root actually runs against, distinct from the module's own standalone image.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis, Meilisearch) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | Redis host port | `MEILISEARCH_PORT` |
|---|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 (`REDIS_PORT`) | 7700 |
| `ez-php/framework` | 3307 | — | — |
| `ez-php/` (application template) | 3308 | 6383 (`REDIS_PORT`) | — |
| `ez-php/orm` | 3309 | — | — |
| `ez-php/cache` | — | 6380 (`REDIS_HOST_PORT`) | — |
| `ez-php/queue` | 3310 | 6381 (`REDIS_HOST_PORT`) | — |
| `ez-php/rate-limiter` | — | 6382 (`REDIS_HOST_PORT`) | — |
| `ez-php/search` | — | — | 7701 |
| `ez-php/event-store` | 3311 | — | — |
| **next free** | **3312** | **6384** | **7702** |

Only set a port for services the module actually uses. Modules without external services need no port config.

> The `MEILISEARCH_PORT` column is the **host** port. Inside a Compose network the service is always reachable at `http://meilisearch:7700` regardless of the host mapping — only publish-side ports need to be unique.

> The "Redis host port" column is likewise the **host**-published port. `ez-php/cache`, `ez-php/queue`, and `ez-php/rate-limiter` map it through a separate `REDIS_HOST_PORT` env var in `docker-compose.yml`, keeping `REDIS_PORT` fixed at `6379` for in-container connections (the app container always reaches Redis at `redis:6379` over the Compose network, regardless of the host mapping) — the root project and the `ez-php/` application template are the two exceptions, since both have no host/container split and use `REDIS_PORT` for both (the template's other in-container Redis settings — `CACHE_REDIS_PORT`, `QUEUE_REDIS_PORT`, `RATE_LIMITER_REDIS_PORT` — stay fixed at `6379` regardless, same as every other module).

> This table tracks only MySQL, Redis, and Meilisearch ports — the three services shared across multiple modules where a collision is otherwise easy to introduce. `ez-php/mail`'s Mailpit service is the one other module with published host ports: SMTP `1025` and web UI `8025`, mapped through `MAILPIT_SMTP_HOST_PORT`/`MAILPIT_API_HOST_PORT` in `modules/mail/docker-compose.yml` (mirroring the `*_HOST_PORT` pattern above), documented in `modules/mail/.env.example`. It isn't a table column because no other module runs Mailpit, so there is nothing to collide with — but a new module adding its own single-use service's ports should likewise parameterize them and document the defaults in its own `.env.example` rather than adding a column here.

### 5 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/bignum

Arbitrary-precision integer and decimal arithmetic. Immutable value objects backed by `ext-bcmath` (always required). Both `BigInteger` and `BigDecimal` use a pluggable `IntegerBackend` that prefers `ext-gmp` (auto-selected when loaded) and falls back to `ext-bcmath` otherwise — each class holds its own default independently.

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
  BigIntegerTest.php          — Full coverage of BigInteger operations (forces GmpBackend)
  BigDecimalTest.php          — Full coverage of BigDecimal operations and rounding (forces GmpBackend)
  BigDecimalBcMathTest.php    — BigDecimal arithmetic paths under BcMathBackend specifically
  RoundingModeTest.php        — Enum case assertions
  Backend/
    GmpBackendTest.php        — GmpBackend tests (requires ext-gmp)
    BcMathBackendTest.php     — BcMathBackend tests (requires ext-bcmath)
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
| Backend | `private readonly IntegerBackend $backend` — same pluggable backend as `BigInteger`, resolved once at construction, own independent default |
| Scale propagation | add/subtract → max scale; multiply → sum of scales; dividedBy/nthRoot/powRational → explicit scale |

`dividedBy(divisor, scale, RoundingMode)` is the primary division method. It computes an integer quotient with one extra digit of precision (for rounding) via the backend's `divide()`, then applies `applyRounding()`.

`sqrt(scale, RoundingMode)` is `nthRoot(2, scale, RoundingMode)`. `nthRoot(n, scale, RoundingMode)` computes the floor n-th root of the unscaled value at an intermediate scale — `max(scale + 1, ceil(this.scale / n))`, chosen so the operand is only ever scaled *up* before rooting, never truncated — via the private `integerNthRoot()` helper (Newton's method on `IntegerBackend` primitives only, no backend-specific root function), then rounds down to the target scale through `toScale()`. `powRational(numerator, denominator, scale, RoundingMode)` reduces the fraction by gcd first — an exact denominator-divides-numerator case (e.g. `4/2`) delegates straight to the exact integer `pow()`, ignoring `$scale` — otherwise computes `pow(numerator)->nthRoot(denominator, scale, mode)`, powering before rooting so any imprecision is introduced once, at the end, not compounded through an early root.

`toScale(int, RoundingMode)` is the canonical rounding implementation, also used internally by `nthRoot()`. `round()` is an alias.

### IntegerBackend (`src/Backend/IntegerBackend.php`)

Thin interface separating both `BigInteger` and `BigDecimal` from their arithmetic engine. Both backends accept and return normalized integer strings. `BigInteger` and `BigDecimal` each resolve and cache their own default backend independently (two separate static fields) — forcing one via `setDefaultBackend()` does not affect the other.

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

- **BigDecimal internal representation** — Storing `(unscaledValue, scale)` rather than a decimal string keeps all arithmetic in the integer domain. It avoids repeated string parsing and makes operations like scale alignment (`scaleUp`) trivially correct via the backend.

- **Integer n-th root implemented in BigDecimal, not added to IntegerBackend** — `nthRoot()`/`powRational()` need a general n-th root, not just square root, but a `root(a, n)` method was deliberately not added to `IntegerBackend`: that interface is public (`setDefaultBackend()` accepts any implementation), so adding a method is a breaking change for any third-party backend; the Newton-based algorithm would still need writing and testing once either way; and a single backend-agnostic implementation, built only from `multiply`/`divide`/`add`/`subtract`/`pow`/`compare`, cannot silently diverge between `GmpBackend` and `BcMathBackend` the way two independent `root()` implementations could. `gmp_root()`/a bcmath equivalent stays available as a later behind-the-interface optimization if profiling ever shows `integerNthRoot()`'s Newton loop is a bottleneck — not needed at realistic scales (root degree ≤ a handful, scale ≤ tens of digits), since Newton converges quadratically.

- **BigDecimal shares BigInteger's IntegerBackend instead of a separate DecimalBackend** — every operation BigDecimal needs (add, subtract, multiply, truncated divide, pow, abs, sqrt, compare) is already on `IntegerBackend`, since decimal arithmetic here is just integer arithmetic on the unscaled value plus scale bookkeeping. A second, near-identical interface would have been a premature abstraction; `BigDecimal` resolves and caches its own default backend the same way `BigInteger` does (own static field, own `setDefaultBackend()`/`getDefaultBackend()`), but the two are independent — forcing one doesn't affect the other.

- **applyRounding is private and non-static** — Rounding logic is internal to `BigDecimal`, shared by `dividedBy`, `toScale`, and `sqrt`, and needs `$this->backend` for the final `add('1')` when rounding up. The algorithm: compute the quotient/root with one extra digit via the backend's integer arithmetic, extract the last digit, apply the rounding rule, and add 1 to the truncated result if needed. Negative values are handled by working on the absolute value and re-applying the sign — this simplifies the match statement in `applyRounding`.

- **GmpBackend auto-detection** — `resolveBackend()` (present on both `BigInteger` and `BigDecimal`, not shared code — see above) checks `extension_loaded('gmp')` once per class and caches the result in that class's static `$defaultBackend` field. This means the backend is transparent to callers unless overridden via `setDefaultBackend()`. `BigIntegerTest` forces `GmpBackend`, `BigDecimalTest` forces `GmpBackend`, and `BigDecimalBcMathTest` forces `BcMathBackend` in `setUp()` — each of the three needed because a static default can otherwise leak its value across test classes depending on run order.

- **No `UNNECESSARY` rounding mode** — Unlike brick/math, this library does not have an `UNNECESSARY` mode (which throws if rounding would occur). Adding it would complicate the `divide` convenience method and is an edge case. Users who need exact division should structure the call scale correctly before calling `dividedBy`.

- **`divide` on BigDecimal is integer division** — `BigDecimal::divide()` delegates to `dividedBy($other, 0, RoundingMode::DOWN)`, returning a scale-0 result. This mirrors the BigInteger `divide` semantics and avoids ambiguity about what "default scale" means for division.

- **Float input in `BigDecimal::of(float)`** — Floats are converted via `sprintf('%.14F', $value)` to avoid scientific notation and obtain a decimal string with 14 fractional digits, then trailing zeros are stripped. This provides practical precision for financial and scientific inputs without silently accepting floating-point rounding artifacts. For exact decimal input, always pass a string.

- **Normalization invariant** — `unscaledValue` never has leading zeros (except the string `"0"`). All factory methods and arithmetic operations enforce this via `normalizeUnscaled()`. This ensures that unscaled-value equality implies decimal equality at the same scale.

- **Zero framework coupling** — No container, no service providers, no framework imports. The package is usable as a standalone Composer dependency.

---
- **`BigDecimal` (~900 lines) is deliberately kept as one class.** It is an immutable value object; its length is the arithmetic and comparison surface (`add`/`subtract`/`multiply`/`divide`/`mod`/`pow`/`sqrt`/`nthRoot`/`powRational`/rounding, comparisons, conversions) plus a docblock on each method. All of it operates on the same private `unscaledValue`/`scale` pair, so splitting it would either leak that representation or add forwarding methods. The only separable block is the root/rational-power family (`sqrt`, `nthRoot`, `powRational`, ~130 lines); extract it into an internal `DecimalRoots` helper if that family grows (e.g. more transcendental functions), keeping the public methods on `BigDecimal`.


## Testing Approach

- **No external infrastructure required** — All tests are in-process, pure PHP. No database, no Redis, no Docker needed to run the test suite locally via `vendor/bin/phpunit`.
- **Force a specific backend in every top-level test class** — `BigIntegerTest` and `BigDecimalTest` both call `setDefaultBackend(new GmpBackend())` in `setUp()`; `BigDecimalBcMathTest` calls `BigDecimal::setDefaultBackend(new BcMathBackend())`. This avoids non-determinism from auto-detection and from one test class's static default leaking into another via run order.
- **GmpBackend tested separately** — `tests/Backend/GmpBackendTest.php` carries `#[RequiresPhpExtension('gmp')]` and is skipped automatically when GMP is not installed.
- **BcMathBackend tested directly, indirectly (BigInteger), and via BigDecimal** — `tests/Backend/BcMathBackendTest.php` exercises the backend's own methods in isolation (mirroring `GmpBackendTest`'s cases, plus a negative-operand `gcd()` case specific to bcmath's absolute-value-then-Euclidean-algorithm implementation). `BigDecimalBcMathTest` is the only place bcmath-backed `BigDecimal` arithmetic (scale alignment, `dividedBy` rounding modes, `sqrt`, `toInt`) is exercised — `BigDecimalTest` forces `GmpBackend`, so without it that code path is untested even though `ext-bcmath` is the package's actual hard requirement.
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
| Trigonometric / transcendental functions, irrational-exponent `pow()` | Out of scope: algebraic roots and rational exponents are supported (`sqrt()`, `nthRoot()`, `powRational()`); anything needing `ln`/`exp` (real-valued exponents, trig) is not, and won't be added under this package |
