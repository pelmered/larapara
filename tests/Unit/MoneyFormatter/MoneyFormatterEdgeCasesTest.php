<?php

declare(strict_types=1);

use Money\Exception\ParserException;
use Money\Money;
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
    expect(MoneyFormatter::formatShortFromMinor(1000000, Currency::fromCode('USD'), 'en_US'))
        ->toEqual('$10.00K');

    // Testing thousands
    expect(MoneyFormatter::formatShortFromMinor(100000, Currency::fromCode('USD'), 'en_US'))
        ->toEqual('$1.00K');
});

it('formats small values correctly', function (): void {
    // Testing fractions of cents
    expect(MoneyFormatter::formatFromMinor(1, Currency::fromCode('USD'), 'en_US'))
        ->toEqual('$0.01');

    // Testing 0
    expect(MoneyFormatter::formatFromMinor(0, Currency::fromCode('USD'), 'en_US'))
        ->toEqual('$0.00');
});

// An amount is whole minor units. Anything else used to be cast to an int, which turned it into a
// plausible looking wrong amount instead of an error.
it('refuses an amount that is not whole minor units', function (mixed $value): void {
    expect(fn (): string => MoneyFormatter::formatFromMinor($value, Currency::fromCode('USD'), 'en_US'))
        ->toThrow(InvalidAmount::class);
})->with([
    'not a number'        => ['not-a-number'],
    'decimals'            => ['199.99'],
    'thousands separator' => ['1,234'],
    'trailing text'       => ['1234 USD'],
]);

it('formats an amount given as a numeric string', function (): void {
    expect(MoneyFormatter::formatFromMinor('123456', Currency::fromCode('USD'), 'en_US'))
        ->toEqual('$1,234.56')
        ->and(MoneyFormatter::formatFromMinor(' -123456 ', Currency::fromCode('USD'), 'en_US'))
        ->toEqual('-$1,234.56');
});

it('formats negative values correctly', function (): void {
    expect(MoneyFormatter::formatFromMinor(-1500000, Currency::fromCode('USD'), 'en_US'))
        ->toEqual('-$15,000.00');
});

// The call every application makes names no decimals, so this is the one that says what a currency
// with a minor unit other than two renders as.
it('formats a currency with the fraction digits of that currency', function (string $currency, int $value, string $expectedOutput): void {
    expect(replaceNonBreakingSpaces(MoneyFormatter::formatFromMinor($value, Currency::fromCode($currency), 'en_US')))
        ->toBe($expectedOutput);
})->with([
    'two minor units'   => ['USD', 12345, '$123.45'],
    'no minor units'    => ['JPY', 12345, '¥12,345'],
    'three minor units' => ['BHD', 12345, 'BHD 12.345'],
]);

it('lets the decimals argument override the fraction digits of the currency', function (string $currency, int $value, int $decimals, string $expectedOutput): void {
    expect(replaceNonBreakingSpaces(MoneyFormatter::formatFromMinor($value, Currency::fromCode($currency), 'en_US', decimals: $decimals)))
        ->toBe($expectedOutput);
})->with([
    'yen with decimals' => ['JPY', 12345, 2, '¥12,345.00'],
    'dinar without'     => ['BHD', 12345, 0, 'BHD 12'],
    'dollars with four' => ['USD', 12345, 4, '$123.4500'],
]);

// Formatting and parsing back is the round trip an application makes around a form field, and it
// only holds while both directions take the scale from the same place.
it('parses back what it formats', function (string $currency, int $value): void {
    $formatted = MoneyFormatter::formatFromMinor($value, Currency::fromCode($currency), 'en_US', showCurrencySymbol: false);

    expect(MoneyFormatter::parseToMinor($formatted, Currency::fromCode($currency), 'en_US'))
        ->toBe((string) $value);
})->with([
    'two minor units'   => ['USD', 123456],
    'no minor units'    => ['JPY', 123456],
    'three minor units' => ['BHD', 123456],
    'krona'             => ['SEK', 123456],
]);

