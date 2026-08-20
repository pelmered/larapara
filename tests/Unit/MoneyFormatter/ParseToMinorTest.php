<?php

use Money\Currency as MoneyCurrency;
use Money\Exception\ParserException;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

beforeEach(function (): void {
    config(['larapara.currency_cache.type' => false]);
    config(['larapara.available_currencies' => ['USD', 'EUR', 'SEK', 'CHF', 'EGP', 'JPY', 'BHD', 'CLF']]);
});

/**
 * Fills the separators of a locale into a template, where "_" stands for its grouping separator and
 * "~" for its decimal one.
 *
 * Taken from ICU rather than written out: CLDR reshapes these between releases — the space of sv_SE,
 * the apostrophe of de_CH and the Arabic-Indic separators have all moved — and the test matrix spans
 * several ICU versions. What these tests are about is the rules, not which character CLDR picked.
 */
function localizedNumber(string $template, string $locale, string $currency): string
{
    $formattingRules = MoneyFormatter::getFormattingRules($locale, Currency::fromCode($currency));

    return strtr($template, [
        '_' => $formattingRules->groupingSeparator,
        '~' => $formattingRules->decimalSeparator,
    ]);
}

/*
|--------------------------------------------------------------------------
| The number a locale writes
|--------------------------------------------------------------------------
|
| Whatever else the rules do, a string written the way its own locale writes
| numbers has to parse to the amount it reads as.
|
*/

it('parses a number written the way its locale writes it', function (string $locale, string $currency, string $template, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor(localizedNumber($template, $locale, $currency), Currency::fromCode($currency), $locale))
        ->toBe($expectedOutput);
})->with([
    'comma grouping, dot decimal' => ['en_US', 'USD', '1_234~56', '123456'],
    'dot grouping, comma decimal' => ['de_DE', 'EUR', '1_234~56', '123456'],
    'space grouping'              => ['sv_SE', 'SEK', '1_234~56', '123456'],
    'narrow space grouping'       => ['fr_FR', 'EUR', '1_234~56', '123456'],
    'apostrophe grouping'         => ['de_CH', 'CHF', '1_234~56', '123456'],
    'arabic-indic separators'     => ['ar_EG', 'EGP', '١_٢٣٤~٥٦', '123456'],
    'no separators at all'        => ['en_US', 'USD', '1234~56', '123456'],
    'no decimals'                 => ['en_US', 'USD', '100', '10000'],
    'several grouping separators' => ['en_US', 'USD', '1_000_000~00', '100000000'],
]);

// An amount formatted by this package has to parse back to the amount it came from.
it('parses back what it formatted', function (string $locale, string $currency, int $minorUnit): void {
    foreach ([1, 999, 1999, 123456, 100000000, -1999] as $amount) {
        $formatted = MoneyFormatter::formatFromMinor(
            $amount,
            Currency::fromCode($currency),
            $locale,
            decimals: $minorUnit,
            showCurrencySymbol: false
        );

        expect(MoneyFormatter::parseToMinor($formatted, Currency::fromCode($currency), $locale))
            ->toBe((string) $amount);
    }
})->with([
    'comma grouping'          => ['en_US', 'USD', 2],
    'dot grouping'            => ['de_DE', 'EUR', 2],
    'space grouping'          => ['sv_SE', 'SEK', 2],
    'narrow space grouping'   => ['fr_FR', 'EUR', 2],
    'apostrophe grouping'     => ['de_CH', 'CHF', 2],
    'arabic-indic separators' => ['ar_EG', 'EGP', 2],
    'no minor units'          => ['en_US', 'JPY', 0],
    'three minor units'       => ['en_US', 'BHD', 3],
    'four minor units'        => ['en_US', 'CLF', 4],
]);

/*
|--------------------------------------------------------------------------
| Rule 1 — a dot is read as the locale's decimal separator
|--------------------------------------------------------------------------
|
| A dot means a decimal point on nearly every keyboard, spreadsheet and
| programming language. Only a dot the locale itself refused reaches this
| rule, so a dot in a genuine grouping position keeps its meaning.
|
*/

