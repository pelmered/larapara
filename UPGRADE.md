
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

- **Assigning `null` to a nullable money column now stores `NULL`.** It used to store `0.000`, so "no price
  set" and "free" were indistinguishable. If you have rows that were meant to be null, they are zeros in
  the column now and only you can tell which is which.

## Currency codes are validated as they are written

`CurrencyCast::set()` and `MoneyCast::set()` now normalize the code (trimmed, upper-cased) and refuse a
code that `available_currencies` does not list, throwing `UnsupportedCurrency`. Reading such a row already
threw the same exception, so this moves the failure to the write that causes it.

If your application writes a currency it does not list — a crypto code without
`load_crypto_currencies`, say — add it to `available_currencies`.

## `available_currencies` is normalized

Codes from the config are trimmed and upper-cased before use, so `MONEY_AVAILABLE_CURRENCIES="USD, EUR"`
works. A code the currency provider does not know now throws `UnsupportedCurrency` naming that code,
instead of `ErrorException: Undefined array key` on the first currency read.

## `MoneyFormatter::format()` refuses an amount that is not whole minor units

`format()` cast its input to an int, so `'199.99'` rendered as `$1.99` and `'not a number'` as `$0.00`.
Anything that is not whole minor units now throws `Pelmered\LaraPara\Exceptions\InvalidAmount`. If you
were passing a major-unit amount, multiply it by the minor unit first, or use `numberFormat()`.

## `MoneyFormatter::parseDecimal()` rejects what it used to truncate

- The whole string has to be a number in the given locale. `'12 USD'`, `'1.2.3'`, `'0x1A'` and `'NaN'` now
  throw `ParserException`, as the documentation always said they would, rather than returning the part that
  happened to parse.
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

It divided by a hardcoded 100, so it disagreed with `format()` for the 39 bundled currencies whose minor
unit is not 2 — JPY came out a hundred times low, BHD ten times high. Abbreviated output changes for those
currencies, and the "format in full below 1000" threshold is now 1000 of the currency's major unit rather
than 100000 minor units.

It also no longer goes through `Illuminate\Support\Number::abbreviate()`, which formatted with the global
`Number` locale rather than the locale it was given: after `Number::useLocale('sv')` every abbreviated
amount threw `RuntimeException('Invalid format')`. Locales whose digits are not Latin — `ar_EG`, `fa_IR`,
`bn_IN` and the like — either threw or produced garbled numbers, and now format correctly.

Negative amounts are abbreviated now instead of always being formatted in full, and
`formatShort(0, ..., showCurrencySymbol: false)` no longer shows a currency symbol.

## `format(..., showCurrencySymbol: false)` uses the minor unit of the currency

It divided by a hardcoded 100 whatever the currency, so `format(1234, JPY, showCurrencySymbol: false)` gave
`12.34` and now gives `1,234.00`. For a crypto currency it now throws `UnknownCurrencyException`, like
`format()` itself does; use `numberFormat()` with the currency's minor unit for those.

## `numberFormat()` with negative decimals

Negative decimals are significant digits. The value was scaled through an intermediate `(int)` cast first,
which zeroed anything below the scale factor: `numberFormat(12.34, 'en_US', decimals: -3)` returned `'0'`
and now returns `'12.3'`. The documented cases are unchanged.


# Upgrade from 1.* to 2.*

### Add Model cats for money fields (optional but strongly recommended)

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
````

### Add currency columns

Each money column needs a corresponding currency column with the name {money_column}_currency

For new columns
```php
Schema::table('tablename', function (Blueprint $table) {
    $table->money('price'); // This will create two columns, 'price' (integer) and 'price_currency' (char(3))
});
```
For changing existing columns, in this case a column called `price`.
```php
Schema::table('tablename', function (Blueprint $table) {
    $table->char('price_currency', 3)->after('price')->change();
    $this->index(['price', 'price_currency']);
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

`formatAsDecimal()` has been removed. Use `numberFormat()` instead. The method signature is the same except the second parameter(currency) is removed. 

#### Example:
```php
MoneyFormatter::formatAsDecimal(123456, Currency::fromCode('USD')); // Output: $1,234.56
// should be changed to:
MoneyFormatter::numberFormat(123456); // Output: $1,234.56

``
