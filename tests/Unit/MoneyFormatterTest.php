<?php

namespace Pelmered\LaraPara\Tests;

use Money\Currency as MoneyCurrency;
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

it('parses small decimal money', function (): void {
    expect(MoneyFormatter::parseDecimal('2,00', Currency::fromCode('USD'), 'en_US'))
        ->toBe('20000');
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
it('formats to the international currency symbol in right to left locales', function (string $locale): void {
    config(['larapara.intl_currency_symbol' => true]);

    foreach ([123456, -123456] as $amount) {
        $formatted = MoneyFormatter::format($amount, Currency::fromCode('USD'), $locale);

        // Asserted on shape rather than on the exact output: CLDR moves the directional marks in
        // these patterns between ICU releases, and the test matrix spans several of them.
        $withoutMarks = str_replace(
            ["\u{200E}", "\u{200F}", "\u{061C}"],
            '',
            replaceNonBreakingSpaces($formatted)
        );

        expect(substr_count($formatted, 'USD'))->toBe(1)
            ->and($formatted)->toContain("\u{200F}")
            ->and($withoutMarks)->toContain('1,234.56 USD');
    }
})->with(['he_IL', 'ar_AE']);

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
