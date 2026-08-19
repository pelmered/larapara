<?php

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
    'no minor units'           => ['JPY', 999, '¥999.00'],
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

it('abbreviates in the digits of the locale', function (string $locale, string $expectedOutput): void {
    expect(MoneyFormatter::formatShort(123456789, Currency::fromCode('USD'), $locale, showCurrencySymbol: false))
        ->toBe($expectedOutput);
})->with([
    'eastern arabic-indic'  => ['ar_EG', '١٫٢٣M'],
    'extended arabic-indic' => ['fa_IR', '۱٫۲۳M'],
    'bengali'               => ['bn_IN', '১.২৩M'],
    'devanagari'            => ['mr_IN', '१.२३M'],
    'tibetan'               => ['dz_BT', '༡.༢༣M'],
    'latin with comma'      => ['de_DE', '1,23M'],
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
    'non-latin digits' => ['ar_EG', "\u{200f}١٫٢٣M USD"],
]);

it('abbreviates with the requested number of decimals', function (int $decimals, string $expectedOutput): void {
    expect(MoneyFormatter::formatShort(123456789, Currency::fromCode('USD'), 'en_US', decimals: $decimals))
        ->toBe($expectedOutput);
})->with([
    'none'               => [0, '$1M'],
    'one'                => [1, '$1.2M'],
    'two'                => [2, '$1.23M'],
    'significant digits' => [-3, '$1.23M'],
]);
