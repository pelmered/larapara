# AGENTS.md

This file provides guidance to coding agents working in this repository, and is the only copy of it:
`CLAUDE.md` is a stub that imports this file, so edit this one.

## What this is

`pelmered/larapara` — a standalone Laravel package (no app skeleton) wrapping [Money PHP] with the
Laravel pieces it leaves out: a localized formatter/parser, Eloquent casts, migration macros, and a
cached currency registry. `README.md` is the reference documentation and is unusually complete — read
the relevant section before changing behaviour, and update it when behaviour changes.

## Commands

```bash
composer test                                  # Pest via testbench package:test
composer test -- tests/Unit/MoneyFormatterTest.php   # single file (args forward to Pest)
composer test -- --filter="parses"             # single test by name
composer lint                                  # pint + rector --dry-run + phpstan (level 8)
composer fix                                   # pint --fix + rector (applies changes)
composer coverage                              # clover coverage over src/
composer types                                 # Pest type coverage
composer mutate                                # Pest mutation testing over src/
```

Tests run on Testbench with sqlite `:memory:`; there is no dev database to protect.

`composer mutate` changes one operator, condition or return value in `src/` at a time and reports the
ones the suite still passed with. It answers the question line coverage cannot: whether a test would
notice the code being wrong. It needs a coverage driver (pcov is enough) and takes minutes rather
than seconds, so it is a deliberate run and not part of `composer check`. An escaped mutation is a
question, not a defect: some are worth a test, and some — a config default, a message string — are
noise to be read past.

Two directories are excluded from it, both for the same reason: they hold no behaviour, and mutating
them buries the code that does.

- `Currencies/Providers` is data — a name and a minor unit per currency, 2600 lines of it — and a
  mutated table field (`minorUnit: 2` → `3`, a currency's name → nonsense) is unkillable by any test
  worth writing. It accounted for 3481 of 4606 mutations. A provider that ever grows logic needs
  taking out of this exclusion.
- `Exceptions` is prose. Every class there is static factories returning `new self('message ' . $value)`
  with no branching, and the mutators chop one fragment off each concatenated message at a time — 137
  survivors, since tests assert the exception type and the values interpolated into it, not the
  wording. Concatenation over interpolation (house style) multiplies these.

Both are paths, not namespaces: `--ignore` matches against the file path whatever its help text says.

What is left is 941 mutations over 12 files, scoring 82% — the number CI holds to a floor of 78, the
slack being that formatted output depends on the runner's ICU. A mutation reported as *uncovered*
rather than untested is a constant declaration: not an executable line, so no test can be said to
reach it.

## Architecture

**Minor units are the currency of the codebase.** Every amount crossing an API boundary — what
`Money::getAmount()` returns, what `parseToMinor()` produces, what `formatFromMinor()` takes, what the
casts are assigned — is an integer count of a currency's minor units. Scaling happens only where the
currency's `minorUnit` is in hand, and JPY (0) and BHD (3) are the cases that break naive `* 100` code.
The database boundary is the exception: under `store.format = decimal`, `MoneyCast::toDecimal()` writes
major units (`123456` USD as the column value `1234.56`) and `fromDecimal()` reads them back, so a
migration, a raw query or a backfill against a decimal column is working in major units.

**An amount is two columns.** `LaraParaServiceProvider::moneyColumns()` (the `money()`/`nullableMoney()`/
`smallMoney()`/`unsignedMoney()` Blueprint macros) writes the amount column, a never-nullable currency
column, and an index over both. `currencyColumnFor()` is the single source of the suffix, used by the
macros and by both casts, so the two sides cannot disagree. `store.format` (`int` vs `decimal`) changes
what the macros create *and* how `MoneyCast` converts; `MoneyCast` refuses an amount the configured
`decimal_scale` would round away rather than letting the database silently lose it.

**ICU is the authority on formatting, and it is not ours.** `MoneyFormatter` (static, locale passed on
every call — no configured locale) delegates every symbol, separator and digit to `intl`/CLDR. ICU
version differs per PHP build, so exact formatted output is *not* stable across platforms: tests must
derive the volatile characters from `MoneyFormatter::getFormattingRules()` or normalize the space
characters, never assert a literal like `'1 234,56 kr'`. `tests/Pest.php` provides
`replaceNonBreakingSpaces()` for this; CI prints `INTL_ICU_VERSION` in every job.

**Parsing is deliberately asymmetric to formatting.** `parseToMinor()` accepts what `format()` and
`formatFromMinor()` write, plus two forgiveness rules (a dot read as the locale's decimal separator; a
grouping separator out of position dropped), refuses a number written in some *other* locale, and turns
both off under `strict`. The round trip is format→parse and never parse→parse: the input is a localized
amount in *major* units and the output is *minor* units, so `'100'` in USD parses to `'10000'` and
feeding the result back in scales it by the minor unit a second time. The accept/refuse boundary is
specified case-by-case in the README's parsing section — treat those examples as the spec.

**Currencies flow provider → repository → cache.** A `CurrenciesProvider` (ISO by default, optional
crypto, or a custom container-resolved class) supplies the list; `CurrencyRepository` applies
`available_currencies`/`excluded_currencies` and caches the result; `Currency::fromCode()` throws
`UnsupportedCurrency` for anything outside it. The cache hooks into `php artisan optimize` via the
`money:cache`/`money:clear` commands.

## Constraints

- Supports PHP 8.2–8.5 and Laravel 11.28 / 12 / 13 — this package **does** keep backwards
  compatibility, overriding the global "current versions only" preference. Code must work across that
  whole matrix (see `CurrencyRepository::FLEXIBLE_CREATED_KEY_PREFIX` for the shape this takes).
- No UI dependencies. No Filament, Livewire or Blade code belongs here; that lives in
  `pelmered/filament-money-field`, which builds on this package.
- Types are declared with `php-static-analysis` attributes (`#[Returns]`, `#[Throws]`), not only
  docblocks, and PHPStan runs at level 8 with full type coverage.
- Behaviour changes go in `UPGRADE.md` with the migration an affected application needs.

[Money PHP]: https://www.moneyphp.org/en/stable/
