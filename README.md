# LaraPara

Money handling for Laravel, powered by [Money PHP](https://www.moneyphp.org/en/stable/).

LaraPara is a standalone package that adds the Laravel pieces that Money PHP intentionally leaves out:
a properly localized formatter and parser, Eloquent casts for amounts and currencies, migration macros
for money columns, and a configurable, cached currency registry.

The formatter is the reason this package exists. Most money packages (and Laravel's own number helpers)
get currency symbols, decimal separators and thousands separators wrong outside of `en_US`, especially for
less common currencies. Formatting `123456` minor units as `SEK` in `sv_SE` gives you `1 234,56 kr` here,
where other solutions tend to give you something like `SEK 1234.56`, which is not how anyone in Sweden
writes an amount.

[![Latest Stable Version](https://poser.pugx.org/pelmered/larapara/v/stable)](https://packagist.org/packages/pelmered/larapara)
[![Total Downloads](https://poser.pugx.org/pelmered/larapara/d/total)](//packagist.org/packages/pelmered/larapara)
[![Monthly Downloads](https://poser.pugx.org/pelmered/larapara/d/monthly)](//packagist.org/packages/pelmered/larapara)
[![License](https://poser.pugx.org/pelmered/larapara/license)](https://packagist.org/packages/pelmered/larapara)

[![Tests](https://github.com/pelmered/larapara/actions/workflows/tests.yml/badge.svg?branch=main)](https://github.com/pelmered/larapara/actions/workflows/tests.yml)
[![Test Coverage](https://img.shields.io/endpoint?url=https://otterwise.app/badge/github/pelmered/larapara/coverage/25ef865e-5235-4775-a357-246bef38293c)](https://otterwise.app/github/pelmered/larapara)
[![Type Coverage](https://img.shields.io/endpoint?url=https://otterwise.app/badge/github/pelmered/larapara/type/25ef865e-5235-4775-a357-246bef38293c)](https://otterwise.app/github/pelmered/larapara)
[![Complexity](https://img.shields.io/endpoint?url=https://otterwise.app/badge/github/pelmered/larapara/complexity/25ef865e-5235-4775-a357-246bef38293c)](https://otterwise.app/github/pelmered/larapara)
[![Crap](https://img.shields.io/endpoint?url=https://otterwise.app/badge/github/pelmered/larapara/crap/25ef865e-5235-4775-a357-246bef38293c)](https://otterwise.app/github/pelmered/larapara)
[![PHPStan](https://img.shields.io/badge/PHPStan-level%208-brightgreen.svg?style=flat)](https://otterwise.app/github/pelmered/larapara)

[![Tested with Laravel 11 to 13](https://img.shields.io/badge/Tested%20with%20Laravel-11%20%7C%2012%20%7C%2013-brightgreen?maxAge=2419200)](https://github.com/pelmered/larapara/actions/workflows/tests.yml)
[![Tested on PHP 8.2 to 8.4](https://img.shields.io/badge/Tested%20on%20PHP-8.2%20|%208.3%20|%208.4-brightgreen.svg?maxAge=2419200)](https://github.com/pelmered/larapara/actions/workflows/tests.yml)
[![Tested on OS:es Linux, MacOS, Windows](https://img.shields.io/badge/Tested%20on%20lastest%20versions%20of-%20Ubuntu%20|%20MacOS%20|%20Windows-brightgreen.svg?maxAge=2419200)](https://github.com/pelmered/larapara/actions/workflows/tests.yml)

## Contents

- [What you get](#what-you-get)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Storing money in the database](#storing-money-in-the-database)
- [Formatting and parsing money](#formatting-and-parsing-money)
  - [Where the formatting comes from](#where-the-formatting-comes-from)
- [Validating user input](#validating-user-input)
- [Currencies](#currencies)
- [Caching](#caching)
- [Using LaraPara with Filament](#using-larapara-with-filament)
- [Roadmap](#roadmap)
- [Contributing](#contributing)

## What you get

- `MoneyFormatter` — localized formatting and parsing for any locale/currency combination, including
  abbreviated output (`$1.23M`), amounts without currency symbols, significant digits, and access to the
  raw formatting rules (symbol, separators, fraction digits) for a locale.
- `MoneyCast` and `CurrencyCast` — Eloquent casts that give you `Money\Money` and `Currency` value objects,
  and keep the amount and its currency in two columns.
- Blueprint macros — `money()`, `nullableMoney()`, `smallMoney()` and `unsignedMoney()` create the amount
  column, the currency column and an index in one call.
- A currency registry — ISO 4217 currencies out of the box, optional crypto currencies, an allow/deny list
  of currencies for your application, custom providers, and caching that hooks into `php artisan optimize`.
- No UI dependencies. This package is framework-agnostic beyond Laravel itself; there is no Filament,
  Livewire or Blade code in it.
- Modern and strict tooling: PHP 8.2+, Pest, PHPStan level 8, Pint, Rector, and full type coverage.

Every public method on `MoneyFormatter`, `Currency`, `CurrencyRepository` and `CurrencyCollection` is
considered stable API and will not change without a major version bump.

**Are you using this package to make profits? Please consider [sponsoring me](https://github.com/sponsors/pelmered).**

## Requirements

- PHP 8.2 or higher
- Laravel 11.28 or higher
- [PHP Internationalization extension (intl)](https://www.php.net/manual/en/intro.intl.php), which is
  built on [ICU](https://icu.unicode.org/) — see [Where the formatting comes from](#where-the-formatting-comes-from)
- A database column for the amount (integer for minor units, or decimal) plus a column for the currency

## Installation

```bash
composer require pelmered/larapara
```

> No stable version has been tagged yet. Until then, require `pelmered/larapara:dev-main`.

The service provider is auto-discovered. Publish the config file if you want to edit it directly:

```bash
php artisan vendor:publish --tag=larapara-config
```

## Configuration

Most things can be set from your `.env` file without publishing the config.

```env
MONEY_DEFAULT_CURRENCY=SEK
MONEY_AVAILABLE_CURRENCIES="USD,EUR,SEK"
```

| Config key                | Env variable                    | Default                  | Description                                                                                       |
|---------------------------|---------------------------------|--------------------------|---------------------------------------------------------------------------------------------------|
| `store.format`            | –                               | `int`                    | How amounts are stored: `int` (minor units, i.e. cents) or `decimal`.                             |
| `store.decimal_scale`     | –                               | `3`                      | Decimals a decimal column keeps. An amount carrying more is refused rather than rounded away.      |
| `default_currency`        | `MONEY_DEFAULT_CURRENCY`        | `USD`                    | Currency used when nothing else is set.                                                            |
| `intl_currency_symbol`    | `MONEY_INTL_CURRENCY_SYMBOL`    | `false`                  | Use ISO 4217 codes (`USD`, `EUR`, `SEK`) instead of symbols (`$`, `€`, `kr`).                      |
| `parse.strict`            | `MONEY_PARSE_STRICT`            | `false`                  | Accept only what the locale itself writes when parsing. See [strict mode](#strict-mode).           |
| `currency_provider`       | –                               | `ISOCurrenciesProvider`  | Class that provides the currency list. See [custom currency lists](#custom-currency-lists).        |
| `available_currencies`    | `MONEY_AVAILABLE_CURRENCIES`    | `[]` (all)               | Allow list of ISO codes. Comma separated in `.env`, array in the config file. Codes are trimmed and upper-cased; a code the currency provider does not know throws `UnsupportedCurrency`. |
| `excluded_currencies`     | –                               | `[]`                     | Deny list. Only applied when `available_currencies` is empty.                                      |
| `currency_column_suffix`  | `MONEY_CURRENCY_COLUMN_SUFFIX`  | `_currency`              | Suffix for the currency column belonging to an amount column.                                      |
| `currency_cache.type`     | `MONEY_CURRENCY_CACHE`          | `flexible`               | `remember`, `flexible`, `forever` or `false` to disable.                                           |
| `currency_cache.ttl`      | `MONEY_CURRENCY_CACHE_TTL`      | `[2592000, 31556926]`    | Cache TTL in seconds. `flexible` takes a `[fresh, expires]` pair — the value is served stale between the two — which needs the config file; a single number from `.env` is used for both. |
| `load_crypto_currencies`  | `MONEY_LOAD_CRYPTO_CURRENCIES`  | `false`                  | Add crypto currencies to the currency list.                                                        |
| `currency_cast_to`        | `MONEY_CURRENCY_CAST`           | `LaraPara\...\Currency`  | What `CurrencyCast` returns: LaraPara's `Currency` (recommended) or `Money\Currency`.               |

### Locale

Locale is not configured in this package. `MoneyFormatter` takes the locale as an argument on every call,
so you decide per call whether to use the application locale, the user's preferred locale, or a fixed one:

```php
MoneyFormatter::format($post->price, app()->getLocale());
```

## Storing money in the database

An amount is stored together with its currency, in two columns: `price` and `price_currency`
(the suffix is configurable with `currency_column_suffix`). This keeps multi-currency applications honest —
an amount without its currency is not a monetary value.

### Migrations

```php
Schema::create('products', function (Blueprint $table) {
    $table->id();
    $table->money('price'); // Creates `price`, `price_currency` and an index over both
});
```

Available macros, and what they create for the amount column:

| Macro                     | With `store.format = int`          | With `store.format = decimal`  |
|---------------------------|------------------------------------|--------------------------------|
| `money()`                 | `bigInteger`                       | `decimal(12, 3)`               |
| `nullableMoney()`         | `bigInteger` (nullable)            | `decimal(12, 3)` (nullable)    |
| `smallMoney()`            | `unsignedSmallInteger` (nullable)  | `decimal(6, 3)` (unsigned, nullable) |
| `unsignedMoney()`         | `unsignedBigInteger`               | `decimal(12, 3)` (unsigned)    |

A macro is unsigned only where its name says so, and nullable only where its name says so, in both
storage formats.

All of them also create the currency column as `string(12)`, `NOT NULL`, defaulting to
`default_currency`, plus a composite index over `[price_currency, price]`. The currency column is never
nullable: an amount without a unit means nothing, and a row whose amount is `null` still records the
unit it would have been in — assigning `null` to a currency attribute stores the default currency. The
column is wider than an ISO code because a currency provider may bring longer ones; the bundled crypto
list has `1000SATS` and `AUCTION`.

Pass a second argument to name the index, and a third to set the scale of a decimal column:

```php
$table->money('price', 'products_price_index');
$table->money('price', scale: 8);              // for currencies with more than three minor units
```

Each macro returns the amount column, so the chain lands where it reads as landing:

```php
$table->money('price')->nullable()->default(0);   // the amount column, not the currency column
```

The decimal scale defaults to `store.decimal_scale` (3), which covers every ISO currency except CLF and
UYW; crypto currencies carry eight. `MoneyCast` refuses an amount whose minor units the scale cannot
represent rather than letting the database round it away, so raise the scale — in the config and in the
column — or store amounts as integer minor units.

A column given a scale of its own has to tell the cast the same, since the cast is the side that refuses
the amount: `MoneyCast::class.':8'` beside `money('price', scale: 8)`. Told nothing, the cast refuses by
`store.decimal_scale`, which turns down a satoshi the column has room for.

The scale has to leave the column a digit for the amount itself, and `smallMoney()` holds six digits
where the others hold twelve — so the eight decimals a crypto amount needs do not fit in a small column
at all. A macro that would write such a column throws
`Pelmered\LaraPara\Exceptions\InvalidColumnScale` rather than leaving it to the database: MySQL and
PostgreSQL refuse `decimal(6, 8)`, while SQLite accepts it and every amount written to it, so a test
suite on SQLite would have nothing to say about the migration production refuses.

To add a currency column to an existing amount column, add it as nullable, backfill the rows you already
have, and only then make it required:

```php
Schema::table('products', function (Blueprint $table) {
    $table->string('price_currency', 12)->nullable()->after('price');
});

DB::table('products')->whereNull('price_currency')->update(['price_currency' => 'USD']);

Schema::table('products', function (Blueprint $table) {
    $table->string('price_currency', 12)->nullable(false)->default('USD')->change();
    $table->index(['price_currency', 'price']);
});
```

Adding the column as non-nullable in one step fails on PostgreSQL as soon as the table has rows, and leaves
amounts without a currency everywhere else.

### Casts

Cast the amount column with `MoneyCast` and the currency column with `CurrencyCast`:

```php
use Pelmered\LaraPara\Casts\CurrencyCast;
use Pelmered\LaraPara\Casts\MoneyCast;

protected function casts(): array
{
    return [
        'price'             => MoneyCast::class,
        'price_currency'    => CurrencyCast::class,
        'shipping'          => MoneyCast::class,
        'shipping_currency' => CurrencyCast::class,
    ];
}
```

A column the migration gave a scale of its own takes that scale here too, so the cast refuses the amounts
the column cannot hold and no others: `'price' => MoneyCast::class.':8'` beside
`$table->money('price', scale: 8)`. See [migrations](#migrations).

Reading gives you value objects:

```php
$product->price;                        // Money\Money
$product->price->getAmount();           // '123456' (minor units, as a string)
$product->price->getCurrency();         // Money\Currency

$product->price_currency;               // Pelmered\LaraPara\Currencies\Currency
$product->price_currency->getCode();    // 'SEK'
$product->price_currency->name;         // 'Swedish Krona'
$product->price_currency->minorUnit;    // 2
```

Writing a `Money` object writes both columns:

```php
use Money\Currency;
use Money\Money;

$product->price = new Money(123456, new Currency('SEK'));
$product->save(); // price = 123456, price_currency = 'SEK'
```

You can also assign an array or a plain amount. A plain amount keeps the currency already on the model,
falling back to `default_currency`:

```php
$product->price = ['amount' => 5000, 'currency' => 'EUR'];
$product->price = 5000; // Currency from the model's currency column, or the default currency
```

A plain amount is whole minor units, as an int or a numeric string. Anything else — `'1234.56'`,
`'twelve'` — throws `Pelmered\LaraPara\Exceptions\InvalidAmount` rather than storing the int it
casts to, which is the same rule the formatter holds. An amount a person typed is read by
[`parseToMoney()`](#parsetomoney), which knows the scale of the currency.

Currency codes are validated and upper-cased as they are written, by both casts. Writing a currency that
`available_currencies` does not list throws `Pelmered\LaraPara\Exceptions\UnsupportedCurrency`, since
reading such a row back would throw the same exception.

With `store.format = decimal`, the same `Money` object is stored as `1234.56` and read back as `123456`
minor units, using the minor unit of the currency — 2 for most currencies, 3 for BHD, and 0 for the likes
of JPY, which are stored as whole units (¥1000 is written as `1000`).

If you would rather not work with value objects, add an
[accessor](https://laravel.com/docs/12.x/eloquent-mutators#accessors-and-mutators) that returns the raw value:

```php
protected function price(): Attribute
{
    return Attribute::make(
        get: static fn (?string $value) => $value,
    );
}
```

## Formatting and parsing money

`Pelmered\LaraPara\MoneyFormatter\MoneyFormatter` provides static methods for formatting and parsing. Every
one of them takes the locale explicitly, and every amount is in minor units — 123456 is $1,234.56 — which
is what `MoneyCast` stores and what `Money::getAmount()` returns.

Which method to call is decided by what you have in hand:

| You have | Method | |
|---|---|---|
| a `Money` (what the casts give you) | [`format()`](#format) | `format($post->price, 'en_US')` |
| minor units and a currency | [`formatFromMinor()`](#formatfromminor) | `formatFromMinor(123456, $usd, 'en_US')` |
| either, abbreviated | [`formatShort()`](#formatshort-and-formatshortfromminor) / `formatShortFromMinor()` | `$1.23M` |
| a number that is not an amount | [`formatNumber()`](#formatnumber) | `formatNumber(1234.56, 'en_US')` |
| a localized string from a user | [`parseToMoney()`](#parsetomoney) | `'$1,234.56'` → `Money` |
| the same, as raw minor units | [`parseToMinor()`](#parsetominor) | `'1,234.56'` → `'123456'` |

Every formatting method takes two ways of saying how precise the output is, and at most one of them:
`$decimals` is a number of decimals, `$significantDigits` a number of significant digits, so
`significantDigits: 2` on `12345678` minor units gives `$120,000`. Passing both throws
`Pelmered\LaraPara\Exceptions\InvalidNumber`, since they answer the same question — as does a negative
`$decimals`, which used to be how significant digits were asked for. Neither affects the amount itself,
only how it is written, and ICU rounds half to even when writing it, as CLDR specifies.

### Where the formatting comes from

Every symbol, separator and digit in the output of this package comes from [ICU](https://icu.unicode.org/),
the internationalization library the `intl` extension is built on. ICU carries the
[CLDR](https://cldr.unicode.org/) locale database — how each locale writes numbers, which currency symbol it
uses and where it puts it — and LaraPara does not second-guess it.

**ICU is a property of your PHP build, not of this package.** The extension links against whatever ICU the
host was compiled with, so the version differs between operating systems, distributions and PHP builds:

```bash
php -r 'printf("ICU %s, CLDR %s\n", INTL_ICU_VERSION, INTL_ICU_DATA_VERSION);'
```

CLDR is revised with every ICU release, and monetary formatting is one of the things that gets revised.
Between versions a currency symbol may change (`US$` ⇄ `$`), the space before it may become a different kind
of space, and a locale may switch which apostrophe or space it groups with. **So the exact output of a
formatting call is not stable across PHP builds** — and there is no minimum ICU version to require, because
the differences are in the data rather than in what ICU can do.

What *is* stable is the round trip. `parseToMinor()` reads a locale's separators from the same ICU that
`formatFromMinor()` writes them with, so whatever your ICU produces, your ICU parses — and any member of a separator's
character class is understood regardless of which one CLDR currently names.

#### Writing tests against formatted output

The examples in this README use ordinary spaces for readability, but real output does not. `sv_SE` and
`de_DE` group with a non-breaking space (U+00A0), `fr_FR` with a narrow one (U+202F), and several locales put
directional marks around the currency symbol. Asserting a literal string will fail on somebody else's ICU —
so take the volatile part from ICU too, and assert your own logic:

```php
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

// Fragile: that space is a U+00A0 today, and the symbol may gain or lose one
expect(MoneyFormatter::formatFromMinor(123456, Currency::fromCode('SEK'), 'sv_SE'))->toBe('1 234,56 kr');

// Robust: the separators come from the same place the formatter got them
$rules = MoneyFormatter::getFormattingRules('sv_SE', Currency::fromCode('SEK'));

expect(MoneyFormatter::formatFromMinor(123456, Currency::fromCode('SEK'), 'sv_SE'))
    ->toContain('1'.$rules->groupingSeparator.'234'.$rules->decimalSeparator.'56')
    ->toContain($rules->currencySymbol);

// Or normalise the spaces away when the exact character is not what you are testing
$normalise = static fn (string $value): string => str_replace(["\u{00a0}", "\u{202f}"], ' ', $value);

expect($normalise(MoneyFormatter::formatFromMinor(123456, Currency::fromCode('SEK'), 'sv_SE')))->toBe('1 234,56 kr');
```

If you test on more than one operating system, expect ICU to differ between them — this package's own CI
prints `INTL_ICU_VERSION` in every job for exactly that reason.

### `format`

Formats a `Money\Money` object into a localized currency string.

```php
public static function format(
    Money $money,
    string $locale,
    int $outputStyle = NumberFormatter::CURRENCY,
    ?int $decimals = null,
    ?int $significantDigits = null,
    bool $showCurrencySymbol = true,
): string
```

- `$money`: the `Money` object to format. It carries both the amount and the currency it counts.
- `$locale`: locale string, e.g. `en_US`, `sv_SE`.
- `$outputStyle`: a [`NumberFormatter` style constant](https://www.php.net/manual/en/class.numberformatter.php#intl.numberformatter-constants).
- `$decimals`: how many decimals to write, defaulting to the minor unit of the currency.
- `$significantDigits`: an alternative to `$decimals`, not a companion to it. Passing both throws.
- `$showCurrencySymbol`: set to `false` to get the amount only, placed by the minor unit of the currency.

This is the call an application makes, since a money attribute cast with `MoneyCast` *is* a `Money`:

```php
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

MoneyFormatter::format($post->price, app()->getLocale());
```

```php
use Money\Currency;
use Money\Money;

MoneyFormatter::format(new Money(123456, new Currency('USD')), 'en_US'); // $1,234.56
MoneyFormatter::format(new Money(123456, new Currency('SEK')), 'sv_SE'); // 1 234,56 kr
MoneyFormatter::format(new Money(123456, new Currency('JPY')), 'en_US'); // ¥123,456

MoneyFormatter::format(new Money(123456, new Currency('USD')), 'en_US', showCurrencySymbol: false); // 1,234.56
```

### `formatFromMinor`

Formats an amount given in the minor units of a currency — 123456 in USD is $1,234.56 — which is what
every amount in this package is: what `MoneyCast` stores, what `Money::getAmount()` returns, and what
`parseToMinor()` reads a string into.

```php
public static function formatFromMinor(
    null|int|string $value,
    Currency|MoneyCurrency $currency,
    string $locale,
    int $outputStyle = NumberFormatter::CURRENCY,
    ?int $decimals = null,
    ?int $significantDigits = null,
    bool $showCurrencySymbol = true,
): string
```

- `$value`: minor units as an int or a numeric string, or `null`/`''` (returns an empty string).
  An amount that is not whole minor units — `'199.99'`, `'1,234'`, `'not a number'` — throws
  `Pelmered\LaraPara\Exceptions\InvalidAmount` rather than being truncated to a wrong amount.
  For a `Money` object, use [`format()`](#format).
- `$currency`: a LaraPara `Currency` or a `Money\Currency`, which says how many minor units make a unit.
- `$decimals`: how many decimals to write, defaulting to the minor unit of the currency, so ¥ amounts
  carry no decimals and BHD amounts carry three.
- `$significantDigits`: an alternative to `$decimals`, not a companion to it. Passing both throws.
- `$showCurrencySymbol`: set to `false` to get the amount only, placed by the minor unit of the currency.

```php
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US'); // $1,234.56
MoneyFormatter::formatFromMinor(123456, Currency::fromCode('SEK'), 'sv_SE'); // 1 234,56 kr

MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US', showCurrencySymbol: false); // 1,234.56

MoneyFormatter::formatFromMinor(12345, Currency::fromCode('JPY'), 'en_US');  // ¥12,345    (no minor unit)
MoneyFormatter::formatFromMinor(12345, Currency::fromCode('BHD'), 'en_US');  // BHD 12.345 (three)

MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US', decimals: 0);  // $1,235
MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US', decimals: 2);  // $1,234.56
MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US', significantDigits: 2); // $1,200

```

### `formatNumber`

Formats a number into a localized numeric string, without any currency.

```php
public static function formatNumber(
    null|int|float|string $value,
    string $locale,
    ?int $decimals = null,
    ?int $significantDigits = null,
): string
```

- `$value`: a **PHP** number — an int, a float, or a numeric string such as `'1234.56'`. A string written
  the way a locale writes it (`'1.234,56'`) is not one of those; reading that is
  [`parseToMinor()`](#parsetominor)'s job. `null` and `''` return an empty string, and anything else
  that is not a number throws `Pelmered\LaraPara\Exceptions\InvalidNumber` rather than rendering as
  nothing.
- `$decimals`: how many decimals to write. Defaults to as many as the value has, which is what the locale
  would print.
- `$significantDigits`: an alternative to `$decimals`, not a companion to it. Passing both throws.

This formats the number it is given and scales nothing. A count of minor units means nothing without
the currency that says how many of them make a unit, so amounts go through
[`formatFromMinor()`](#formatfromminor) — which takes that currency — and this stays a number formatter.

```php
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

MoneyFormatter::formatNumber(1234.56, 'en_US');        // 1,234.56
MoneyFormatter::formatNumber('1234.56', 'en_US');      // 1,234.56
MoneyFormatter::formatNumber(1234, 'en_US');           // 1,234
MoneyFormatter::formatNumber(1234.5, 'en_US');         // 1,234.5
MoneyFormatter::formatNumber(1234.5678, 'en_US');      // 1,234.5678 — however many it has
MoneyFormatter::formatNumber(1234.56, 'de_DE');        // 1.234,56
MoneyFormatter::formatNumber(1234.56, 'sv_SE');        // 1 234,56

MoneyFormatter::formatNumber(1234.5, 'en_US', decimals: 2);          // 1,234.50
MoneyFormatter::formatNumber(1234.5678, 'en_US', decimals: 2);       // 1,234.57
MoneyFormatter::formatNumber(1234.56, 'en_US', significantDigits: 2); // 1,200

MoneyFormatter::formatNumber(null, 'en_US');           // ''
MoneyFormatter::formatNumber('not a number', 'en_US'); // InvalidNumber
MoneyFormatter::formatNumber('1.234,56', 'en_US');     // InvalidNumber — that is a localized string
```

For an amount without a currency symbol — which is what a minor-unit value usually wants — use
`formatFromMinor(..., showCurrencySymbol: false)`, which scales by the minor unit of the currency:

```php
MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US', showCurrencySymbol: false);
// 1,234.56

MoneyFormatter::formatFromMinor(100000000, Currency::fromCode('BTC'), 'en_US', showCurrencySymbol: false);
// 1.00000000 — eight minor units, which ICU has no data for
```

### `formatShort` and `formatShortFromMinor`

Formats an amount in an abbreviated format, for tables and dashboards.

```php
public static function formatShort(
    Money $money,
    string $locale,
    ?int $decimals = null,
    ?int $significantDigits = null,
    bool $showCurrencySymbol = true,
): string

public static function formatShortFromMinor(
    null|int|string $value,
    Currency|MoneyCurrency $currency,
    string $locale,
    ?int $decimals = null,
    ?int $significantDigits = null,
    bool $showCurrencySymbol = true,
): string
```

The pair mirrors [`format()`](#format) and [`formatFromMinor()`](#formatfromminor): a `Money` carries its
own currency, a raw value is that currency's minor units.

Amounts below 1000 of the currency's own major unit are formatted in full — 100000 minor units for USD,
1000 for JPY — where the currency decides the decimals. The abbreviation itself uses the minor unit of
the currency too, so `formatShort()` and `formatFromMinor()` always agree about the magnitude of an amount, while
the abbreviated mantissa keeps two decimals whatever the currency: ¥1.23M says three digits that ¥1M
would lose.

```php
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

MoneyFormatter::formatShort($post->price, 'en_US');                                  // $1.23K

MoneyFormatter::formatShortFromMinor(123456, Currency::fromCode('USD'), 'en_US');    // $1.23K
MoneyFormatter::formatShortFromMinor(123456789, Currency::fromCode('USD'), 'en_US'); // $1.23M
MoneyFormatter::formatShortFromMinor(123456789, Currency::fromCode('SEK'), 'sv_SE'); // 1,23M kr

MoneyFormatter::formatShortFromMinor(123456789, Currency::fromCode('USD'), 'en_US', decimals: 1); // $1.2M
MoneyFormatter::formatShortFromMinor(123456789, Currency::fromCode('USD'), 'en_US', decimals: 0); // $1M

MoneyFormatter::formatShortFromMinor(123456789, Currency::fromCode('USD'), 'en_US', showCurrencySymbol: false); // 1.23M

MoneyFormatter::formatShortFromMinor(99999, Currency::fromCode('USD'), 'en_US'); // $999.99
MoneyFormatter::formatShortFromMinor(999, Currency::fromCode('JPY'), 'en_US');   // ¥999
MoneyFormatter::formatShortFromMinor(0, Currency::fromCode('USD'), 'en_US');     // $0.00

MoneyFormatter::formatShortFromMinor(1234567, Currency::fromCode('JPY'), 'en_US'); // ¥1.23M — 0 minor units
MoneyFormatter::formatShortFromMinor(1234567890, Currency::fromCode('BHD'), 'en_US'); // BHD 1.23M — 3 minor units
```

### `parseToMinor`

Parses a localized amount string into minor units. This is what you want for user input.

```php
public static function parseToMinor(
    ?string $moneyString,
    Currency|MoneyCurrency $currency,
    string $locale,
    ?bool $strict = null,
): string
```

Returns the amount in minor units as a numeric string — which is what a `Money` holds and what the casts
store, and it carries an amount past the range an int keeps losslessly — or an empty string for
`null`/`''`. Surrounding whitespace is ignored. The amount is rounded half up to the minor unit of the
currency, so `'1.005'` is `101` cents.

#### What is accepted

**The currency may be written beside the amount**, because that is what
[`formatFromMinor()`](#formatfromminor) writes — a field that displays `$1,234.56` posts that string back,
and a parser that refuses its own output is a trap. Its symbol or its ISO code, in front or behind, with
or without a space:

```php
MoneyFormatter::parseToMinor('$1,234.56', Currency::fromCode('USD'), 'en_US');           // '123456'
MoneyFormatter::parseToMinor('-$1,234.56', Currency::fromCode('USD'), 'en_US');          // '-123456'
MoneyFormatter::parseToMinor('12 USD', Currency::fromCode('USD'), 'en_US');              // '1200'
MoneyFormatter::parseToMinor('USD 12', Currency::fromCode('USD'), 'en_US');              // '1200'
MoneyFormatter::parseToMinor("1\u{a0}234,56\u{a0}kr", Currency::fromCode('SEK'), 'sv_SE'); // '123456'
MoneyFormatter::parseToMinor('1 234,56 kr', Currency::fromCode('SEK'), 'sv_SE');         // '123456'
```

It has to be *that* currency, written once. ICU reads any currency's symbol, so a mismatch would silently
convert:

```php
MoneyFormatter::parseToMinor('€10', Currency::fromCode('USD'), 'en_US');       // ParserException
MoneyFormatter::parseToMinor('12 EUR', Currency::fromCode('USD'), 'en_US');    // ParserException
MoneyFormatter::parseToMinor('12 USD USD', Currency::fromCode('USD'), 'en_US'); // ParserException
```

**Otherwise the whole string has to be a number.** ICU stops reading at the first character it cannot make
sense of, so anything left over means the string was not an amount, and it throws
`Money\Exception\ParserException` rather than returning the part that did parse:

```php
MoneyFormatter::parseToMinor('12 dollars', Currency::fromCode('USD'), 'en_US'); // ParserException
MoneyFormatter::parseToMinor('1.2.3', Currency::fromCode('USD'), 'en_US');      // ParserException
MoneyFormatter::parseToMinor('0x1A', Currency::fromCode('USD'), 'en_US');       // ParserException
MoneyFormatter::parseToMinor('NaN', Currency::fromCode('USD'), 'en_US');        // ParserException
```

**Separators are forgiven**, since they are the most common way for user input to miss its locale. A string
the locale itself refuses gets a second reading under two rules before being rejected:

| Rule | Why | Example |
|---|---|---|
| A dot becomes the locale's decimal separator | A dot means a decimal point on nearly every keyboard, spreadsheet and programming language | `'1.5'` in `sv_SE` and `de_DE` alike is `150`, not `1500` |
| A grouping separator out of position is dropped | It carries no value of its own | `'2,00'` in `en_US` is `20000`, the same amount as `'200'` |

A separator that *is* in a grouping position always keeps its meaning, so `'1.234'` in `de_DE` and `'1,234'`
in `en_US` are both one thousand two hundred and thirty four. Only a separator the locale has already refused
reaches the rules above, which is why the two never contradict each other.

A locale's grouping separator is one member of a class, and a keyboard usually produces a different member of
it: `sv_SE` groups with a no-break space, `fr_FR` with a narrow one, `de_CH` with a right single quotation
mark, while people type a plain space and a plain apostrophe. Any member of the class is read as grouping, so
`'1 234,56'` and `"1'234.56"` are understood as readily as the characters ICU would have written:

```php
MoneyFormatter::parseToMinor('2 00', Currency::fromCode('SEK'), 'sv_SE');   // '20000', typed with a space
MoneyFormatter::parseToMinor("2'00", Currency::fromCode('CHF'), 'de_CH');   // '20000', typed with an apostrophe
```

The class is the one the locale's own separator belongs to, not a free-for-all — a space is not a grouping
separator in a locale that groups with a comma.

#### What is still refused

The rules read a separator that is merely out of place. They do not rescue a string that is a number in some
*other* locale, because guessing which one would invent an amount:

```php
// Grouping never follows the decimal separator, so this is malformed rather than out of position
MoneyFormatter::parseToMinor('1.234,56', Currency::fromCode('USD'), 'en_US'); // ParserException
// A US number in a Swedish or German field stays ambiguous
MoneyFormatter::parseToMinor('1,234.56', Currency::fromCode('SEK'), 'sv_SE'); // ParserException
MoneyFormatter::parseToMinor('1,234.56', Currency::fromCode('EUR'), 'de_DE'); // ParserException
```

#### Strict mode

Forgiving a separator is right for a form a person fills in, and wrong for a CSV import or an API endpoint,
where you would rather the client fixed its payload. `strict` turns the second reading off, so only what the
locale itself writes is accepted:

```php
MoneyFormatter::parseToMinor('1.5', Currency::fromCode('SEK'), 'sv_SE');               // '150'
MoneyFormatter::parseToMinor('1.5', Currency::fromCode('SEK'), 'sv_SE', strict: true); // ParserException

MoneyFormatter::parseToMinor('1 234,56', Currency::fromCode('SEK'), 'sv_SE', strict: true); // '123456'
MoneyFormatter::parseToMinor('1234,56', Currency::fromCode('SEK'), 'sv_SE', strict: true);  // '123456'
```

It defaults to `config('larapara.parse.strict')`, which ships as `false`. Since it is an argument as well as
a config key, a lenient form and a strict import can live in the same application. Note that strict mode
does not require a grouping separator, and does not insist on the exact space character the locale prefers
between the digits — ICU accepts an ordinary space where `sv_SE` writes a non-breaking one, in both modes.

An amount written with its currency is accepted in strict mode too, but only exactly as this
configuration writes it: the notation `intl_currency_symbol` selects, where the locale puts it, with the
space the locale uses. Every other placement, notation and space is forgiveness, so strict mode refuses
it:

```php
// with intl_currency_symbol = false, which is the default
MoneyFormatter::parseToMinor('$1,234.56', Currency::fromCode('USD'), 'en_US', strict: true); // '123456'

MoneyFormatter::parseToMinor('USD 12', Currency::fromCode('USD'), 'en_US', strict: true);    // ParserException
MoneyFormatter::parseToMinor('12 USD', Currency::fromCode('USD'), 'en_US', strict: true);    // ParserException
MoneyFormatter::parseToMinor('12 USD', Currency::fromCode('USD'), 'en_US');                  // '1200'

// sv_SE writes a non-breaking space before kr
MoneyFormatter::parseToMinor('1 234,56 kr', Currency::fromCode('SEK'), 'sv_SE', strict: true); // ParserException
MoneyFormatter::parseToMinor('1 234,56 kr', Currency::fromCode('SEK'), 'sv_SE');              // '123456'
```

```php
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

MoneyFormatter::parseToMinor('1,234.56', Currency::fromCode('USD'), 'en_US'); // '123456'
MoneyFormatter::parseToMinor('1.234,56', Currency::fromCode('EUR'), 'de_DE'); // '123456'
MoneyFormatter::parseToMinor('1 234,56', Currency::fromCode('SEK'), 'sv_SE'); // '123456'
MoneyFormatter::parseToMinor('100', Currency::fromCode('USD'), 'en_US');      // '10000'
MoneyFormatter::parseToMinor('', Currency::fromCode('USD'), 'en_US');         // ''

// The separator rules
MoneyFormatter::parseToMinor('2,00', Currency::fromCode('USD'), 'en_US');       // '20000' — same as '200'
MoneyFormatter::parseToMinor('1,234', Currency::fromCode('USD'), 'en_US');      // '123400' — grouping kept
MoneyFormatter::parseToMinor('1.5', Currency::fromCode('EUR'), 'de_DE');        // '150' — dot as decimal
MoneyFormatter::parseToMinor('1.234', Currency::fromCode('EUR'), 'de_DE');      // '123400' — dot as grouping
MoneyFormatter::parseToMinor('1.5', Currency::fromCode('SEK'), 'sv_SE');        // '150'
MoneyFormatter::parseToMinor('1 234.56', Currency::fromCode('SEK'), 'sv_SE');   // '123456'

// The minor unit of the currency decides the scale, and the rounding
MoneyFormatter::parseToMinor('1234', Currency::fromCode('JPY'), 'en_US');       // '1234' — 0 minor units
MoneyFormatter::parseToMinor('1.234', Currency::fromCode('BHD'), 'en_US');      // '1234' — 3 minor units
MoneyFormatter::parseToMinor('1.005', Currency::fromCode('USD'), 'en_US');      // '101' — rounded half up

MoneyFormatter::parseToMinor('invalid', Currency::fromCode('USD'), 'en_US');
// Money\Exception\ParserException: The value must be a valid numeric value.
MoneyFormatter::parseToMinor('12 dollars', Currency::fromCode('USD'), 'en_US');
// Money\Exception\ParserException: The value must be a valid numeric value.
```

### `parseToMoney`

Reads a localized amount string into a `Money` object, which is the inverse of [`format()`](#format):
what one writes, the other reads back.

```php
public static function parseToMoney(
    ?string $moneyString,
    Currency|MoneyCurrency|string $currency,
    string $locale,
    ?bool $strict = null,
): ?Money
```

- `$currency`: a LaraPara `Currency`, a `Money\Currency`, or a currency code. A code is resolved through
  the registry, so one `available_currencies` does not list throws `UnsupportedCurrency` here rather than
  being stored and read back as an exception later. A code also carries the minor unit of a currency ICU
  has no data for, which a bare `Money\Currency` does not.
- Everything else behaves as [`parseToMinor()`](#parsetominor), which this method reads the amount with.
- Returns `null` for `null`/`''`, since no amount is not an amount of anything.

```php
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

$price = MoneyFormatter::parseToMoney($request->input('price'), $request->input('currency'), app()->getLocale());

$post->price = $price;  // MoneyCast stores the amount and the currency together
```

```php
MoneyFormatter::parseToMoney('$1,234.56', 'USD', 'en_US');   // Money(123456, USD)
MoneyFormatter::parseToMoney('1 234,56 kr', 'SEK', 'sv_SE'); // Money(123456, SEK)
MoneyFormatter::parseToMoney('', 'USD', 'en_US');            // null
MoneyFormatter::parseToMoney('1,234.56', 'GBP', 'en_US');    // UnsupportedCurrency
```

### `getFormattingRules`

Returns the formatting rules for a locale and currency, as a `CurrencyFormattingRules` object. Useful when
you need to build your own input mask or formatter.

```php
public static function getFormattingRules(
    string $locale,
    Currency|MoneyCurrency $currency,
): CurrencyFormattingRules
```

```php
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

$rules = MoneyFormatter::getFormattingRules('en_US', Currency::fromCode('USD'));
$rules->currencySymbol;    // $
$rules->decimalSeparator;  // .
$rules->groupingSeparator; // ,
$rules->fractionDigits;    // 2

$rules = MoneyFormatter::getFormattingRules('sv_SE', Currency::fromCode('SEK'));
$rules->currencySymbol;    // kr
$rules->decimalSeparator;  // ,
$rules->groupingSeparator; //   (non-breaking space)
$rules->fractionDigits;    // 2
```

### `getDefaultCurrency`

Returns the currency from `config('larapara.default_currency')` as a `Currency` object.

```php
public static function getDefaultCurrency(): Currency
```

```php
MoneyFormatter::getDefaultCurrency()->getCode(); // 'USD'
```

## Validating user input

Two validation rules, so an amount and a currency coming from a request can be checked before anything is
parsed or saved.

```php
use Pelmered\LaraPara\Rules\MoneyString;
use Pelmered\LaraPara\Rules\SupportedCurrency;

$validated = $request->validate([
    'price'          => ['required', new MoneyString($request->input('price_currency'))],
    'price_currency' => ['required', new SupportedCurrency],
]);
```

Both rules pass an empty value, so `required` and `nullable` stay in charge of whether a field may be empty.

### `MoneyString`

Validates that a string is an amount `parseToMinor()` can read, in the same locale and under the same rules.

```php
new MoneyString(
    mixed $currency = null,    // a Currency, a Money\Currency or a code; defaults to config('larapara.default_currency')
    ?string $locale = null,    // defaults to app()->getLocale()
    ?bool $strict = null,      // defaults to config('larapara.parse.strict')
)
```

```php
// Lenient, as parseToMinor is by default
'price' => [new MoneyString('SEK', 'sv_SE')],              // '1.5' passes, and parses as 1,50

// Strict, for an import or an API
'price' => [new MoneyString('SEK', 'sv_SE', strict: true)], // '1.5' fails
```

The failure message shows the shape it expects rather than describing it, built from the locale and currency
it was given — *"The price field must be a valid amount, such as 1 234,56."*

Whether the currency itself is supported is `SupportedCurrency`'s business: an unsupported one here does not
fail the amount, because the scale of a currency does not decide whether a string is a number. That way a bad
currency and a bad amount each report their own problem, and passing `$request->input('price_currency')`
straight in cannot turn into an exception — the parameter is `mixed` for that reason, since a client is free
to send an array where a code was expected, and anything that is not a code is read as the default currency.

### `SupportedCurrency`

Validates that a currency code is one this application supports — that is, one `available_currencies` allows.
The code is trimmed and upper-cased first, exactly as the casts normalize it on write, so anything this rule
passes can be stored.

```php
new SupportedCurrency(?array $currencyCodes = null)
```

```php
'price_currency' => ['required', new SupportedCurrency],

// Offer fewer currencies on one form than the application supports
'payout_currency' => ['required', new SupportedCurrency(['USD', 'EUR'])],
```

A list narrowed to a code `available_currencies` does not have would leave a field nothing could satisfy, so
it throws `UnsupportedCurrency` where it is written instead of failing every submission.

Build the field's options from the same list, and the two cannot drift apart:

```php
use Pelmered\LaraPara\Currencies\CurrencyRepository;

CurrencyRepository::getAvailableCurrencies()->toSelectArray();
// ['USD' => 'USD - US Dollar', 'EUR' => 'EUR - Euro', 'SEK' => 'SEK - Swedish Krona']
```

### Messages

The messages live in `resources/lang/en/validation.php` under the `larapara` namespace. Publish them to
override:

```bash
php artisan vendor:publish --tag="larapara-translations"
```

## Currencies

`Pelmered\LaraPara\Currencies\Currency` is a small value object with the currency code, its name and its
minor unit:

```php
use Pelmered\LaraPara\Currencies\Currency;

$currency = Currency::fromCode('sek'); // Case insensitive
$currency->code;      // 'SEK'
$currency->name;      // 'Swedish Krona'
$currency->minorUnit; // 2

$currency->getCode();          // 'SEK'
(string) $currency;            // 'SEK'
$currency->toMoneyCurrency();  // Money\Currency
$currency->equals(Currency::fromCode('USD')); // false

Currency::fromMoney($product->price);                   // From a Money object
Currency::fromMoneyCurrency(new \Money\Currency('SEK')); // From a Money\Currency object
```

`Currency::fromCode()` throws `Pelmered\LaraPara\Exceptions\UnsupportedCurrency` if the code is not in the
list of available currencies.

### Available currencies

`CurrencyRepository` resolves the currencies your application accepts:

```php
use Pelmered\LaraPara\Currencies\CurrencyRepository;

CurrencyRepository::getAvailableCurrencies(); // CurrencyCollection, keyed by currency code
CurrencyRepository::isValidCode('SEK');       // true
CurrencyRepository::isValid($currency);       // true
CurrencyRepository::clearCache();
```

Limit which currencies are available in the config file:

```php
'available_currencies' => ['USD', 'EUR', 'SEK'],
```

or in your `.env`:

```env
MONEY_AVAILABLE_CURRENCIES="USD,EUR,SEK"
```

Leave `available_currencies` empty to allow all currencies from the provider, and use
`excluded_currencies` to remove individual ones.

The returned `CurrencyCollection` is an Illuminate collection of `Currency` objects with one extra method
for building select inputs:

```php
CurrencyRepository::getAvailableCurrencies()->toSelectArray();
// ['USD' => 'USD - US Dollar', 'EUR' => 'EUR - Euro', 'SEK' => 'SEK - Swedish Krona']
```

### Crypto currencies

A crypto currency list ships with the package, but is not loaded by default:

```env
MONEY_LOAD_CRYPTO_CURRENCIES=true
```

Crypto currencies are not part of ISO 4217, so their minor unit comes from the package's own registry
rather than from ICU — `Currency::fromCode('BTC')` gives you the right one (8), and formatting and parsing
both scale by it:

```php
// 100000000 minor units = 1 BTC
MoneyFormatter::formatFromMinor(100000000, Currency::fromCode('BTC'), 'en_US');
// BTC 1.00000000

MoneyFormatter::formatFromMinor(100000000, Currency::fromCode('BTC'), 'en_US', showCurrencySymbol: false);
// 1.00000000

MoneyFormatter::parseToMinor('1.00000000', Currency::fromCode('BTC'), 'en_US'); // '100000000'
```

ICU has no *symbol* for a currency outside ISO 4217, so it writes the code where the symbol would go and
places it the way the locale places a symbol. That is the only part of the output crypto support is
missing, and it is what `getFormattingRules()->currencySymbol` reports too.

The code comes out in full whatever its length. ICU carries a currency as a three-character code —
truncating a longer one and refusing a shorter one — so the 181 bundled codes that are not three
characters (`1000SATS`, `AUCTION`, `AI`) are handed to it as the symbol instead, which is the same thing
it writes for the rest. Parsing reads that notation back in strict mode as well as lenient, since it is
what this package writes and ICU has no reading of these codes to be strict about:

```php
MoneyFormatter::formatFromMinor(100000000, Currency::fromCode('1000SATS'), 'en_US');
// 1000SATS 1.00000000

MoneyFormatter::parseToMinor('1000SATS 1.00000000', Currency::fromCode('1000SATS'), 'en_US', strict: true);
// '100000000'
```

The minor unit is read from the code, so it does not matter which object you hold: a bare
`Money\Currency` is looked up in the registry the same way a LaraPara `Currency` carries it. A code no
currency list has — one you built a `Money\Currency` for by hand — has nothing to read, and falls back to
two decimals in both directions.

The crypto list carries no names of its own, so `Currency::name` is the code for those — `BTC - BTC` in a
select array.

Crypto amounts do not fit `store.format = decimal` at the default scale either: eight minor units need
`store.decimal_scale = 8` and a column to match, or integer storage. `MoneyCast` throws
`InvalidAmount` rather than letting the database round the amount away.

### Custom currency lists

To provide your own currency list, implement the `CurrenciesProvider` interface and point the config at it:

```php
use Pelmered\LaraPara\Currencies\Providers\CurrenciesProvider;

class MyCurrenciesProvider implements CurrenciesProvider
{
    public function loadCurrencies(): array
    {
        return [
            'XYZ' => [
                'alphabeticCode' => 'XYZ',
                'currency'       => 'My Currency',
                'minorUnit'      => 2,
                'numericCode'    => 999,
            ],
        ];
    }
}
```

```php
'currency_provider' => MyCurrenciesProvider::class,
```

The provider is resolved from the container, so you can inject dependencies into it — for example to load
currencies from your database or an API.

## Caching

The currency list is cached with a flexible cache by default. Two commands are included:

```bash
php artisan money:cache    # Build the currency cache (add -v to list the cached currencies)
php artisan money:clear    # Clear it
```

They are also wired into Laravel's optimization commands, so `php artisan optimize` and
`php artisan optimize:clear` take care of the currency cache as well.

Set `MONEY_CURRENCY_CACHE=false` to disable caching, for example while developing a custom provider.

## Using LaraPara with Filament

LaraPara has no Filament dependency. If you want localized money input fields, table columns and infolist
entries for Filament, use [pelmered/filament-money-field](https://github.com/pelmered/filament-money-field),
which is built on top of this package. Anything you configure here (default currency, available currencies,
storage format, column suffix) applies there too.

The `CurrencySymbolPlacement` enum and the `EnumHelpers` trait live in this package as well; they are the
shared pieces that UI layers like the Filament package build their symbol placement options on.

## Roadmap

Open an issue if you want any of this, or something else. It helps a lot if you describe your use case.

- Currency conversion. Store amounts in a base currency and convert to the user's preferred currency on
  the fly.

## Contributing

I'm very happy to receive PRs with fixes or improvements. If it is a new feature, it is probably best to
open an issue first, so I can give feedback and see if it fits in this package — especially for larger
features, so you don't waste your time.

When submitting a PR, I appreciate if you:

- Add tests for your code. Not a strict requirement. Ask for guidance if you are unsure.
- Run the test suite and make sure it passes with `composer test`.
- Check the code with `composer lint` (PHPStan and Pint). `composer fix` fixes most style issues for you.

## Credits

Built on [Money PHP](https://www.moneyphp.org/en/stable/) by the moneyphp team, and on the ISO 4217 and
crypto currency data that ships with it.

## License

[MIT](LICENSE)
