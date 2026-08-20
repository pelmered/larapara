<?php

declare(strict_types=1);

use Illuminate\Support\Number;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

beforeEach(function (): void {
    config(['larapara.currency_cache.type' => false]);
    config(['larapara.available_currencies' => ['USD', 'EUR', 'SEK', 'JPY', 'BHD', 'ISK', 'CLF']]);
});

it('abbreviates by the magnitude of the amount', function (int $value, string $expectedOutput): void {
    expect(MoneyFormatter::formatShort($value, Currency::fromCode('USD'), 'en_US'))
        ->toBe($expectedOutput);
})->with([
    'thousands'   => [100000, '$1.00K'],
    'millions'    => [100000000, '$1.00M'],
    'billions'    => [100000000000, '$1.00B'],
    'trillions'   => [100000000000000, '$1.00T'],
    'quadrillion' => [100000000000000000, '$1.00Q'],
]);

// The threshold and the divisor come from the minor unit of the currency, so formatShort() and
// format() cannot disagree about the magnitude of the same amount.
it('abbreviates by the minor unit of the currency', function (string $currency, int $value, string $expectedOutput): void {
    expect(replaceNonBreakingSpaces(MoneyFormatter::formatShort($value, Currency::fromCode($currency), 'en_US')))
        ->toBe($expectedOutput);
})->with([
    'two minor units'   => ['USD', 123456789, '$1.23M'],
    'no minor units'    => ['JPY', 1234567, '¥1.23M'],
    'no minor units 2'  => ['ISK', 1234567, 'ISK 1.23M'],
    'three minor units' => ['BHD', 1234567890, 'BHD 1.23M'],
    'four minor units'  => ['CLF', 12345678900, 'CLF 1.23M'],
]);

it('formats amounts below a thousand in full', function (string $currency, int $value, string $expectedOutput): void {
    expect(MoneyFormatter::formatShort($value, Currency::fromCode($currency), 'en_US'))
        ->toBe($expectedOutput);
})->with([
    'just below the threshold' => ['USD', 99999, '$999.99'],
    'at the threshold'         => ['USD', 100000, '$1.00K'],
    'no minor units'           => ['JPY', 999, '¥999'],
    'zero'                     => ['USD', 0, '$0.00'],
]);

// Number::abbreviate() formats with the global Number locale rather than the one it is given, which
// made every abbreviated amount throw once an application had set one.
it('abbreviates independently of the global number locale', function (): void {
    Number::useLocale('sv');

    try {
        expect(MoneyFormatter::formatShort(123456789, Currency::fromCode('USD'), 'en_US'))
            ->toBe('$1.23M');
    } finally {
        Number::useLocale('en');
    }
});

it('abbreviates negative amounts', function (): void {
    expect(MoneyFormatter::formatShort(-123456789, Currency::fromCode('USD'), 'en_US'))
        ->toBe('-$1.23M')
        ->and(MoneyFormatter::formatShort(-123456, Currency::fromCode('USD'), 'en_US'))
        ->toBe('-$1.23K');
});

it('leaves out the currency symbol when asked to, at any magnitude', function (int $value, string $expectedOutput): void {
    expect(MoneyFormatter::formatShort($value, Currency::fromCode('USD'), 'en_US', showCurrencySymbol: false))
        ->toBe($expectedOutput);
})->with([
    'abbreviated' => [123456789, '1.23M'],
    'in full'     => [99999, '999.99'],
    'zero'        => [0, '0.00'],
]);

// The digits are the point here, so they are written out; the decimal separator between them comes
// from ICU, since CLDR moves those between releases and the test matrix spans several ICU versions.
it('abbreviates in the digits of the locale', function (string $locale, string $expectedDigits): void {
    $currency  = Currency::fromCode('USD');
    $digits    = mb_str_split($expectedDigits);
    $separator = MoneyFormatter::getFormattingRules($locale, $currency)->decimalSeparator;

    expect(MoneyFormatter::formatShort(123456789, $currency, $locale, showCurrencySymbol: false))
        ->toBe($digits[0].$separator.$digits[1].$digits[2].'M');
})->with([
    'eastern arabic-indic'  => ['ar_EG', '١٢٣'],
    'extended arabic-indic' => ['fa_IR', '۱۲۳'],
    'bengali'               => ['bn_IN', '১২৩'],
    'devanagari'            => ['mr_IN', '१२३'],
    'tibetan'               => ['dz_BT', '༡༢༣'],
    'latin'                 => ['de_DE', '123'],
]);

// The abbreviation used to be spliced into the formatted output with an ASCII-only regex, which
// threw for locales whose digits are not Latin, and garbled the number for the rest of them.
it('keeps the number intact when the currency symbol is shown', function (string $locale): void {
    $currency = Currency::fromCode('USD');

    expect(MoneyFormatter::formatShort(123456789, $currency, $locale))
        ->toContain(MoneyFormatter::formatShort(123456789, $currency, $locale, showCurrencySymbol: false));
})->with([
    'ar_EG', 'ar_SA', 'ar', 'ar_MA', 'fa_IR', 'ckb_IQ', 'ps_AF', 'bn_BD', 'bn_IN',
    'mr_IN', 'as_IN', 'dz_BT', 'ne_NP', 'my_MM', 'ur_PK', 'he_IL', 'ja_JP', 'sv_SE', 'de_DE', 'en_US',
]);

it('abbreviates with the international currency symbol', function (string $locale, string $expectedOutput): void {
    config(['larapara.intl_currency_symbol' => true]);

    expect(replaceNonBreakingSpaces(MoneyFormatter::formatShort(123456789, Currency::fromCode('USD'), $locale)))
        ->toBe($expectedOutput);
})->with([
    'symbol as prefix' => ['en_US', 'USD 1.23M'],
    'symbol as suffix' => ['sv_SE', '1,23M USD'],
]);

// Asserted on the invariants rather than on fixed output: these locales put directional marks around
// both affixes, and CLDR reshapes their patterns between ICU releases.
it('abbreviates with the international currency symbol in a locale with non-latin digits', function (string $locale): void {
    config(['larapara.intl_currency_symbol' => true]);

    $currency = Currency::fromCode('USD');
    $result   = MoneyFormatter::formatShort(123456789, $currency, $locale);

    expect(substr_count($result, 'USD'))->toBe(1)
        ->and($result)->toContain(MoneyFormatter::formatShort(123456789, $currency, $locale, showCurrencySymbol: false));
})->with(['ar_EG', 'fa_IR', 'bn_IN', 'mr_IN', 'dz_BT']);

it('abbreviates with the requested number of decimals', function (int $decimals, string $expectedOutput): void {
    expect(MoneyFormatter::formatShort(123456789, Currency::fromCode('USD'), 'en_US', decimals: $decimals))
        ->toBe($expectedOutput);
})->with([
    'none'               => [0, '$1M'],
    'one'                => [1, '$1.2M'],
    'two'                => [2, '$1.23M'],
    'significant digits' => [-3, '$1.23M'],
]);
