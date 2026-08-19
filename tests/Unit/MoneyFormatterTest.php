<?php

namespace Pelmered\LaraPara\Tests;

use Money\Currency as MoneyCurrency;
use Money\Exception\ParserException;
use NumberFormatter;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

function provideMoneyDataSek(): array
{
    return [
        'thousands' => [
            1000000,
            '10 000,00 kr',
        ],
        'decimals' => [
            10045,
            '100,45 kr',
        ],
        'millions' => [
            123456789,
            '1 234 567,89 kr',
        ],
        'empty_string' => [
            '',
            '',
        ],
        'null' => [
            null,
            '',
        ],
    ];
}

function provideDecimalMoneyDataSek(): array
{
    return [
        'thousands' => [
            1000000,
            '10 000,00',
        ],
        'decimals' => [
            10045,
            '100,45',
        ],
        'millions' => [
            123456789,
            '1 234 567,89',
        ],
        'empty_string' => [
            '',
            '',
        ],
        'null' => [
            null,
            '',
        ],
    ];
}

function provideMoneyDataUsd(): array
{
    return [
        'thousands' => [
            1000000,
            '$10,000.00',
        ],
        'decimals' => [
            10045,
            '$100.45',
        ],
        'millions' => [
            123456789,
            '$1,234,567.89',
        ],
        'empty_string' => [
            '',
            '',
        ],
        'null' => [
            null,
            '',
        ],
    ];
}

function provideDecimalMoneyDataUsd(): array
{
    return [
        'thousands' => [
            1000000,
            '10,000.00',
        ],
        'decimals' => [
            10045,
            '100.45',
        ],
        'millions' => [
            123456789,
            '1,234,567.89',
        ],
        'empty_string' => [
            '',
            '',
        ],
        'null' => [
            null,
            '',
        ],
    ];
}

function provideDecimalDataSek(): array
{
    return [
        'thousands' => [
            '10 000,00',
            '1000000',
        ],
        'decimals' => [
            '100,45',
            '10045',
        ],
        'millions' => [
            '1 234 567,89',
            '123456789',
        ],
        'empty_string' => [
            '',
            '',
        ],
        'null' => [
            null,
            '',
        ],
    ];
}

function provideDecimalDataUsd(): array
{
    return [
        'thousands' => [
            '10,000.00',
            '1000000',
        ],
        'decimals' => [
            '100.45',
            '10045',
        ],
        'millions' => [
            '1,234,567.89',
            '123456789',
        ],
        'empty_string' => [
            '',
            '',
        ],
        'null' => [
            null,
            '',
        ],
    ];
}

it('formats money in usd', function (mixed $input, string $expectedOutput): void {
    expect(MoneyFormatter::format($input, Currency::fromCode('USD'), 'en_US'))
        ->toBe(replaceNonBreakingSpaces($expectedOutput));
})->with(provideMoneyDataUsd());

it('formats money in sek', function (mixed $input, string $expectedOutput): void {
    $result        = MoneyFormatter::format($input, Currency::fromCode('SEK'), 'sv_SE');
    $cleanResult   = replaceNonBreakingSpaces($result);
    $cleanExpected = replaceNonBreakingSpaces($expectedOutput);

    expect($cleanResult)->toEqual($cleanExpected);
})->with(provideMoneyDataSek());

it('formats decimal money with US locale', function (mixed $input, string $expectedOutput): void {
    expect(MoneyFormatter::numberFormat($input, 'en_US'))
        ->toBe(replaceNonBreakingSpaces($expectedOutput));
})->with(provideDecimalMoneyDataUsd());

it('formats decimal money with Swedish locale', function (mixed $input, string $expectedOutput): void {
    $result        = MoneyFormatter::numberFormat($input, 'sv_SE');
    $cleanResult   = replaceNonBreakingSpaces($result);
    $cleanExpected = replaceNonBreakingSpaces($expectedOutput);

    expect($cleanResult)->toEqual($cleanExpected);
})->with(provideDecimalMoneyDataSek());

it('parses decimal money in sek', function (mixed $input, string $expectedOutput): void {
    expect(MoneyFormatter::parseDecimal($input, Currency::fromCode('SEK'), 'sv_SE'))
        ->toBe($expectedOutput);
})->with(provideDecimalDataSek());

it('parses decimal money in usd', function (mixed $input, string $expectedOutput): void {
    expect(MoneyFormatter::parseDecimal($input, Currency::fromCode('USD'), 'en_US'))
        ->toBe($expectedOutput);
})->with(provideDecimalDataUsd());

it('parses decimal money in usd with intl symbol', function (mixed $input, string $expectedOutput): void {
    config(['larapara.intl_currency_symbol' => true]);

    expect(MoneyFormatter::parseDecimal($input, Currency::fromCode('USD'), 'en_US'))
        ->toBe($expectedOutput);
})->with(provideDecimalDataUsd());

