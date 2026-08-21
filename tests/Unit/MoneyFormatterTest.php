<?php

namespace Pelmered\LaraPara\Tests;

use Locale;
use Money\Currency as MoneyCurrency;
use Money\Money;
use NumberFormatter;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Exceptions\InvalidNumber;
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
    expect(MoneyFormatter::formatFromMinor($input, Currency::fromCode('USD'), 'en_US'))
        ->toBe(replaceNonBreakingSpaces($expectedOutput));
})->with(provideMoneyDataUsd());

it('formats money in sek', function (mixed $input, string $expectedOutput): void {
    $result        = MoneyFormatter::formatFromMinor($input, Currency::fromCode('SEK'), 'sv_SE');
    $cleanResult   = replaceNonBreakingSpaces($result);
    $cleanExpected = replaceNonBreakingSpaces($expectedOutput);

    expect($cleanResult)->toEqual($cleanExpected);
})->with(provideMoneyDataSek());

it('formats decimal money with US locale', function (mixed $input, string $expectedOutput): void {
    expect(MoneyFormatter::formatFromMinor($input, Currency::fromCode('USD'), 'en_US', showCurrencySymbol: false))
        ->toBe(replaceNonBreakingSpaces($expectedOutput));
})->with(provideDecimalMoneyDataUsd());

it('formats decimal money with Swedish locale', function (mixed $input, string $expectedOutput): void {
    $result        = MoneyFormatter::formatFromMinor($input, Currency::fromCode('SEK'), 'sv_SE', showCurrencySymbol: false);
    $cleanResult   = replaceNonBreakingSpaces($result);
    $cleanExpected = replaceNonBreakingSpaces($expectedOutput);

    expect($cleanResult)->toEqual($cleanExpected);
})->with(provideDecimalMoneyDataSek());

it('parses decimal money in sek', function (mixed $input, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode('SEK'), 'sv_SE'))
        ->toBe($expectedOutput);
})->with(provideDecimalDataSek());

it('parses decimal money in usd', function (mixed $input, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode('USD'), 'en_US'))
        ->toBe($expectedOutput);
})->with(provideDecimalDataUsd());

it('parses decimal money in usd with intl symbol', function (mixed $input, string $expectedOutput): void {
    config(['larapara.intl_currency_symbol' => true]);

    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode('USD'), 'en_US'))
        ->toBe($expectedOutput);
})->with(provideDecimalDataUsd());

it('formats to international currency symbol', function (): void {
    config(['larapara.intl_currency_symbol' => true]);

    $result        = MoneyFormatter::formatFromMinor(100000, Currency::fromCode('USD'), 'en_US');
    $cleanResult   = replaceNonBreakingSpaces($result);
    $cleanExpected = replaceNonBreakingSpaces('USD 1,000.00');

    expect($cleanResult)->toEqual($cleanExpected);
});

it('formats to international currency symbol as suffix', function (): void {
    config(['larapara.intl_currency_symbol' => true]);

    $result        = MoneyFormatter::formatFromMinor(100000, Currency::fromCode('SEK'), 'sv_SE');
    $cleanResult   = replaceNonBreakingSpaces($result);
    $cleanExpected = replaceNonBreakingSpaces('1 000,00 SEK');

    expect($cleanResult)->toEqual($cleanExpected);
});

// The international symbol must come from the currency being formatted, not from the locale's region.
// See: https://github.com/pelmered/larapara/issues/5
it('formats to the international currency symbol of the formatted currency', function (string $locale, string $expectedOutput): void {
    config(['larapara.intl_currency_symbol' => true]);

    $result = MoneyFormatter::formatFromMinor(12345, Currency::fromCode('USD'), $locale);

    expect(replaceNonBreakingSpaces($result))->toEqual($expectedOutput);
})->with([
    'locale without region'      => ['en', 'USD 123.45'],
    'locale of the currency'     => ['en_US', 'USD 123.45'],
    'locale with suffix symbol'  => ['sv_SE', '123,45 USD'],
    'locale with other currency' => ['de_DE', '123,45 USD'],
]);

