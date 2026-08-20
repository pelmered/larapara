<?php

declare(strict_types=1);

use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Exceptions\InvalidAmount;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

beforeEach(function (): void {
    // Ensure the currency cache is configured to work with tests
    config(['larapara.currency_cache.type' => false]);

    config(['larapara.available_currencies' => ['USD', 'EUR', 'SEK', 'JPY', 'BHD']]);
});

it('formats large values in short format', function (): void {
    // Testing millions
    expect(MoneyFormatter::formatShort(1000000, Currency::fromCode('USD'), 'en_US'))
        ->toEqual('$10.00K');

    // Testing thousands
    expect(MoneyFormatter::formatShort(100000, Currency::fromCode('USD'), 'en_US'))
        ->toEqual('$1.00K');
});

it('formats small values correctly', function (): void {
    // Testing fractions of cents
    expect(MoneyFormatter::format(1, Currency::fromCode('USD'), 'en_US'))
        ->toEqual('$0.01');

    // Testing 0
    expect(MoneyFormatter::format(0, Currency::fromCode('USD'), 'en_US'))
        ->toEqual('$0.00');
});

// An amount is whole minor units. Anything else used to be cast to an int, which turned it into a
// plausible looking wrong amount instead of an error.
it('refuses an amount that is not whole minor units', function (mixed $value): void {
    expect(fn (): string => MoneyFormatter::format($value, Currency::fromCode('USD'), 'en_US'))
        ->toThrow(InvalidAmount::class);
})->with([
    'not a number'        => ['not-a-number'],
    'decimals'            => ['199.99'],
    'thousands separator' => ['1,234'],
    'trailing text'       => ['1234 USD'],
]);

it('formats an amount given as a numeric string', function (): void {
    expect(MoneyFormatter::format('123456', Currency::fromCode('USD'), 'en_US'))
        ->toEqual('$1,234.56')
        ->and(MoneyFormatter::format(' -123456 ', Currency::fromCode('USD'), 'en_US'))
        ->toEqual('-$1,234.56');
});

it('formats negative values correctly', function (): void {
    expect(MoneyFormatter::format(-1500000, Currency::fromCode('USD'), 'en_US'))
        ->toEqual('-$15,000.00');
});

// The call every application makes names no decimals, so this is the one that says what a currency
// with a minor unit other than two renders as.
it('formats a currency with the fraction digits of that currency', function (string $currency, int $value, string $expectedOutput): void {
    expect(replaceNonBreakingSpaces(MoneyFormatter::format($value, Currency::fromCode($currency), 'en_US')))
        ->toBe($expectedOutput);
})->with([
    'two minor units'   => ['USD', 12345, '$123.45'],
    'no minor units'    => ['JPY', 12345, '¥12,345'],
    'three minor units' => ['BHD', 12345, 'BHD 12.345'],
]);

it('lets the decimals argument override the fraction digits of the currency', function (string $currency, int $value, int $decimals, string $expectedOutput): void {
    expect(replaceNonBreakingSpaces(MoneyFormatter::format($value, Currency::fromCode($currency), 'en_US', decimals: $decimals)))
        ->toBe($expectedOutput);
})->with([
    'yen with decimals' => ['JPY', 12345, 2, '¥12,345.00'],
    'dinar without'     => ['BHD', 12345, 0, 'BHD 12'],
    'dollars with four' => ['USD', 12345, 4, '$123.4500'],
]);

// Formatting and parsing back is the round trip an application makes around a form field, and it
// only holds while both directions take the scale from the same place.
it('parses back what it formats', function (string $currency, int $value): void {
    $formatted = MoneyFormatter::format($value, Currency::fromCode($currency), 'en_US', showCurrencySymbol: false);

    expect(MoneyFormatter::parseDecimal($formatted, Currency::fromCode($currency), 'en_US'))
        ->toBe((string) $value);
})->with([
    'two minor units'   => ['USD', 123456],
    'no minor units'    => ['JPY', 123456],
    'three minor units' => ['BHD', 123456],
    'krona'             => ['SEK', 123456],
]);

it('handles different locales properly', function (): void {
    // Testing French locale
    expect(replaceNonBreakingSpaces(MoneyFormatter::format(12345, Currency::fromCode('EUR'), 'fr_FR')))
        ->toContain('123,45');

    // Testing German locale
    expect(replaceNonBreakingSpaces(MoneyFormatter::format(12345, Currency::fromCode('EUR'), 'de_DE')))
        ->toContain('123,45');
});

it('parses money strings from different locales', function (): void {
    // US format
    expect(MoneyFormatter::parseDecimal('1,234.56', Currency::fromCode('USD'), 'en_US'))
        ->toEqual('123456');

    // European format
    expect(MoneyFormatter::parseDecimal('1.234,56', Currency::fromCode('EUR'), 'de_DE'))
        ->toEqual('123456');

    // Swedish format
    expect(MoneyFormatter::parseDecimal('1 234,56', Currency::fromCode('SEK'), 'sv_SE'))
        ->toEqual('123456');
});