it('reads a dot as the decimal separator of the locale', function (string $locale, string $currency, string $template, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor(localizedNumber($template, $locale, $currency), Currency::fromCode($currency), $locale))
        ->toBe($expectedOutput);
})->with([
    // Locales that group with dots: the dot would otherwise be dropped as grouping.
    'de_DE, one decimal'          => ['de_DE', 'EUR', '1.5', '150'],
    'de_DE, two decimals'         => ['de_DE', 'EUR', '1.50', '150'],
    'de_DE, no grouping position' => ['de_DE', 'EUR', '12.34', '1234'],
    'es_ES'                       => ['es_ES', 'EUR', '1.5', '150'],
    'it_IT'                       => ['it_IT', 'EUR', '1.5', '150'],
    'pt_BR'                       => ['pt_BR', 'USD', '1.5', '150'],
    'nl_NL'                       => ['nl_NL', 'EUR', '1.5', '150'],
    'tr_TR'                       => ['tr_TR', 'USD', '1.5', '150'],
    'id_ID'                       => ['id_ID', 'USD', '1.5', '150'],
    'da_DK'                       => ['da_DK', 'EUR', '1.5', '150'],
    // Locales that group with a space: the dot is neither separator and used to be refused.
    'sv_SE, one decimal'   => ['sv_SE', 'SEK', '1.5', '150'],
    'sv_SE, two decimals'  => ['sv_SE', 'SEK', '1.50', '150'],
    'sv_SE, with grouping' => ['sv_SE', 'SEK', '1_234.56', '123456'],
    'sv_SE, plain'         => ['sv_SE', 'SEK', '1234.56', '123456'],
    'fr_FR'                => ['fr_FR', 'EUR', '1.5', '150'],
    'fi_FI'                => ['fi_FI', 'EUR', '1.5', '150'],
    'pl_PL'                => ['pl_PL', 'EUR', '1.5', '150'],
    'cs_CZ'                => ['cs_CZ', 'EUR', '1.5', '150'],
    'ru_RU'                => ['ru_RU', 'EUR', '1.5', '150'],
    'nb_NO'                => ['nb_NO', 'EUR', '1.5', '150'],
    'uk_UA'                => ['uk_UA', 'EUR', '1.5', '150'],
    // A locale whose separators are not ASCII at all.
    'ar_EG' => ['ar_EG', 'EGP', '1.5', '150'],
    'fa_IR' => ['fa_IR', 'EUR', '1.5', '150'],
]);

it('leaves a dot that is in a grouping position as grouping', function (string $locale, string $currency, string $input, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode($currency), $locale))
        ->toBe($expectedOutput);
})->with([
    'de_DE, thousands'         => ['de_DE', 'EUR', '1.234', '123400'],
    'de_DE, millions'          => ['de_DE', 'EUR', '1.234.567', '123456700'],
    'de_DE, with the decimals' => ['de_DE', 'EUR', '1.234,56', '123456'],
    'es_ES, thousands'         => ['es_ES', 'EUR', '1.234', '123400'],
]);

it('needs no second reading where the dot already is the decimal separator', function (string $locale, string $currency, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor('1.5', Currency::fromCode($currency), $locale))
        ->toBe($expectedOutput);
})->with([
    'en_US'                 => ['en_US', 'USD', '150'],
    'en_GB'                 => ['en_GB', 'USD', '150'],
    'de_CH'                 => ['de_CH', 'CHF', '150'],
    'ja_JP, no minor units' => ['ja_JP', 'JPY', '2'],
]);

/*
|--------------------------------------------------------------------------
| Rule 2 — a grouping separator out of position is dropped
|--------------------------------------------------------------------------
|
| It carries no value of its own, so "2,00" in en_US is the same amount as
| "200". See: https://github.com/pelmered/larapara/issues/20
|
*/

it('drops a grouping separator that is out of position', function (string $locale, string $currency, string $template, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor(localizedNumber($template, $locale, $currency), Currency::fromCode($currency), $locale))
        ->toBe($expectedOutput);
})->with([
    'in the decimals'         => ['en_US', 'USD', '2_00', '20000'],
    'no grouping position'    => ['en_US', 'USD', '12_34', '123400'],
    'a single digit group'    => ['en_US', 'USD', '1_2', '1200'],
    'several out of position' => ['en_US', 'USD', '1_2_3', '12300'],
    'before the decimals'     => ['en_US', 'USD', '12_34~56', '123456'],
    'apostrophe grouping'     => ['de_CH', 'CHF', '2_00', '20000'],
    'arabic-indic grouping'   => ['ar_EG', 'EGP', '٢_٠٠', '20000'],
]);

it('keeps a grouping separator that is in position', function (string $input, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode('USD'), 'en_US'))
        ->toBe($expectedOutput);
})->with([
    'thousands'         => ['1,234', '123400'],
    'millions'          => ['1,234,567', '123456700'],
    'with the decimals' => ['1,234.56', '123456'],
]);