it('handles different locales properly', function (): void {
    // Testing French locale
    expect(replaceNonBreakingSpaces(MoneyFormatter::formatFromMinor(12345, Currency::fromCode('EUR'), 'fr_FR')))
        ->toContain('123,45');

    // Testing German locale
    expect(replaceNonBreakingSpaces(MoneyFormatter::formatFromMinor(12345, Currency::fromCode('EUR'), 'de_DE')))
        ->toContain('123,45');
});

it('parses money strings from different locales', function (): void {
    // US format
    expect(MoneyFormatter::parseToMinor('1,234.56', Currency::fromCode('USD'), 'en_US'))
        ->toEqual('123456');

    // European format
    expect(MoneyFormatter::parseToMinor('1.234,56', Currency::fromCode('EUR'), 'de_DE'))
        ->toEqual('123456');

    // Swedish format
    expect(MoneyFormatter::parseToMinor('1 234,56', Currency::fromCode('SEK'), 'sv_SE'))
        ->toEqual('123456');
});

// Without a symbol nothing needs ICU's data for the currency, only its minor unit, so an amount in a
// currency ISO 4217 has never heard of formats and parses back like any other.
it('formats and parses a currency outside ISO 4217', function (): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', 'BTC'],
    ]);

    $btc       = Currency::fromCode('BTC');
    $formatted = MoneyFormatter::formatFromMinor(100000000, $btc, 'en_US', showCurrencySymbol: false);

    expect($formatted)->toBe('1.00000000')
        ->and(MoneyFormatter::parseToMinor($formatted, $btc, 'en_US'))->toBe('100000000')
        ->and(MoneyFormatter::parseToMinor('0.00000001', $btc, 'en_US'))->toBe('1');
});

// ICU writes the code where it has no symbol, so the only thing that ever stood in the way was the
// formatter being handed ISO 4217 alone to place the decimal point by.
it('puts the code on a currency outside ISO 4217', function (): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', 'BTC'],
    ]);

    $btc = Currency::fromCode('BTC');

    expect(replaceNonBreakingSpaces(MoneyFormatter::formatFromMinor(100000000, $btc, 'en_US')))
        ->toBe('BTC 1.00000000')
        ->and(replaceNonBreakingSpaces(MoneyFormatter::format(new Money('100000000', $btc->toMoneyCurrency()), 'en_US')))
        ->toBe('BTC 1.00000000')
        ->and(replaceNonBreakingSpaces(MoneyFormatter::formatShortFromMinor(123456789000, $btc, 'en_US')))
        ->toBe('BTC 1.23K');
});

// The aggregate is ISO first, so a currency ISO covers is placed by ISO's data either way.
it('places an ISO currency by ISO data', function (string $currency, int $value, string $expectedOutput): void {
    config(['larapara.available_currencies' => ['USD', 'JPY', 'BHD']]);

    expect(replaceNonBreakingSpaces(MoneyFormatter::formatFromMinor($value, Currency::fromCode($currency), 'en_US')))
        ->toBe($expectedOutput);
})->with([
    'two minor units'   => ['USD', 123456, '$1,234.56'],
    'no minor units'    => ['JPY', 1234, '¥1,234'],
    'three minor units' => ['BHD', 1234567, 'BHD 1,234.567'],
]);

// Strict parsing accepts only what the locale itself writes, which is what the formatter writes, so
// the round trip holds in strict mode too — including the locales whose separators are not typeable.
it('parses back what it formats in strict mode', function (string $currency, string $locale): void {
    $formatted = MoneyFormatter::formatFromMinor(123456, Currency::fromCode($currency), $locale, showCurrencySymbol: false);

    expect(MoneyFormatter::parseToMinor($formatted, Currency::fromCode($currency), $locale, strict: true))
        ->toBe('123456');
})->with([
    'dollars'                  => ['USD', 'en_US'],
    'krona, no-break space'    => ['SEK', 'sv_SE'],
    'euro, dot grouping'       => ['EUR', 'de_DE'],
    'yen, no minor unit'       => ['JPY', 'ja_JP'],
    'dinar, three minor units' => ['BHD', 'ar_BH'],
]);

