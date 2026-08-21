
# Behaviour changes in the 2.* line

These come from a correctness review. Most applications need nothing, but each one changes an output or
turns a silent wrong answer into an exception, so check the ones that apply to you.

## Stored amounts, with `store.format = decimal`

Three defects in `MoneyCast` are fixed. All of them are specific to decimal storage — integer storage,
which is the default, was never affected.

- **Currencies with no minor unit are no longer scaled by two.** ¥1000 used to be written to the column as
  `10.000` and is now written as `1000.000`. **Rows written before the upgrade need migrating**, or they
  will read back a hundred times too small. There are 30 such currencies in the bundled ISO list, JPY,
  KRW, ISK, CLP and VND among them:

  ```php
  use Pelmered\LaraPara\Currencies\Currency;
  use Pelmered\LaraPara\Currencies\CurrencyRepository;

  $zeroMinorUnit = CurrencyRepository::getAvailableCurrencies()
      ->filter(fn (Currency $currency): bool => $currency->minorUnit === 0)
      ->keys()
      ->all();

  DB::table('products')->whereIn('price_currency', $zeroMinorUnit)->update([
      'price' => DB::raw('price * 100'),
  ]);
  ```

  Currencies with 3 minor units, such as BHD, were already stored correctly and must be left alone.

- **Reading an amount back no longer loses a minor unit.** The stored decimal was scaled back with a bare
  `(int)` cast over a float multiplication, so roughly one amount in twenty truncated: `19.99` read back as
  `1998` instead of `1999`. Nothing needs migrating — the column always held the right value — but any
  figure your application copied out of a read is a cent short.

  The read moves the decimal point rather than multiplying, the way the write places it, so an amount a
  double cannot hold exactly — above 2\*\*53 minor units, which needs a wider column than the macros
  write — comes back as it went in rather than a unit or two off.

- **Assigning `null` to a nullable money column now stores `NULL`.** It used to store `0.000`, so "no price
  set" and "free" were indistinguishable. If you have rows that were meant to be null, they are zeros in
  the column now and only you can tell which is which.

## The currency cache keeps the TTL it is configured with

The TTL was passed to the cache in whatever shape the config or the environment produced it in, and
neither shape failed loudly when it was the wrong one for the type. `MONEY_CURRENCY_CACHE_TTL=3600` with
the default `flexible` type was read one character at a time, so the entry lived 6 seconds instead of an
hour, and `MONEY_CURRENCY_CACHE=remember` with the shipped array default cached for 1 second. Both are
coerced to the shape the type takes now, so a cache that appeared to do nothing starts working — if you
tuned anything around the old behaviour, check the TTL you have configured.

`money:clear` also forgets the companion key the `flexible` type stores the age of the entry under, and
`money:cache` says so when the cache is disabled instead of reporting currencies it did not write.

## Crypto currencies have a name

Every currency from `CryptoCurrenciesProvider` had an empty name, so `toSelectArray()` rendered
`BTC - `. The code stands in for the name it does not have, giving `BTC - BTC`.

## `intl_currency_symbol` applies to every currency style

It was applied to `NumberFormatter::CURRENCY` and `CURRENCY_ACCOUNTING` only, and silently dropped for
the other ICU currency styles — the cash and standard variants, which have no PHP constants — even
though their patterns carry the same currency placeholder. Any style whose pattern has one now honours
the setting.

## Amounts are formatted with the fraction digits of their currency

`format()`, `formatFromMinor()` and the `formatShort()` pair took two decimals for every currency and now
take the minor unit of the currency, which changes the output for 39 of the 179 bundled currencies: `¥1,000.00`
is `¥1,000`, and `BHD 1,234.57` for 1234567 fils is `BHD 1,234.567`. Pass `decimals:` to get the old
number back for a specific call. `parseToMinor()` already scaled by the currency, so formatting and
parsing now agree in both directions.