// Grouping never follows the decimal separator, so such a string is malformed rather than merely out
// of position — dropping the separator would move the decimal point and invent an amount.
it('refuses a grouping separator that follows the decimal separator', function (string $locale, string $currency, string $template): void {
    expect(fn (): string => MoneyFormatter::parseToMinor(localizedNumber($template, $locale, $currency), Currency::fromCode($currency), $locale))
        ->toThrow(ParserException::class);
})->with([
    'en_US, dot grouping input' => ['en_US', 'USD', '1~234_56'],
    'en_US, trailing group'     => ['en_US', 'USD', '12~34_56'],
    'de_CH, trailing group'     => ['de_CH', 'CHF', '12~34_56'],
]);

// The two rules together cannot rescue a string that reads as another locale's number.
it('refuses a number written for a different locale', function (string $locale, string $currency, string $input): void {
    expect(fn (): string => MoneyFormatter::parseToMinor($input, Currency::fromCode($currency), $locale))
        ->toThrow(ParserException::class);
})->with([
    'us number in a swedish locale' => ['sv_SE', 'SEK', '1,234.56'],
    'us number in a german locale'  => ['de_DE', 'EUR', '1,234.56'],
]);

/*
|--------------------------------------------------------------------------
| The whole string has to be a number
|--------------------------------------------------------------------------
*/

it('refuses a string that is not a number in its entirety', function (string $locale, string $input): void {
    expect(fn (): string => MoneyFormatter::parseToMinor($input, Currency::fromCode('USD'), $locale))
        ->toThrow(ParserException::class, 'The value must be a valid numeric value.');
})->with([
    'trailing currency code'   => ['en_US', '12 USD'],
    'trailing letters'         => ['en_US', '12abc'],
    'leading letters'          => ['en_US', 'abc12'],
    'two decimal separators'   => ['en_US', '1.2.3'],
    'hexadecimal'              => ['en_US', '0x1A'],
    'a word'                   => ['en_US', 'invalid'],
    'not a number at all'      => ['en_US', 'not-a-number'],
    'not finite'               => ['en_US', 'NaN'],
    'infinite'                 => ['en_US', 'INF'],
    'a leading plus'           => ['en_US', '+12.34'],
    'accounting parentheses'   => ['en_US', '(12.34)'],
    'whitespace only'          => ['en_US', '   '],
    'a separator only'         => ['en_US', ','],
    'a decimal separator only' => ['en_US', '.'],
]);

it('keeps the original exception as the cause where there is one', function (): void {
    try {
        MoneyFormatter::parseToMinor('nonsense', Currency::fromCode('USD'), 'en_US');
    } catch (ParserException $parserException) {
        expect($parserException->getMessage())->toBe('The value must be a valid numeric value.');

        return;
    }

    $this->fail('parseToMinor did not throw');
});

/*
|--------------------------------------------------------------------------
| Amounts, signs and minor units
|--------------------------------------------------------------------------
*/

it('parses an empty value as an empty string', function (?string $input): void {
    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode('USD'), 'en_US'))->toBe('');
})->with([
    'null'         => [null],
    'empty string' => [''],
]);

it('parses zero', function (string $input): void {
    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode('USD'), 'en_US'))->toBe('0');
})->with(['0', '0.00', '0.001', '-0']);

it('parses a negative amount', function (string $locale, string $currency, string $template, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor(localizedNumber($template, $locale, $currency), Currency::fromCode($currency), $locale))
        ->toBe($expectedOutput);
})->with([
    'hyphen minus'          => ['en_US', 'USD', '-1.5', '-150'],
    'with grouping'         => ['en_US', 'USD', '-1_234~56', '-123456'],
    'dot as decimal'        => ['de_DE', 'EUR', '-1.5', '-150'],
    'own decimal separator' => ['de_DE', 'EUR', '-1~5', '-150'],
    'space grouping'        => ['sv_SE', 'SEK', '-1_234~56', '-123456'],
    'true minus sign'       => ['sv_SE', 'SEK', "\u{2212}1~5", '-150'],
    'dropped grouping'      => ['en_US', 'USD', '-2_00', '-20000'],
]);