it('formats negative amounts with the international currency symbol', function (string $locale, string $expectedOutput): void {
    config(['larapara.intl_currency_symbol' => true]);

    $result = MoneyFormatter::formatFromMinor(-12345, Currency::fromCode('USD'), $locale);

    expect(replaceNonBreakingSpaces($result))->toEqual($expectedOutput);
})->with([
    'prefix symbol'                 => ['en_US', '-USD 123.45'],
    'suffix symbol'                 => ['de_DE', '-123,45 USD'],
    'suffix symbol with minus sign' => ['sv_SE', "\u{2212}123,45 USD"],
]);

it('abbreviates with the international currency symbol of the formatted currency', function (): void {
    config(['larapara.intl_currency_symbol' => true]);

    $result = MoneyFormatter::formatShortFromMinor(123456789, Currency::fromCode('USD'), 'sv_SE');

    expect(replaceNonBreakingSpaces($result))->toEqual('1,23M USD');
});

// RTL locales put directional marks in both affixes, so the currency placement has to come from
// the pattern. Reading it off a non-empty affix renders the code twice and drops the marks.
it('formats to the international currency symbol in right to left locales', function (string $locale, string $localeCurrency): void {
    // Only the directional marks, so the two outputs can be compared on those alone.
    $marksOf = static fn (string $value): string => (string) preg_replace('/[^\x{200E}\x{200F}\x{061C}]/u', '', $value);

    foreach ([123456, -123456] as $amount) {
        config(['larapara.intl_currency_symbol' => false]);
        $plain = MoneyFormatter::formatFromMinor($amount, Currency::fromCode('USD'), $locale);

        config(['larapara.intl_currency_symbol' => true]);
        $formatted = MoneyFormatter::formatFromMinor($amount, Currency::fromCode('USD'), $locale);

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

    $positive = MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US', NumberFormatter::CURRENCY_ACCOUNTING);
    $negative = MoneyFormatter::formatFromMinor(-123456, Currency::fromCode('USD'), 'en_US', NumberFormatter::CURRENCY_ACCOUNTING);

    // The accounting style brackets negatives instead of signing them, which the pattern keeps.
    expect(replaceNonBreakingSpaces($positive))->toEqual('USD 1,234.56')
        ->and(replaceNonBreakingSpaces($negative))->toEqual('(USD 1,234.56)');
});

// ICU has more currency styles than the two PHP names, and every one of them renders the placeholder.
it('formats to the international currency symbol in the other currency styles', function (int $outputStyle): void {
    config(['larapara.intl_currency_symbol' => true]);

    $result = MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US', $outputStyle);

    expect(replaceNonBreakingSpaces($result))->toEqual('USD 1,234.56');
})->with([
    'cash currency'     => [13],
    'standard currency' => [16],
]);

// The formatters are kept between calls, so everything they are built from has to be part of what
// they are kept under — the config among it, which decides the symbol the pattern carries.
it('formats the same call differently when the symbol setting changes', function (): void {
    config(['larapara.intl_currency_symbol' => false]);

    expect(MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US'))->toBe('$1,234.56');

    config(['larapara.intl_currency_symbol' => true]);

    expect(replaceNonBreakingSpaces(MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US')))
        ->toBe('USD 1,234.56');

    config(['larapara.intl_currency_symbol' => false]);

    expect(MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US'))->toBe('$1,234.56');
});

it('gets the formatting rules of the given currency', function (): void {
    expect(MoneyFormatter::getFormattingRules('sv_SE', Currency::fromCode('USD'))->currencySymbol)->toBe('US$');

    config(['larapara.intl_currency_symbol' => true]);

    expect(MoneyFormatter::getFormattingRules('sv_SE', Currency::fromCode('USD'))->currencySymbol)->toBe('USD');
});

// Appending the currency keyword to an empty locale makes an identifier ICU refuses outright, while
// every other intl call reads the empty one as the default locale.
it('gets the formatting rules of the default locale for an empty locale', function (): void {
    $currency = Currency::fromCode('USD');

    expect(MoneyFormatter::getFormattingRules('', $currency))
        ->toEqual(MoneyFormatter::getFormattingRules(Locale::getDefault(), $currency));
});

// Formatters are kept under the locale they were built for, so an empty locale has to be resolved
// before it becomes that key: otherwise the first call freezes whatever the default was then, which
// a long-running process is free to change between calls.
it('follows the default locale when it changes, for an empty locale', function (): void {
    $currency = Currency::fromCode('USD');
    $default  = Locale::getDefault();

    try {
        Locale::setDefault('en_US');
        expect(MoneyFormatter::formatFromMinor(123456, $currency, ''))
            ->toBe(MoneyFormatter::formatFromMinor(123456, $currency, 'en_US'));

        Locale::setDefault('sv_SE');
        expect(MoneyFormatter::formatFromMinor(123456, $currency, ''))
            ->toBe(MoneyFormatter::formatFromMinor(123456, $currency, 'sv_SE'));
    } finally {
        Locale::setDefault($default);
    }
});

// ICU locale keywords only accept 3 character currency codes, so longer ones fall back to the
// currency of the locale's region unless we short circuit them.
it('gets the formatting rules of a currency ICU does not know', function (): void {
    expect(MoneyFormatter::getFormattingRules('sv_SE', new MoneyCurrency('AERGO'))->currencySymbol)->toBe('AERGO');

    config(['larapara.intl_currency_symbol' => true]);

    expect(MoneyFormatter::getFormattingRules('sv_SE', new MoneyCurrency('AERGO'))->currencySymbol)->toBe('AERGO');
});

it('formats with decimal parameter', function (): void {
    expect(MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US'))
        ->toBe(replaceNonBreakingSpaces('$1,234.56'))
        ->and(MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US', decimals: 0))
        ->toBe(replaceNonBreakingSpaces('$1,235'));
});

it('formats with decimal parameter in sek', function (): void {
    $result        = MoneyFormatter::formatFromMinor(100060, Currency::fromCode('SEK'), 'sv_SE', decimals: 0);
    $cleanResult   = replaceNonBreakingSpaces($result);
    $cleanExpected = replaceNonBreakingSpaces('1 001 kr');

    expect($cleanResult)->toEqual($cleanExpected);
});

it('formats 0 in short format', function (): void {
    expect(MoneyFormatter::formatShortFromMinor(0, Currency::fromCode('USD'), 'en_US', significantDigits: 3))
        ->toBe(replaceNonBreakingSpaces('$0'));
});

// The amount only used to be divided by a hardcoded hundred here, which was wrong for every
// currency whose minor unit is not two.
it('formats without a currency symbol by the minor unit of the currency', function (string $currency, int $value, int $decimals, string $expectedOutput): void {
    config(['larapara.available_currencies' => ['USD', 'EUR', 'SEK', 'JPY', 'BHD']]);

    expect(MoneyFormatter::formatFromMinor($value, Currency::fromCode($currency), 'en_US', decimals: $decimals, showCurrencySymbol: false))
        ->toBe($expectedOutput);
})->with([
    'two minor units'   => ['USD', 123456, 2, '1,234.56'],
    'no minor units'    => ['JPY', 1234, 0, '1,234'],
    'three minor units' => ['BHD', 1234567, 3, '1,234.567'],
]);

// Negative decimals are significant digits, which ICU applies on its own. Scaling the value through
// an intermediate int cast first zeroed anything below the scale factor.
it('formats to a number of significant digits', function (mixed $value, int $significantDigits, string $expectedOutput): void {
    expect(MoneyFormatter::formatNumber($value, 'en_US', significantDigits: $significantDigits))
        ->toBe($expectedOutput);
})->with([
    'documented example'    => [1234.56, 2, '1,200'],
    'value below the scale' => [12.34, 3, '12.3'],
    'whole value'           => [1234, 3, '1,230'],
]);

// The two are two ways to say how precise the output is, and ICU discards the fraction digits when
// both are set, so asking for both is a mistake rather than a combination.
it('refuses two kinds of precision at once', function (): void {
    expect(fn (): string => MoneyFormatter::formatNumber(1234.56, 'en_US', decimals: 2, significantDigits: 3))
        ->toThrow(InvalidNumber::class);
});

it('refuses a negative number of decimals, which used to mean significant digits', function (): void {
    expect(fn (): string => MoneyFormatter::formatNumber(1234.56, 'en_US', decimals: -3))
        ->toThrow(InvalidNumber::class)
        ->and(fn (): string => MoneyFormatter::formatFromMinor(123456, Currency::fromCode('USD'), 'en_US', decimals: -3))
        ->toThrow(InvalidNumber::class);
});

// Not a number, rather than a number to render as nothing: '' used to come back for anything
// unparseable, which hides the mistake in the caller.
it('refuses a value that is not a number', function (mixed $value): void {
    expect(fn (): string => MoneyFormatter::formatNumber($value, 'en_US'))->toThrow(InvalidNumber::class);
})->with([
    'a word'          => ['not a number'],
    'trailing text'   => ['1234abc'],
    'a localized one' => ['1.234,56'],
]);

// A value that is not a number is usually input that was never validated, and the message it lands in
// goes to the log, so the message names the type it was given rather than repeating the value.
it('keeps the rejected value out of the exception message', function (): void {
    $value = '4111111111111111';

    expect(InvalidNumber::notNumeric($value)->getMessage())
        ->not->toContain($value)
        ->toContain('string');
});

it('formats nothing as nothing', function (mixed $value): void {
    expect(MoneyFormatter::formatNumber($value, 'en_US'))->toBe('');
})->with([
    'null'         => [null],
    'empty string' => [''],
]);

// A number formatter formats the number it is given: the same value in any type it can arrive in
// reads the same, and nothing is scaled behind the caller's back.
it('formats the number it is given, whatever the type', function (mixed $value, string $expectedOutput): void {
    expect(MoneyFormatter::formatNumber($value, 'en_US'))->toBe($expectedOutput);
})->with([
    'int'                    => [1234, '1,234'],
    'digits as text'         => ['1234', '1,234'],
    'float'                  => [1234.56, '1,234.56'],
    'decimals as text'       => ['1234.56', '1,234.56'],
    'whole float'            => [1234.00, '1,234'],
    'whole decimals as text' => ['1234.00', '1,234'],
    'zero'                   => [0, '0'],
    'zero as a float'        => [0.0, '0'],
]);

// The decimals of the value, since a number is not an amount and has no currency to ask.
it('keeps the decimals of the number unless asked for others', function (mixed $value, ?int $decimals, string $expectedOutput): void {
    expect(MoneyFormatter::formatNumber($value, 'en_US', $decimals))->toBe($expectedOutput);
})->with([
    'as given'         => [1234.5, null, '1,234.5'],
    'more than given'  => [1234.5, 2, '1,234.50'],
    'fewer than given' => [1234.5678, 2, '1,234.57'],
    // ICU rounds half to even for display, as CLDR specifies.
    'none'              => [1234.5, 0, '1,234'],
    'none, rounding up' => [1235.5, 0, '1,236'],
    'a whole number'    => [1234, null, '1,234'],
    'more than three'   => [1234.56789, 5, '1,234.56789'],
]);

// ICU stops at three fraction digits of its own accord, so the decimals of the value were kept only
// as long as it had no more than three of them: nothing asked for 1234.5678 to be written as
// 1,234.568, or for a tenth of a cent to be written as 0.
it('keeps the decimals of the number past the third', function (mixed $value, string $expectedOutput): void {
    expect(MoneyFormatter::formatNumber($value, 'en_US'))->toBe($expectedOutput);
})->with([
    'four decimals'          => [1234.5678, '1,234.5678'],
    'four decimals as text'  => ['1234.5678', '1,234.5678'],
    'eight, a crypto amount' => [1.23456789, '1.23456789'],
    'below the third'        => [0.00001234, '0.00001234'],
    // The noise of the binary representation is absorbed rather than written out: 1.005 is held as
    // 1.00499999999999989, and a tenth plus two tenths as 0.30000000000000004.
    'a value a double rounds' => [1.005, '1.005'],
    'a sum a double rounds'   => [0.1 + 0.2, '0.3'],
]);

// A Money carries its own currency, and its amount is the minor units of that currency — the two
// entry points are the same amount either way round.
it('formats a Money object and its amount alike', function (): void {
    $money = new Money(123456, new MoneyCurrency('USD'));

    expect(MoneyFormatter::format($money, 'en_US'))->toBe('$1,234.56')
        ->and(MoneyFormatter::format($money, 'en_US', showCurrencySymbol: false))->toBe('1,234.56')
        ->and(MoneyFormatter::formatFromMinor($money->getAmount(), Currency::fromCode('USD'), 'en_US'))
        ->toBe('$1,234.56');
});

// The amount comes from the Money and so does the currency, where formatShort() used to take the
// amount from one and the minor unit and symbol from the other.
it('abbreviates a Money object by its own currency', function (): void {
    config(['larapara.available_currencies' => ['USD', 'JPY']]);

    $yen = new Money(123456789, new MoneyCurrency('JPY'));

    expect(MoneyFormatter::formatShort($yen, 'en_US'))->toBe('¥123.46M');
});