// A grouping separator typed where the decimal separator belongs is read as a decimal separator,
// rather than dropped, which would multiply the amount by a hundred.
// See: https://github.com/pelmered/larapara/issues/20
it('parses a decimal separator typed as the grouping separator', function (string $locale, string $currency, string $input, string $expectedOutput): void {
    expect(MoneyFormatter::parseDecimal($input, Currency::fromCode($currency), $locale))
        ->toBe($expectedOutput);
})->with([
    'grouping separator in the decimals' => ['en_US', 'USD', '2,00', '200'],
    'no grouping position at all'        => ['en_US', 'USD', '12,34', '1234'],
    'dot grouping locale'                => ['de_DE', 'EUR', '1.5', '150'],
    'genuine grouping is left alone'     => ['en_US', 'USD', '1,234', '123400'],
    'genuine dot grouping is left alone' => ['de_DE', 'EUR', '1.234', '123400'],
]);

it('rejects a string that is not a number in its entirety', function (string $locale, string $input): void {
    expect(fn (): string => MoneyFormatter::parseDecimal($input, Currency::fromCode('USD'), $locale))
        ->toThrow(ParserException::class, 'The value must be a valid numeric value.');
})->with([
    'trailing text'      => ['en_US', '12 USD'],
    'leading text'       => ['en_US', 'USD 12'],
    'two decimal points' => ['en_US', '1.2.3'],
    'hexadecimal'        => ['en_US', '0x1A'],
    'not a number'       => ['en_US', 'invalid'],
    'not finite'         => ['en_US', 'NaN'],
]);

// PHP's `precision` ini setting deforms a float above 14 significant digits on its way to a string,
// which silently changed the amount.
it('parses an amount above the float printing precision', function (): void {
    expect(MoneyFormatter::parseDecimal('1234567890123456', Currency::fromCode('USD'), 'en_US'))
        ->toBe('123456789012345600');
});

it('formats to international currency symbol', function (): void {
    config(['larapara.intl_currency_symbol' => true]);

    $result        = MoneyFormatter::format(100000, Currency::fromCode('USD'), 'en_US');
    $cleanResult   = replaceNonBreakingSpaces($result);
    $cleanExpected = replaceNonBreakingSpaces('USD 1,000.00');

    expect($cleanResult)->toEqual($cleanExpected);
});

it('formats to international currency symbol as suffix', function (): void {
    config(['larapara.intl_currency_symbol' => true]);

    $result        = MoneyFormatter::format(100000, Currency::fromCode('SEK'), 'sv_SE');
    $cleanResult   = replaceNonBreakingSpaces($result);
    $cleanExpected = replaceNonBreakingSpaces('1 000,00 SEK');

    expect($cleanResult)->toEqual($cleanExpected);
});

// The international symbol must come from the currency being formatted, not from the locale's region.
// See: https://github.com/pelmered/larapara/issues/5
it('formats to the international currency symbol of the formatted currency', function (string $locale, string $expectedOutput): void {
    config(['larapara.intl_currency_symbol' => true]);

    $result = MoneyFormatter::format(12345, Currency::fromCode('USD'), $locale);

    expect(replaceNonBreakingSpaces($result))->toEqual($expectedOutput);
})->with([
    'locale without region'      => ['en', 'USD 123.45'],
    'locale of the currency'     => ['en_US', 'USD 123.45'],
    'locale with suffix symbol'  => ['sv_SE', '123,45 USD'],
    'locale with other currency' => ['de_DE', '123,45 USD'],
]);

it('formats negative amounts with the international currency symbol', function (string $locale, string $expectedOutput): void {
    config(['larapara.intl_currency_symbol' => true]);

    $result = MoneyFormatter::format(-12345, Currency::fromCode('USD'), $locale);

    expect(replaceNonBreakingSpaces($result))->toEqual($expectedOutput);
})->with([
    'prefix symbol'                 => ['en_US', '-USD 123.45'],
    'suffix symbol'                 => ['de_DE', '-123,45 USD'],
    'suffix symbol with minus sign' => ['sv_SE', "\u{2212}123,45 USD"],
]);

it('abbreviates with the international currency symbol of the formatted currency', function (): void {
    config(['larapara.intl_currency_symbol' => true]);

    $result = MoneyFormatter::formatShort(123456789, Currency::fromCode('USD'), 'sv_SE');

    expect(replaceNonBreakingSpaces($result))->toEqual('1,23M USD');
});