it('parses to the minor unit of the currency', function (string $currency, string $input, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode($currency), 'en_US'))
        ->toBe($expectedOutput);
})->with([
    'two minor units'         => ['USD', '12.34', '1234'],
    'no minor units'          => ['JPY', '1234', '1234'],
    'no minor units, grouped' => ['JPY', '1,234', '1234'],
    'three minor units'       => ['BHD', '1.234', '1234'],
    'three, grouped'          => ['BHD', '1,234.567', '1234567'],
    'four minor units'        => ['CLF', '1.2345', '12345'],
]);

it('rounds half up past the minor unit of the currency', function (string $currency, string $input, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode($currency), 'en_US'))
        ->toBe($expectedOutput);
})->with([
    'half a minor unit up'   => ['USD', '1.005', '101'],
    'a well known float'     => ['USD', '2.675', '268'],
    'another one'            => ['USD', '0.145', '15'],
    'below the half'         => ['USD', '1.004', '100'],
    'above the half'         => ['USD', '1.006', '101'],
    'no minor units'         => ['JPY', '1234.5', '1235'],
    'past three minor units' => ['BHD', '1.2345', '1235'],
]);

// PHP's `precision` ini setting deforms a float above 14 significant digits on its way to a string,
// which silently changed the amount.
it('parses an amount above the float printing precision', function (string $input, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode('USD'), 'en_US'))
        ->toBe($expectedOutput);
})->with([
    'sixteen digits' => ['1234567890123456', '123456789012345600'],
    'fifteen digits' => ['999999999999999', '99999999999999900'],
    'with decimals'  => ['12345678901234.56', '1234567890123456'],
]);

it('accepts the amount either side of surrounding whitespace', function (string $input): void {
    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode('USD'), 'en_US'))->toBe('1234');
})->with(['12.34', ' 12.34', '12.34 ', "\t12.34\n", '  12.34  ']);

it('parses the digits of the locale', function (string $locale, string $currency, string $template, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor(localizedNumber($template, $locale, $currency), Currency::fromCode($currency), $locale))
        ->toBe($expectedOutput);
})->with([
    'eastern arabic-indic'      => ['ar_EG', 'EGP', '١~٥', '150'],
    'extended arabic-indic'     => ['fa_IR', 'EUR', '۱~۵', '150'],
    'latin in an arabic locale' => ['ar_EG', 'EGP', '1.5', '150'],
]);

it('parses a Money currency as well as a LaraPara one', function (): void {
    expect(MoneyFormatter::parseToMinor('1.5', new MoneyCurrency('USD'), 'en_US'))->toBe('150')
        ->and(MoneyFormatter::parseToMinor('1.5', Currency::fromCode('USD'), 'en_US'))->toBe('150');
});

/*
|--------------------------------------------------------------------------
| Strict mode
|--------------------------------------------------------------------------
|
| Strict mode is the first reading only: what the locale itself writes, and
| nothing the separator rules would have rescued.
|
*/

it('refuses what the separator rules would have rescued when it is strict', function (string $locale, string $currency, string $template): void {
    expect(fn (): string => MoneyFormatter::parseToMinor(localizedNumber($template, $locale, $currency), Currency::fromCode($currency), $locale, strict: true))
        ->toThrow(ParserException::class, 'The value must be a valid numeric value.');
})->with([
    'dropped grouping separator'  => ['en_US', 'USD', '2_00'],
    'grouping out of position'    => ['en_US', 'USD', '12_34'],
    'a dot in a dot locale'       => ['de_DE', 'EUR', '1.5'],
    'a dot in a space locale'     => ['sv_SE', 'SEK', '1.5'],
    'a dot in a narrow space one' => ['fr_FR', 'EUR', '1.5'],
]);

it('accepts what the locale itself writes when it is strict', function (string $locale, string $currency, string $template, string $expectedOutput): void {
    expect(MoneyFormatter::parseToMinor(localizedNumber($template, $locale, $currency), Currency::fromCode($currency), $locale, strict: true))
        ->toBe($expectedOutput);
})->with([
    'us number'              => ['en_US', 'USD', '1_234~56', '123456'],
    'us number, no grouping' => ['en_US', 'USD', '1234~56', '123456'],
    'german number'          => ['de_DE', 'EUR', '1_234~56', '123456'],
    'german, no grouping'    => ['de_DE', 'EUR', '1234~56', '123456'],
    'swedish number'         => ['sv_SE', 'SEK', '1_234~56', '123456'],
    'whole units'            => ['en_US', 'USD', '100', '10000'],
    'negative'               => ['en_US', 'USD', '-1_234~56', '-123456'],
]);