`getFormattingRules()->fractionDigits` reports the minor unit of the currency it is given rather than
ICU's, which is 2 for anything outside ISO 4217 — a crypto currency with eight minor units said two.

The abbreviated part of `formatShort()` still carries two decimals whatever the currency, since ¥1.23M
says three digits that ¥1M would lose. Below the abbreviation threshold the currency decides.

## `parseToMinor()` no longer takes `$decimals`

The parameter never did anything: a number formatter reads every decimal a string carries whatever its
fraction digits are set to, so the scale always came from the currency. Calls that passed it positionally
have to drop it — `parseToMinor($value, $currency, $locale, $decimals, $strict)` becomes
`parseToMinor($value, $currency, $locale, $strict)`.

## Amounts and numbers are formatted by different methods

`formatNumber()` had two jobs, and the PHP type of its value decided which one: `123456` was `1,234.56`
(minor units) while `'123456'` was `123,456.00` (a plain number) — the same amount a hundred times apart,
and `Money::getAmount()` returns the string form. Neither job is gone, but each has its own method now,
named for what it takes:

| Was | Is | Takes |
|---|---|---|
| `formatMoney()` | `format()` | a `Money`, which carries its own currency |
| `format()` | `formatFromMinor()` | minor units, plus the currency that scales them |
| `formatShort()` with a `Money` | `formatShort()` | a `Money` |
| `formatShort()` with a raw value | `formatShortFromMinor()` | minor units, plus a currency |
| `numberFormat()` | `formatNumber()` | a number, formatted as given |

- `format()` is the old `formatMoney()`, and it takes `showCurrencySymbol` now as well. It is the call an
  application makes, since a `MoneyCast` attribute *is* a `Money`.
- `formatFromMinor()` is the old `format()`: the same arguments, minus the `Money` it no longer accepts,
  and named for the unit it takes — neither `format` nor `formatAmount` said that 123456 means $1,234.56.
- `formatNumber()` scales nothing: `formatNumber(1234.56, 'en_US')` is `1,234.56` and
  `formatNumber(1234, 'en_US')` is `1,234`. It keeps the decimals the value has, however many there are —
  `formatNumber(1234.5678, 'en_US')` is `1,234.5678`, where ICU's own default stops at three. Its
  `$minorDecimals` parameter is gone, and so is the `minorUnits` argument and the
  `number_format.minor_units` config key of the previous iteration.

```php
// a Money — what MoneyCast gives you
MoneyFormatter::format($post->price, 'en_US');                                     // $1,234.56
MoneyFormatter::format($post->price, 'en_US', showCurrencySymbol: false);           // 1,234.56

// minor units and the currency that counts them
MoneyFormatter::formatFromMinor(123456, $usd, 'en_US');                            // $1,234.56
MoneyFormatter::formatFromMinor(123456, $usd, 'en_US', showCurrencySymbol: false); // 1,234.56

// a number that is not an amount
MoneyFormatter::formatNumber(1234.56, 'en_US');                                    // 1,234.56
```

Nothing guesses any more: a `Money` carries its unit, a currency argument names it, and no currency at all
means the value is a plain number.

`formatShort()` took `Money|int|string` beside a separate currency argument, and used the amount of the
`Money` with the minor unit and symbol of the argument — so a mismatched pair formatted a different
currency at a different magnitude, and it disagreed with `format()`, which ignored the argument. Each half
is its own method now, with one input shape and no argument to ignore.

## Precision is asked for by name, and `formatNumber()` formats numbers

Every formatting method documented "a negative `$decimals` means significant digits", which no signature
said and no reader could guess. It is a named `$significantDigits` parameter now, and a negative
`$decimals` throws `Pelmered\LaraPara\Exceptions\InvalidNumber`:

```php
MoneyFormatter::formatFromMinor(123456, $usd, 'en_US', significantDigits: 2); // $1,200
MoneyFormatter::formatFromMinor(123456, $usd, 'en_US', decimals: -2);         // InvalidNumber
```