// RTL locales put directional marks in both affixes, so the currency placement has to come from
// the pattern. Reading it off a non-empty affix renders the code twice and drops the marks.
it('formats to the international currency symbol in right to left locales', function (string $locale, string $localeCurrency): void {
    // Only the directional marks, so the two outputs can be compared on those alone.
    $marksOf = static fn (string $value): string => (string) preg_replace('/[^\x{200E}\x{200F}\x{061C}]/u', '', $value);

    foreach ([123456, -123456] as $amount) {
        config(['larapara.intl_currency_symbol' => false]);
        $plain = MoneyFormatter::format($amount, Currency::fromCode('USD'), $locale);

        config(['larapara.intl_currency_symbol' => true]);
        $formatted = MoneyFormatter::format($amount, Currency::fromCode('USD'), $locale);

        // Compared against what ICU itself does for the locale rather than against fixed output:
        // CLDR reshapes these patterns between ICU releases and the test matrix spans several of
        // them. What has to hold is that the code appears once, that it is the formatted currency
        // rather than the locale's, and that the marks of the locale survive the rewrite.
        expect(substr_count($formatted, 'USD'))->toBe(1)
            ->and($formatted)->not->toContain($localeCurrency)
            ->and($marksOf($formatted))->toBe($marksOf($plain));
    }
})->with([
    'currency in the suffix' => ['he_IL', 'ILS'],
    'currency in the prefix' => ['ar_AE', 'AED'],
]);

it('formats to the international currency symbol in the accounting style', function (): void {
    config(['larapara.intl_currency_symbol' => true]);

    $positive = MoneyFormatter::format(123456, Currency::fromCode('USD'), 'en_US', NumberFormatter::CURRENCY_ACCOUNTING);
    $negative = MoneyFormatter::format(-123456, Currency::fromCode('USD'), 'en_US', NumberFormatter::CURRENCY_ACCOUNTING);

    // The accounting style brackets negatives instead of signing them, which the pattern keeps.
    expect(replaceNonBreakingSpaces($positive))->toEqual('USD 1,234.56')
        ->and(replaceNonBreakingSpaces($negative))->toEqual('(USD 1,234.56)');
});

it('gets the formatting rules of the given currency', function (): void {
    expect(MoneyFormatter::getFormattingRules('sv_SE', Currency::fromCode('USD'))->currencySymbol)->toBe('US$');

    config(['larapara.intl_currency_symbol' => true]);

    expect(MoneyFormatter::getFormattingRules('sv_SE', Currency::fromCode('USD'))->currencySymbol)->toBe('USD');
});

// ICU locale keywords only accept 3 character currency codes, so longer ones fall back to the
// currency of the locale's region unless we short circuit them.
it('gets the formatting rules of a currency ICU does not know', function (): void {
    expect(MoneyFormatter::getFormattingRules('sv_SE', new MoneyCurrency('AERGO'))->currencySymbol)->toBe('AERGO');

    config(['larapara.intl_currency_symbol' => true]);

    expect(MoneyFormatter::getFormattingRules('sv_SE', new MoneyCurrency('AERGO'))->currencySymbol)->toBe('AERGO');
});

it('formats with decimal parameter', function (): void {
    expect(MoneyFormatter::format(123456, Currency::fromCode('USD'), 'en_US'))
        ->toBe(replaceNonBreakingSpaces('$1,234.56'))
        ->and(MoneyFormatter::format(123456, Currency::fromCode('USD'), 'en_US', decimals: 0))
        ->toBe(replaceNonBreakingSpaces('$1,235'));
});

it('formats with decimal parameter in sek', function (): void {
    $result        = MoneyFormatter::format(100060, Currency::fromCode('SEK'), 'sv_SE', decimals: 0);
    $cleanResult   = replaceNonBreakingSpaces($result);
    $cleanExpected = replaceNonBreakingSpaces('1 001 kr');

    expect($cleanResult)->toEqual($cleanExpected);
});

it('formats 0 in short format', function (): void {
    expect(MoneyFormatter::formatShort(0, Currency::fromCode('USD'), 'en_US', decimals: -3))
        ->toBe(replaceNonBreakingSpaces('$0'));
});

// The amount only used to be divided by a hardcoded hundred here, which was wrong for every
// currency whose minor unit is not two.
it('formats without a currency symbol by the minor unit of the currency', function (string $currency, int $value, int $decimals, string $expectedOutput): void {
    config(['larapara.available_currencies' => ['USD', 'EUR', 'SEK', 'JPY', 'BHD']]);

    expect(MoneyFormatter::format($value, Currency::fromCode($currency), 'en_US', decimals: $decimals, showCurrencySymbol: false))
        ->toBe($expectedOutput);
})->with([
    'two minor units'   => ['USD', 123456, 2, '1,234.56'],
    'no minor units'    => ['JPY', 1234, 0, '1,234'],
    'three minor units' => ['BHD', 1234567, 3, '1,234.567'],
]);

// Negative decimals are significant digits, which ICU applies on its own. Scaling the value through
// an intermediate int cast first zeroed anything below the scale factor.
it('formats to a number of significant digits', function (mixed $value, int $decimals, string $expectedOutput): void {
    expect(MoneyFormatter::numberFormat($value, 'en_US', decimals: $decimals))
        ->toBe($expectedOutput);
})->with([
    'documented example'    => [1234.56, -2, '1,200'],
    'value below the scale' => [12.34, -3, '12.3'],
    'minor units input'     => [123456, -3, '1,230'],
]);
