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

[![Tested with Laravel 11 to 12](https://img.shields.io/badge/Tested%20with%20Laravel-11%20%7C%2012-brightgreen?maxAge=2419200)](https://github.com/pelmered/larapara/actions/workflows/tests.yml)
[![Tested on PHP 8.2 to 8.4](https://img.shields.io/badge/Tested%20on%20PHP-8.2%20|%208.3%20|%208.4-brightgreen.svg?maxAge=2419200)](https://github.com/pelmered/larapara/actions/workflows/tests.yml)
[![Tested on OS:es Linux, MacOS, Windows](https://img.shields.io/badge/Tested%20on%20lastest%20versions%20of-%20Ubuntu%20|%20MacOS%20|%20Windows-brightgreen.svg?maxAge=2419200)](https://github.com/pelmered/larapara/actions/workflows/tests.yml)

## Contents

- [What you get](#what-you-get)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Storing money in the database](#storing-money-in-the-database)
- [Formatting and parsing money](#formatting-and-parsing-money)
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
- Laravel 11.24 or higher
- [PHP Internationalization extension (intl)](https://www.php.net/manual/en/intro.intl.php)
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
| `default_currency`        | `MONEY_DEFAULT_CURRENCY`        | `USD`                    | Currency used when nothing else is set.                                                            |
| `intl_currency_symbol`    | `MONEY_INTL_CURRENCY_SYMBOL`    | `false`                  | Use ISO 4217 codes (`USD`, `EUR`, `SEK`) instead of symbols (`$`, `€`, `kr`).                      |
| `currency_provider`       | –                               | `ISOCurrenciesProvider`  | Class that provides the currency list. See [custom currency lists](#custom-currency-lists).        |
| `available_currencies`    | `MONEY_AVAILABLE_CURRENCIES`    | `[]` (all)               | Allow list of ISO codes. Comma separated in `.env`, array in the config file. Codes are trimmed and upper-cased; a code the currency provider does not know throws `UnsupportedCurrency`. |
| `excluded_currencies`     | –                               | `[]`                     | Deny list. Only applied when `available_currencies` is empty.                                      |
| `currency_column_suffix`  | `MONEY_CURRENCY_COLUMN_SUFFIX`  | `_currency`              | Suffix for the currency column belonging to an amount column.                                      |
| `currency_cache.type`     | `MONEY_CURRENCY_CACHE`          | `flexible`               | `remember`, `flexible`, `forever` or `false` to disable.                                           |
| `currency_cache.ttl`      | `MONEY_CURRENCY_CACHE_TTL`      | `[2592000, 31556926]`    | Cache TTL in seconds. `flexible` takes a `[fresh, expires]` pair — the value is served stale between the two — so set it in the config file rather than `.env`. |
| `load_crypto_currencies`  | `MONEY_LOAD_CRYPTO_CURRENCIES`  | `false`                  | Add crypto currencies to the currency list.                                                        |
| `currency_cast_to`        | `MONEY_CURRENCY_CAST`           | `LaraPara\...\Currency`  | What `CurrencyCast` returns: LaraPara's `Currency` (recommended) or `Money\Currency`.               |

### Locale

Locale is not configured in this package. `MoneyFormatter` takes the locale as an argument on every call,
so you decide per call whether to use the application locale, the user's preferred locale, or a fixed one:

```php
MoneyFormatter::format($post->price, $post->price_currency, app()->getLocale());
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
| `nullableMoney()`         | `unsignedBigInteger` (nullable)    | `decimal(12, 3)` (nullable)    |
| `smallMoney()`            | `unsignedSmallInteger` (nullable)  | `decimal(6, 3)` (nullable)     |
| `unsignedMoney()`         | `unsignedBigInteger`               | `decimal(12, 3)` (unsigned)    |

All of them also create the currency column as `string(6)` — nullable for every macro except `money()` —
and a composite index over `[price_currency, price]`. Pass a second argument to name that index:

```php
$table->money('price', 'products_price_index');
```

The decimal variants use a scale of 3, which covers every ISO currency with up to three minor units. Use
integer storage for anything with more precision than that — CLF has four minor units, and the crypto
currencies have eight.

To add a currency column to an existing amount column, add it as nullable, backfill the rows you already
have, and only then make it required:

```php
Schema::table('products', function (Blueprint $table) {
    $table->string('price_currency', 6)->nullable()->after('price');
});

DB::table('products')->whereNull('price_currency')->update(['price_currency' => 'USD']);

Schema::table('products', function (Blueprint $table) {
    $table->string('price_currency', 6)->nullable(false)->change();
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

`Pelmered\LaraPara\MoneyFormatter\MoneyFormatter` provides static methods for formatting and parsing.
The formatting and parsing methods take the locale explicitly, and amounts are in minor units unless stated
otherwise.

For all methods that take `$decimals`: a positive value is the number of decimals, and a negative value is
the number of significant digits, so `-2` on `12345678` gives `$120,000`. This only affects the formatted
output; the amount itself is left alone.

### `formatMoney`

Formats a `Money\Money` object into a localized currency string.

```php
public static function formatMoney(
    Money $money,
    string $locale,
    int $outputStyle = NumberFormatter::CURRENCY,
    int $decimals = 2,
): string
```

- `$money`: the `Money` object to format.
- `$locale`: locale string, e.g. `en_US`, `sv_SE`.
- `$outputStyle`: a [`NumberFormatter` style constant](https://www.php.net/manual/en/class.numberformatter.php#intl.numberformatter-constants).
- `$decimals`: decimals, or significant digits when negative.

```php
use Money\Currency;
use Money\Money;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

MoneyFormatter::formatMoney(new Money(123456, new Currency('USD')), 'en_US'); // $1,234.56
MoneyFormatter::formatMoney(new Money(123456, new Currency('SEK')), 'sv_SE'); // 1 234,56 kr
```

### `format`

Formats a raw amount or a `Money` object, with or without the currency symbol.

```php
public static function format(
    null|int|string|Money $value,
    Currency|MoneyCurrency $currency,
    string $locale,
    int $outputStyle = NumberFormatter::CURRENCY,
    int $decimals = 2,
    bool $showCurrencySymbol = true,
): string
```

- `$value`: minor units as int or numeric string, a `Money` object, or `null`/`''` (returns an empty string).
  An amount that is not whole minor units — `'199.99'`, `'1,234'`, `'not a number'` — throws
  `Pelmered\LaraPara\Exceptions\InvalidAmount` rather than being truncated to a wrong amount.
- `$currency`: a LaraPara `Currency` or a `Money\Currency`. Ignored when `$value` is a `Money` object.
- `$showCurrencySymbol`: set to `false` to get the amount only, placed by the minor unit of the currency.

```php
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

MoneyFormatter::format(123456, Currency::fromCode('USD'), 'en_US'); // $1,234.56
MoneyFormatter::format(123456, Currency::fromCode('SEK'), 'sv_SE'); // 1 234,56 kr

MoneyFormatter::format(123456, Currency::fromCode('USD'), 'en_US', showCurrencySymbol: false); // 1,234.56

MoneyFormatter::format(123456, Currency::fromCode('USD'), 'en_US', decimals: 0);  // $1,235
MoneyFormatter::format(123456, Currency::fromCode('USD'), 'en_US', decimals: 2);  // $1,234.56
MoneyFormatter::format(123456, Currency::fromCode('USD'), 'en_US', decimals: -2); // $1,200

$money = new \Money\Money(123456, new \Money\Currency('EUR'));
MoneyFormatter::format($money, Currency::fromCode('EUR'), 'de_DE'); // 1.234,56 €
```

### `numberFormat`

Formats a number into a localized numeric string, without any currency.

```php
public static function numberFormat(
    null|int|float|string $value,
    string $locale,
    int $decimals = 2,
    int $minorDecimals = 2,
): string
```

- `$value`: the value to format. Non-numeric input returns an empty string.
- `$decimals`: decimals, or significant digits when negative.
- `$minorDecimals`: how many minor unit decimals an integer value carries. Only used for integers.

```php
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

MoneyFormatter::numberFormat(1234.56, 'en_US');        // 1,234.56
MoneyFormatter::numberFormat('1234.56', 'en_US');      // 1,234.56
MoneyFormatter::numberFormat(123456, 'de_DE');         // 1.234,56
MoneyFormatter::numberFormat(123456, 'sv_SE');         // 1 234,56
MoneyFormatter::numberFormat('not a number', 'en_US'); // ''

MoneyFormatter::numberFormat(1234.56, 'en_US', decimals: 0);  // 1,235
MoneyFormatter::numberFormat(1234.56, 'en_US', decimals: -2); // 1,200

MoneyFormatter::numberFormat(123456, 'en_US', minorDecimals: 0);              // 123,456.00
MoneyFormatter::numberFormat(123456, 'en_US', minorDecimals: 2);              // 1,234.56
MoneyFormatter::numberFormat(123456, 'en_US', decimals: 4, minorDecimals: 4); // 12.3456
```

### `formatShort`

Formats an amount in an abbreviated format, for tables and dashboards.

```php
public static function formatShort(
    null|int|string|Money $value,
    Currency|MoneyCurrency $currency,
    string $locale,
    int $decimals = 2,
    bool $showCurrencySymbol = true,
): string
```

Amounts below 1000 of the currency's own major unit are formatted in full — 100000 minor units for USD,
1000 for JPY. The abbreviation itself uses the minor unit of the currency too, so `formatShort()` and
`format()` always agree about the magnitude of an amount.

```php
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

MoneyFormatter::formatShort(123456, Currency::fromCode('USD'), 'en_US');    // $1.23K
MoneyFormatter::formatShort(123456789, Currency::fromCode('USD'), 'en_US'); // $1.23M
MoneyFormatter::formatShort(123456789, Currency::fromCode('SEK'), 'sv_SE'); // 1,23M kr

MoneyFormatter::formatShort(123456789, Currency::fromCode('USD'), 'en_US', decimals: 1); // $1.2M
MoneyFormatter::formatShort(123456789, Currency::fromCode('USD'), 'en_US', decimals: 0); // $1M

MoneyFormatter::formatShort(123456789, Currency::fromCode('USD'), 'en_US', showCurrencySymbol: false); // 1.23M

MoneyFormatter::formatShort(99999, Currency::fromCode('USD'), 'en_US'); // $999.99
MoneyFormatter::formatShort(0, Currency::fromCode('USD'), 'en_US');     // $0.00

MoneyFormatter::formatShort(1234567, Currency::fromCode('JPY'), 'en_US'); // ¥1.23M — 0 minor units
MoneyFormatter::formatShort(1234567890, Currency::fromCode('BHD'), 'en_US'); // BHD 1.23M — 3 minor units
```

### `parseDecimal`

Parses a localized amount string into minor units. This is what you want for user input.

```php
public static function parseDecimal(
    ?string $moneyString,
    Currency|MoneyCurrency $currency,
    string $locale,
    int $decimals = 2,
): string
```

Returns the amount in minor units as a string, or an empty string for `null`/`''`. Throws
`Money\Exception\ParserException` unless the whole string is a valid number in that locale — trailing or
leading text, a second decimal point and a non-finite value are all refused rather than truncated to the
part that did parse.

One thing is forgiven: a grouping separator typed where the decimal separator belongs, which is the most
common way for user input to miss its locale. It is read as a decimal separator, so `'2,00'` in `en_US`
parses as `2.00` rather than being refused. A separator that *is* in a grouping position keeps its meaning,
so `'1,234'` in `en_US` is still one thousand two hundred and thirty four.

```php
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

MoneyFormatter::parseDecimal('1,234.56', Currency::fromCode('USD'), 'en_US'); // '123456'
MoneyFormatter::parseDecimal('1.234,56', Currency::fromCode('EUR'), 'de_DE'); // '123456'
MoneyFormatter::parseDecimal('1 234,56', Currency::fromCode('SEK'), 'sv_SE'); // '123456'
MoneyFormatter::parseDecimal('100', Currency::fromCode('USD'), 'en_US');      // '10000'
MoneyFormatter::parseDecimal('', Currency::fromCode('USD'), 'en_US');         // ''

MoneyFormatter::parseDecimal('2,00', Currency::fromCode('USD'), 'en_US');       // '200'
MoneyFormatter::parseDecimal('1,234', Currency::fromCode('USD'), 'en_US');      // '123400'

MoneyFormatter::parseDecimal('invalid', Currency::fromCode('USD'), 'en_US');
// Money\Exception\ParserException: The value must be a valid numeric value.
MoneyFormatter::parseDecimal('12 USD', Currency::fromCode('USD'), 'en_US');
// Money\Exception\ParserException: The value must be a valid numeric value.
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

Support for them is partial, since crypto currencies are not part of ISO 4217 and `intl` has no data for
them. `Currency::fromCode('BTC')` works and gives you the right minor unit (8), and `getFormattingRules()`
returns the currency code as the symbol, but formatting an amount *with* a currency symbol through
`format()` or `formatMoney()` throws `Money\Exception\UnknownCurrencyException`.

Use `numberFormat()` with the currency's minor unit and add the symbol yourself:

```php
// 100000000 minor units = 1 BTC
MoneyFormatter::numberFormat(100000000, 'en_US', decimals: 8, minorDecimals: 8).' BTC'; // 1.00000000 BTC
```

Note that `format(..., showCurrencySymbol: false)` is not a substitute here: it places the decimal point by
ISO 4217 data, which has nothing to say about a crypto currency, so it throws `UnknownCurrencyException`
just as `format()` does. For ISO currencies it uses the minor unit of the currency correctly.

The crypto list also has no currency names, so `Currency::name` is an empty string for those.

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

On Laravel 11.27.1 and higher they are also wired into Laravel's optimization commands, so
`php artisan optimize` and `php artisan optimize:clear` take care of the currency cache as well. On earlier
versions, run `money:cache` and `money:clear` yourself.

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