it('takes strictness from the config when it is not given', function (): void {
    config(['larapara.parse.strict' => true]);

    expect(fn (): string => MoneyFormatter::parseToMinor('2,00', Currency::fromCode('USD'), 'en_US'))
        ->toThrow(ParserException::class);

    expect(MoneyFormatter::parseToMinor('2,00', Currency::fromCode('USD'), 'en_US', strict: false))->toBe('20000');
});

it('is lenient when the config does not mention strictness at all', function (): void {
    config(['larapara.parse' => null]);

    expect(MoneyFormatter::parseToMinor('2,00', Currency::fromCode('USD'), 'en_US'))->toBe('20000');
});

it('refuses an amount that is not a number in either mode', function (bool $strict): void {
    expect(fn (): string => MoneyFormatter::parseToMinor('nonsense', Currency::fromCode('USD'), 'en_US', strict: $strict))
        ->toThrow(ParserException::class);
})->with([true, false]);

it('parses an empty value the same way in either mode', function (bool $strict): void {
    expect(MoneyFormatter::parseToMinor('', Currency::fromCode('USD'), 'en_US', strict: $strict))->toBe('')
        ->and(MoneyFormatter::parseToMinor(null, Currency::fromCode('USD'), 'en_US', strict: $strict))->toBe('');
})->with([true, false]);

/*
|--------------------------------------------------------------------------
| Separators people actually type
|--------------------------------------------------------------------------
|
| A locale's grouping separator is one member of a class — sv_SE groups with
| a no-break space, fr_FR with a narrow one, de_CH with a right single
| quotation mark — and a keyboard produces the plain member. ICU reads any of
| them where the grouping belongs, so the second reading knows them all too.
| Otherwise which member CLDR names decides whose input is forgiven, and that
| differs between ICU releases.
|
*/

it('reads any member of the separator class as grouping, in position', function (string $locale, string $currency, string $separator): void {
    $decimalSeparator = MoneyFormatter::getFormattingRules($locale, Currency::fromCode($currency))->decimalSeparator;

    expect(MoneyFormatter::parseToMinor('1'.$separator.'234'.$decimalSeparator.'56', Currency::fromCode($currency), $locale))
        ->toBe('123456');
})->with([
    'de_CH, apostrophe'      => ['de_CH', 'CHF', "\u{0027}"],
    'de_CH, right quote'     => ['de_CH', 'CHF', "\u{2019}"],
    'sv_SE, space'           => ['sv_SE', 'SEK', "\u{0020}"],
    'sv_SE, no-break space'  => ['sv_SE', 'SEK', "\u{00a0}"],
    'sv_SE, narrow no-break' => ['sv_SE', 'SEK', "\u{202f}"],
    'sv_SE, thin space'      => ['sv_SE', 'SEK', "\u{2009}"],
    'fr_FR, space'           => ['fr_FR', 'EUR', "\u{0020}"],
    'fr_FR, no-break space'  => ['fr_FR', 'EUR', "\u{00a0}"],
    'fr_FR, narrow no-break' => ['fr_FR', 'EUR', "\u{202f}"],
]);

it('reads any member of the separator class as grouping, out of position', function (string $locale, string $currency, string $separator): void {
    expect(MoneyFormatter::parseToMinor('2'.$separator.'00', Currency::fromCode($currency), $locale))
        ->toBe('20000');
})->with([
    'de_CH, apostrophe'      => ['de_CH', 'CHF', "\u{0027}"],
    'de_CH, right quote'     => ['de_CH', 'CHF', "\u{2019}"],
    'sv_SE, space'           => ['sv_SE', 'SEK', "\u{0020}"],
    'sv_SE, no-break space'  => ['sv_SE', 'SEK', "\u{00a0}"],
    'sv_SE, narrow no-break' => ['sv_SE', 'SEK', "\u{202f}"],
    'sv_SE, thin space'      => ['sv_SE', 'SEK', "\u{2009}"],
    'fr_FR, space'           => ['fr_FR', 'EUR', "\u{0020}"],
    'fr_FR, no-break space'  => ['fr_FR', 'EUR', "\u{00a0}"],
    'fr_FR, narrow no-break' => ['fr_FR', 'EUR', "\u{202f}"],
]);