// ICU carries a currency as a three-character code: it truncates a longer one to its first three
// characters and refuses a shorter one outright. The bundled crypto list has 181 of them, so
// 1000SATS came out as "100" — an amount labelled as a currency it is not counted in.
it('writes a currency code ICU cannot carry as it is', function (string $currency, string $expectedOutput): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', '1000SATS', 'AUCTION', '1INCH', 'AI', 'BTC'],
    ]);

    expect(replaceNonBreakingSpaces(MoneyFormatter::formatFromMinor(100000000, Currency::fromCode($currency), 'en_US')))
        ->toBe($expectedOutput);
})->with([
    'digits at the front'     => ['1000SATS', '1000SATS 1.00000000'],
    'seven characters'        => ['AUCTION', 'AUCTION 1.00000000'],
    'five characters'         => ['1INCH', '1INCH 1.00000000'],
    'shorter than a code'     => ['AI', 'AI 1.00000000'],
    'three characters, as is' => ['BTC', 'BTC 1.00000000'],
]);

// The code stands in for the symbol, so ICU still decides where it goes, what space it is separated
// by and which digits and directional marks the locale writes.
it('places a currency code ICU cannot carry the way the locale places a symbol', function (string $locale, string $expectedOutput): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', '1000SATS'],
    ]);

    $sats = Currency::fromCode('1000SATS');

    expect(replaceNonBreakingSpaces(MoneyFormatter::formatFromMinor(-123456789, $sats, $locale)))
        ->toBe($expectedOutput);
})->with([
    'symbol in front'  => ['en_US', '-1000SATS 1.23456789'],
    'symbol behind'    => ['de_DE', '-1,23456789 1000SATS'],
    'minus of its own' => ['sv_SE', '−1,23456789 1000SATS'],
]);

// Every entry point that writes a currency, since each reaches ICU by a different route: a Money
// through the formatter of the money library, an abbreviation through a pattern of its own, and the
// ISO code where the configuration asks for codes rather than symbols.
it('writes a currency code ICU cannot carry from every entry point', function (): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', '1000SATS'],
    ]);

    $sats = Currency::fromCode('1000SATS');

    expect(replaceNonBreakingSpaces(MoneyFormatter::format(new Money('100000000', $sats->toMoneyCurrency()), 'en_US')))
        ->toBe('1000SATS 1.00000000')
        ->and(replaceNonBreakingSpaces(MoneyFormatter::formatShortFromMinor(123456789000, $sats, 'en_US')))
        ->toBe('1000SATS 1.23K')
        ->and(replaceNonBreakingSpaces(MoneyFormatter::formatShort(new Money('123456789000', $sats->toMoneyCurrency()), 'en_US')))
        ->toBe('1000SATS 1.23K');

    config(['larapara.intl_currency_symbol' => true]);

    expect(replaceNonBreakingSpaces(MoneyFormatter::formatFromMinor(100000000, $sats, 'en_US')))
        ->toBe('1000SATS 1.00000000');
});

// A parser that refuses its own output is a trap, and ICU has no reading of these codes to be strict
// about, so the notation the formatter writes is read back in both modes.
it('parses back a currency code ICU cannot carry', function (bool $strict): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', '1000SATS'],
    ]);

    $sats      = Currency::fromCode('1000SATS');
    $formatted = MoneyFormatter::formatFromMinor(123456789, $sats, 'en_US');

    expect(MoneyFormatter::parseToMinor($formatted, $sats, 'en_US', strict: $strict))
        ->toBe('123456789')
        ->and(MoneyFormatter::parseToMinor(MoneyFormatter::formatFromMinor(123456789, $sats, 'sv_SE'), $sats, 'sv_SE', strict: $strict))
        ->toBe('123456789');
})->with([
    'strict'  => [true],
    'lenient' => [false],
]);

// Only the currency being read, in strict mode as well: the code beside the number is read where it
// is the code of the currency asked for, and nothing else is.
it('refuses another currency beside the number in strict mode', function (): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', '1000SATS', 'AUCTION'],
    ]);

    expect(fn (): string => MoneyFormatter::parseToMinor('AUCTION 1.00000000', Currency::fromCode('1000SATS'), 'en_US', strict: true))
        ->toThrow(ParserException::class);
});