The two are alternatives rather than companions — ICU discards the fraction digits when both are set —
so passing both throws as well.

`formatNumber()` also stops behaving like a money formatter in two ways:

- **It keeps the decimals the value has.** `formatNumber(1234, 'en_US')` was `1,234.00` and is `1,234`;
  `formatNumber(1234.5, 'en_US')` was `1,234.50` and is `1,234.5`. Pass `decimals: 2` for the old output.
  A number has no currency to ask for a minor unit, so the locale's own digits are the only sensible
  default, and forcing two was money's answer to a question about numbers.
- **A value that is not a number throws** `InvalidNumber` instead of returning `''`. `null` and `''` still
  return `''` — nothing in, nothing out — but `'not a number'` and `'1.234,56'` (a *localized* string,
  which is `parseToMinor()`'s business) are mistakes in the caller rather than something to render as
  nothing. Silently returning `''` hid them, the way `format('not a number')` returning `$0.00` used to.

## `parseToMoney()` reads a string into a Money

The inverse of `format()`, and the bookend the parse side was missing: callers were building
`new Money(MoneyFormatter::parseDecimal(...), $currency->toMoneyCurrency())` by hand. The currency may be
a code, which is what a request carries, and one `available_currencies` does not list is refused there
rather than stored and read back as an exception.

```php
$post->price = MoneyFormatter::parseToMoney($request->input('price'), $request->input('currency'), app()->getLocale());
```

## `parseToMinor()` is the new name of `parseDecimal()`, and it reads a currency symbol

The name says what it returns — the minor units of the currency you pass, as a numeric string — the way
`formatFromMinor()` says what it takes. `parseDecimal` described its input, which `parse` already implies.

It also accepts an amount written with the symbol of that currency, where the locale puts it, since that is
what the formatter writes:

```php
MoneyFormatter::parseToMinor('$1,234.56', $usd, 'en_US');       // '123456'  (was ParserException)
MoneyFormatter::parseToMinor('12 USD', $usd, 'en_US');          // '1200'    (was ParserException)
MoneyFormatter::parseToMinor('USD 12', $usd, 'en_US');          // '1200'    (was ParserException)
MoneyFormatter::parseToMinor('€10', $usd, 'en_US');             // ParserException — not this currency
MoneyFormatter::parseToMinor('12 EUR', $usd, 'en_US');          // ParserException — not this currency
MoneyFormatter::parseToMinor('12 dollars', $usd, 'en_US');      // ParserException — not a currency
MoneyFormatter::parseToMinor('12 USD USD', $usd, 'en_US');      // ParserException — written twice
```

The currency has to be the one being read, written once, as its ISO code or its symbol. A symbol belonging
to another currency is refused rather than converted, which ICU's own currency parser would do on its own.

This does not bring back what the 1.\* parser did with `'12 USD'`. That was a *partial* read — ICU
consumed `'12'` and dropped the rest, the same defect that turned `'0x1A'` into `0` — and the whole string
still has to be accounted for. What is new is that a trailing or leading currency counts as accounted for.

Strict mode accepts only what this configuration writes: the notation `intl_currency_symbol` selects,
where the locale puts it, with the space the locale uses. Every other placement, notation and space is
forgiveness, so strict mode refuses it.

## An amount in a currency outside ISO 4217 formats and parses

Formatting a crypto currency used to throw `UnknownCurrencyException`, because the decimal point was
placed from ISO 4217 data alone. The minor unit of the currency is placed alongside it now, in both
directions, so the round trip holds for those currencies too:

```php
MoneyFormatter::formatFromMinor(100000000, Currency::fromCode('BTC'), 'en_US');
// BTC 1.00000000

MoneyFormatter::formatFromMinor(100000000, Currency::fromCode('BTC'), 'en_US', showCurrencySymbol: false);
// 1.00000000

MoneyFormatter::parseToMinor('1.00000000', Currency::fromCode('BTC'), 'en_US'); // '100000000'
```

ICU has no *symbol* for such a currency, so it writes the code where the symbol would go — which is all
that crypto support is missing, rather than the exception it used to be. This also fixes the `MoneyString`
validation rule, which raised `UnknownCurrencyException` out of the parser for a crypto currency instead
of reporting a validation failure.

The code is written in full however long it is. ICU carries a currency as a three-character code, so it
truncated the 170 bundled codes that are longer — `1000SATS` came out as `100`, an amount labelled as a
currency it is not — and threw a `TypeError` out of the money library for the 11 that are shorter. Such a
code is handed to ICU as the currency's symbol now, which is what it writes for a currency outside
ISO 4217 anyway, and `parseToMinor()` reads that notation back in strict mode as well.

## A currency's minor unit is read from its code

Only a LaraPara `Currency` carried a minor unit, so a bare `Money\Currency` outside ISO 4217 was read at
two decimals: `parseToMoney('1.00000000', new Money\Currency('BTC'), 'en_US')` gave 100 minor units where
the same call with `'BTC'` gave 100000000 — the same amount a factor of a million apart, decided by which
object the caller happened to hold. The code is looked up in the registry now, so every way of naming a
currency reads and writes the same amount. A code no currency list has still falls back to two decimals,
since nothing knows any better.

## The currency column is never nullable

All four column macros write the currency column as `NOT NULL`, defaulting to `default_currency`, where
only `money()` did before. An amount without a unit means nothing, and a row whose amount is `null` still
records the unit it would have been in, so assigning `null` to a currency attribute stores the default
currency instead of `NULL`. `CurrencyCast::get()` still reads an existing `NULL` as `null`.

Existing tables need the column widened and made required, in a migration of your own:

```php
Schema::table('products', function (Blueprint $table) {
    $table->string('price_currency', 12)->nullable()->change();
});

DB::table('products')->whereNull('price_currency')->update(['price_currency' => 'USD']);

Schema::table('products', function (Blueprint $table) {
    $table->string('price_currency', 12)->nullable(false)->default('USD')->change();
});
```

The width is `12` rather than `6` because a currency provider may bring codes longer than an ISO one: the
bundled crypto list has `1000SATS` and `AUCTION`, which six characters truncate on a permissive database
and reject on a strict one.

Two macros also changed the signedness of the amount column, which differed between the storage formats
for the same macro: `nullableMoney()` is signed in integer storage as well now (it was
`unsignedBigInteger`), and `smallMoney()` is unsigned in decimal storage as well (it was signed). A macro
is unsigned only where its name says so, and nullable only where its name says so.

## Decimal storage refuses an amount it would round

`store.format = decimal` keeps `store.decimal_scale` decimals — 3 by default, as before, now a config key
and a `scale` argument on each macro. An amount whose minor units the scale cannot represent throws
`Pelmered\LaraPara\Exceptions\InvalidAmount` instead of being rounded away by the database: CLF and UYW
carry four minor units, and every crypto currency carries eight. Raise the scale in the config and in the
column, pass `scale:` to the macro, or store amounts as integer minor units.

A column given its own scale has to tell the cast the same — `MoneyCast::class.':8'` beside
`money('price', scale: 8)` — since the cast is the side that refuses the amount. Told nothing, it refuses
by `store.decimal_scale` whatever the column holds.

A scale that leaves the column no digits for the amount itself is refused with
`Pelmered\LaraPara\Exceptions\InvalidColumnScale` as the migration is built. `smallMoney()` holds six
digits, so `store.decimal_scale = 8` — the setting a crypto project is told to use — used to write
`decimal(6, 8)`: a column MySQL and PostgreSQL reject and SQLite silently accepts. Use `money()` for
those columns, or keep the small ones on integer storage.

`MoneyCast::set()` also writes the decimal by placing the point rather than by dividing, so the value
reaching the column is a numeric string instead of a float and an amount larger than a double holds
exactly is no longer deformed.

## A currency serializes as its code

`Currency` implements `JsonSerializable`, and `CurrencyCast` implements `serialize()`, so a model's array
and JSON forms carry the code where they used to carry an object:

```json
{"price":{"amount":"123456","currency":"USD"},"price_currency":"USD"}
```

Before, `price_currency` was `{"code":"USD","name":"US Dollar","minorUnit":2}`. Anything reading
`price_currency.name` from a payload needs `Currency::fromCode($code)->name` instead.

## Currency codes are validated as they are written

`CurrencyCast::set()` and `MoneyCast::set()` now normalize the code (trimmed, upper-cased) and refuse a
code that `available_currencies` does not list, throwing `UnsupportedCurrency`. Reading such a row already
threw the same exception, so this moves the failure to the write that causes it.

Reading a money attribute whose currency column is empty throws `UnsupportedCurrency` as well, instead of
building a `Money` with an empty currency that only fails further downstream.

If your application writes a currency it does not list — a crypto code without
`load_crypto_currencies`, say — add it to `available_currencies`.

## `available_currencies` is normalized

Codes from the config are trimmed and upper-cased before use, so `MONEY_AVAILABLE_CURRENCIES="USD, EUR"`
works. A code the currency provider does not know now throws `UnsupportedCurrency` naming that code,
instead of `ErrorException: Undefined array key` on the first currency read.

## Formatting refuses an amount that is not whole minor units

Formatting cast its input to an int, so `'199.99'` rendered as `$1.99` and `'not a number'` as `$0.00`.
Anything that is not whole minor units now throws `Pelmered\LaraPara\Exceptions\InvalidAmount`. If you
were passing a major-unit amount, multiply it by the minor unit first, or use `formatNumber()`.

## `MoneyFormatter::parseToMinor()` rejects what it used to truncate

- The whole string has to be accounted for. `'1.2.3'`, `'0x1A'`, `'NaN'` and `'12 dollars'` now throw
  `ParserException`, as the documentation always said they would, rather than returning the part that
  happened to parse. The currency of the amount is accounted for: `'12 USD'` reads as 1200, but for a
  different reason than it did in 1.\*, where the `' USD'` was simply ignored — see the section on that
  above.
- A dot that the locale itself refuses is now read as that locale's decimal separator, since a dot means a
  decimal point on nearly every keyboard, spreadsheet and programming language. In `de_DE`, `'1.5'` was
  `'1500'` (€15.00) and is now `'150'` (€1.50) — dropping the dot as grouping left no way to type a decimal
  point at all. In `sv_SE`, `fr_FR`, `pl_PL` and the other locales that group with spaces, `'1.5'` used to
  throw and now parses as `'150'`. A dot that *is* in a grouping position keeps its meaning, so `'1.234'`
  in `de_DE` is still `'123400'`.

  Every other grouping separator out of position is still dropped, unchanged: `'2,00'` in `en_US` is
  `'20000'`, the same as `'200'`. In those locales the dot is already the decimal separator, so a user who
  means two and a half types `2.5` and always could.
- An amount above 14 significant digits is no longer deformed by PHP's `precision` setting on its way
  through a float.

## `MoneyFormatter::formatShort()` uses the minor unit of the currency

It divided by a hardcoded 100, so it disagreed with `formatFromMinor()` for the 39 bundled currencies whose minor
unit is not 2 — JPY came out a hundred times low, BHD ten times high. Abbreviated output changes for those
currencies, and the "format in full below 1000" threshold is now 1000 of the currency's major unit rather
than 100000 minor units.

It also no longer goes through `Illuminate\Support\Number::abbreviate()`, which formatted with the global
`Number` locale rather than the locale it was given: after `Number::useLocale('sv')` every abbreviated
amount threw `RuntimeException('Invalid format')`. Locales whose digits are not Latin — `ar_EG`, `fa_IR`,
`bn_IN` and the like — either threw or produced garbled numbers, and now format correctly.

Negative amounts are abbreviated now instead of always being formatted in full, and
`formatShort(0, ..., showCurrencySymbol: false)` no longer shows a currency symbol.

## `formatFromMinor(..., showCurrencySymbol: false)` uses the minor unit of the currency

It divided by a hardcoded 100 whatever the currency, so
`formatFromMinor(1234, JPY, showCurrencySymbol: false)` gave `12.34` and now gives `1,234`. A currency
outside ISO 4217 works here too — see the section on that above.

## `formatNumber()` with negative decimals

Negative decimals are significant digits. The value was scaled through an intermediate `(int)` cast first,
which zeroed anything below the scale factor: `formatNumber(12.34, 'en_US', decimals: -3)` returned `'0'`
and now returns `'12.3'`. The documented cases are unchanged.


# Upgrade from 1.* to 2.*

### Add model casts for money fields (optional but strongly recommended)

_-As of now, this is required, will be made optional in the future.-_

Each money column should have a cast that casts the column to a Money object and the currency column should have a cast that casts the column to a Currency object

```php
use Pelmered\LaraPara\Casts\CurrencyCast;
use Pelmered\LaraPara\Casts\MoneyCast;

protected function casts(): array
{
    return [
        'price' => MoneyCast::class,
        'price_currency' => CurrencyCast::class,
        'another_price' => MoneyCast::class,
        'another_price_currency' => CurrencyCast::class,
    ];
}
```
Or as a property:
```php
    protected $casts = [
        'price' => MoneyCast::class,
        'price_currency' => CurrencyCast::class,
        'another_price' => MoneyCast::class,
        'another_price_currency' => CurrencyCast::class,
    ];
```

Value objects are great in most cases, but if you don't want to use them in your code, you can add an [accessor](https://laravel.com/docs/12.x/eloquent-mutators#accessors-and-mutators) for getting the raw values:
```php
protected function price(): Attribute
{
    return Attribute::make(
        get: static fn (string $value) => $value
    );
}
protected function priceCurrency(): Attribute
{
    return Attribute::make(
        get: static fn (string $value) => $value,
    );
}
```

### Add currency columns

Each money column needs a corresponding currency column with the name {money_column}_currency

For new columns
```php
Schema::table('tablename', function (Blueprint $table) {
    $table->money('price'); // This will create two columns, 'price' (integer) and 'price_currency' (varchar(12))
});
```
For an existing amount column, in this case a column called `price`, add the currency column as nullable,
backfill the rows you already have, and only then make it required:
```php
Schema::table('tablename', function (Blueprint $table) {
    $table->string('price_currency', 12)->nullable()->after('price');
});

DB::table('tablename')->whereNull('price_currency')->update(['price_currency' => 'USD']);

Schema::table('tablename', function (Blueprint $table) {
    $table->string('price_currency', 12)->nullable(false)->default('USD')->change();
    $table->index(['price_currency', 'price']);
});
```
Don't forget to run your migrations. 

### Config changes

Recommended approach is to make a backup of your current config, and copy in [the new config](config/larapara.php). Then merge the values that you have changed.

## Configure available currencies

You need to configure the available currencies in the `larapara.php` config or in your `.env` file.

In the config file you can configure like this:

```php
'available_currencies' => [
    'USD',
    'EUR',
    'GBP',
],
```

In the `.env` file you can configure like this:

```env
MONEY_AVAILABLE_CURRENCIES=USD,EUR,GBP
```

## Money Formatter breaking changes

### `formatAsDecimal()` is removed

`formatAsDecimal()` has been removed. Use `formatNumber()` instead. The method signature is the same except the second parameter(currency) is removed. 

#### Example:
```php
MoneyFormatter::formatAsDecimal(123456, Currency::fromCode('USD')); // Output: $1,234.56
// should be changed to:
// formatNumber() takes a plain number and does not convert from minor units,
// so divide by 100 (or use formatFromMinor() with showCurrencySymbol: false).
MoneyFormatter::formatNumber(1234.56, 'en_US'); // Output: 1,234.56
```