// The class is the class of the locale's own separator, not a free-for-all: a space is not a grouping
// separator in a locale that groups with a comma.
it('reads only the class of the locale it is given', function (string $locale, string $currency, string $input): void {
    expect(fn (): string => MoneyFormatter::parseToMinor($input, Currency::fromCode($currency), $locale))
        ->toThrow(ParserException::class);
})->with([
    'space in a comma locale'      => ['en_US', 'USD', "2\u{00a0}00"],
    'apostrophe in a comma locale' => ['en_US', 'USD', "2\u{2019}00"],
    'apostrophe in a space locale' => ['sv_SE', 'SEK', "2\u{2019}00"],
    'space in a dot locale'        => ['de_DE', 'EUR', "2\u{00a0}00"],
]);

it('still refuses a separator class member that follows the decimal separator', function (string $locale, string $currency, string $input): void {
    expect(fn (): string => MoneyFormatter::parseToMinor($input, Currency::fromCode($currency), $locale))
        ->toThrow(ParserException::class);
})->with([
    'de_CH, apostrophe'  => ['de_CH', 'CHF', "12.34\u{0027}56"],
    'de_CH, right quote' => ['de_CH', 'CHF', "12.34\u{2019}56"],
    'sv_SE, space'       => ['sv_SE', 'SEK', "12,34\u{0020}56"],
]);

// The second reading asks ICU for the separators of the locale, which used to be a ValueError rather
// than a parse for the empty locale that every other intl call reads as the default one.
it('gives a misplaced separator a second reading in the default locale too', function (): void {
    expect(MoneyFormatter::parseToMinor('2,00', Currency::fromCode('USD'), ''))->toBe('20000');
});

/*
|--------------------------------------------------------------------------
| The currency written beside the amount
|--------------------------------------------------------------------------
*/

// The formatter writes the symbol; a field that displays it posts it back, and a parser that refuses
// its own output is a trap.
it('reads back what the formatter writes, symbol and all', function (string $currency, string $locale, bool $intlSymbol): void {
    config(['larapara.intl_currency_symbol' => $intlSymbol]);

    $formatted = MoneyFormatter::formatFromMinor(123456, Currency::fromCode($currency), $locale);

    expect(MoneyFormatter::parseToMinor($formatted, Currency::fromCode($currency), $locale))->toBe('123456')
        ->and(MoneyFormatter::parseToMinor($formatted, Currency::fromCode($currency), $locale, strict: true))->toBe('123456');
})->with([
    'dollars, symbol'    => ['USD', 'en_US', false],
    'dollars, code'      => ['USD', 'en_US', true],
    'krona, symbol'      => ['SEK', 'sv_SE', false],
    'krona, code'        => ['SEK', 'sv_SE', true],
    'euro in german'     => ['EUR', 'de_DE', false],
    'yen, no minor unit' => ['JPY', 'ja_JP', false],
]);

it('reads an amount written with its currency', function (string $locale, string $currency, string $input, string $expected): void {
    expect(MoneyFormatter::parseToMinor($input, Currency::fromCode($currency), $locale))->toBe($expected);
})->with([
    'symbol'                                  => ['en_US', 'USD', '$1,234.56', '123456'],
    'symbol, negative'                        => ['en_US', 'USD', '-$1,234.56', '-123456'],
    'symbol, no grouping'                     => ['en_US', 'USD', '$1234.56', '123456'],
    'code where the locale puts the currency' => ['en_US', 'USD', 'USD 12', '1200'],
    'suffix symbol'                           => ['sv_SE', 'SEK', "1\u{a0}234,56\u{a0}kr", '123456'],
    'suffix, typed spaces'                    => ['sv_SE', 'SEK', '1 234,56 kr', '123456'],
]);

// ICU reads any currency's symbol, so the one it reads has to be the one asked for.
it('refuses an amount written with another currency', function (string $input): void {
    expect(fn (): string => MoneyFormatter::parseToMinor($input, Currency::fromCode('USD'), 'en_US'))
        ->toThrow(ParserException::class);
})->with([
    'euro symbol' => ['€10'],
    'euro code'   => ['EUR 10'],
    'krona'       => ['10 kr'],
]);

// Strict mode is what the locale writes, in the notation this configuration writes it in.
it('refuses the other notation and typed spaces in strict mode', function (string $locale, string $currency, string $input): void {
    expect(fn (): string => MoneyFormatter::parseToMinor($input, Currency::fromCode($currency), $locale, strict: true))
        ->toThrow(ParserException::class);
})->with([
    'the code while the symbol is configured' => ['en_US', 'USD', 'USD 12'],
    'spaces a keyboard produced'              => ['sv_SE', 'SEK', '1 234,56 kr'],
]);