// The amount reaches ICU as a double, here and through the money library both, so an amount above
// 2**53 minor units was rendered as a neighbouring one: 900719925474099301 came out as
// $9,007,199,254,740,994.00, a dollar off an amount the casts store and read back exactly.
it('refuses an amount a double cannot carry digit for digit', function (): void {
    config(['larapara.available_currencies' => ['USD']]);

    $usd = Currency::fromCode('USD');

    expect(fn (): string => MoneyFormatter::formatFromMinor('900719925474099301', $usd, 'en_US'))
        ->toThrow(InvalidAmount::class)
        ->and(fn (): string => MoneyFormatter::format(new Money('900719925474099301', $usd->toMoneyCurrency()), 'en_US'))
        ->toThrow(InvalidAmount::class)
        ->and(fn (): string => MoneyFormatter::formatFromMinor('900719925474099301', $usd, 'en_US', showCurrencySymbol: false))
        ->toThrow(InvalidAmount::class);
});

it('formats the largest amount a double carries exactly', function (): void {
    config(['larapara.available_currencies' => ['USD']]);

    expect(MoneyFormatter::formatFromMinor('9007199254740992', Currency::fromCode('USD'), 'en_US'))
        ->toBe('$90,071,992,547,409.92');
});

// An abbreviation is an approximation by construction — $9.01Q says nothing about its last digit — so
// it is the one place an amount too large to render exactly is still rendered.
it('abbreviates an amount too large to format exactly', function (): void {
    config(['larapara.available_currencies' => ['USD']]);

    expect(replaceNonBreakingSpaces(MoneyFormatter::formatShortFromMinor('900719925474099301', Currency::fromCode('USD'), 'en_US')))
        ->toBe('$9.01Q');
});

// Strict mode accepts what the locale writes, and for a code ICU carries it is ICU that decides what
// that means: the exact space of the locale (a plain one is refused), the code where the symbol goes
// and nowhere else, and any number of decimals. A code ICU cannot carry is held to the same rules
// rather than to none, which is what stripping it from either end amounted to.
it('refuses a code ICU cannot carry out of its place in strict mode', function (string $input): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', '1000SATS'],
    ]);

    expect(fn (): string => MoneyFormatter::parseToMinor($input, Currency::fromCode('1000SATS'), 'en_US', strict: true))
        ->toThrow(ParserException::class);
})->with([
    'suffix where the locale writes a prefix' => ['1.00000000 1000SATS'],
    'a space the locale does not write'       => ['1000SATS 1.00000000'],
    'two of the space it does write'          => ["1000SATS\u{a0}\u{a0}1.00000000"],
    'suffix with no separator'                => ['1.000000001000SATS'],
]);

// ICU reads its own code with no space between it and the number, so this reads that too.
it('accepts a code ICU cannot carry with no separator, as ICU does', function (): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', '1000SATS'],
    ]);

    expect(MoneyFormatter::parseToMinor('1000SATS1.00000000', Currency::fromCode('1000SATS'), 'en_US', strict: true))
        ->toBe('100000000');
});

// Both signs and both placements: en_US writes the minus before the code, which is neither end of the
// string, so a negative amount in such a currency did not read back at all — in either mode.
it('parses back what it writes for a code ICU cannot carry, either sign', function (string $locale, int $amount, bool $strict): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', '1000SATS'],
    ]);

    $sats      = Currency::fromCode('1000SATS');
    $formatted = MoneyFormatter::formatFromMinor($amount, $sats, $locale);

    expect(MoneyFormatter::parseToMinor($formatted, $sats, $locale, strict: $strict))->toBe((string) $amount);
})->with([
    'code in front, strict'   => ['en_US', 123456789, true],
    'code in front, negative' => ['en_US', -123456789, true],
    'code behind, negative'   => ['sv_SE', -123456789, true],
    'dot grouping, negative'  => ['de_DE', -123456789, true],
    'lenient, negative'       => ['en_US', -123456789, false],
]);

// Lenient parsing is where the space a keyboard produces and a code written on the wrong side are
// forgiven, since that is the difference between the two modes.
it('forgives the placement of a code ICU cannot carry when lenient', function (string $input): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', '1000SATS'],
    ]);

    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode('1000SATS'), 'en_US'))->toBe('100000000');
})->with([
    'a plain space' => ['1000SATS 1.00000000'],
    'the suffix'    => ['1.00000000 1000SATS'],
]);
