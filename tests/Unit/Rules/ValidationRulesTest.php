<?php

use Illuminate\Support\Facades\Validator;
use Money\Currency as MoneyCurrency;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;
use Pelmered\LaraPara\Rules\MoneyString;
use Pelmered\LaraPara\Rules\SupportedCurrency;

beforeEach(function (): void {
    config(['larapara.currency_cache.type' => false]);
    config(['larapara.available_currencies' => ['USD', 'EUR', 'SEK', 'JPY', 'BHD']]);
    config(['larapara.parse.strict' => false]);
    config(['larapara.default_currency' => 'USD']);
    app()->setLocale('en_US');
});

function validateValue(mixed $value, object $rule): Illuminate\Validation\Validator
{
    return Validator::make(['amount' => $value], ['amount' => [$rule]]);
}

/**
 * Turns the marker 'localized' into 1234.56 written the way the locale writes it.
 *
 * Produced by the formatter rather than spelled out, since CLDR moves the separators of a locale
 * between ICU releases and the test matrix spans several of them.
 */
function localizedAmount(mixed $value, string $locale, string $currency): mixed
{
    return $value === 'localized'
        ? MoneyFormatter::formatFromMinor(123456, Currency::fromCode($currency), $locale, showCurrencySymbol: false)
        : $value;
}

/*
|--------------------------------------------------------------------------
| MoneyString
|--------------------------------------------------------------------------
*/

// The currency of the field written beside the amount is read, wherever a person put it.
it('passes an amount written with its own currency', function (string $value): void {
    expect(validateValue($value, new MoneyString('USD', 'en_US'))->passes())->toBeTrue();
})->with([
    'symbol'        => ['$1,234.56'],
    'trailing code' => ['12 USD'],
    'leading code'  => ['USD 12'],
]);

it('passes an amount its locale can read', function (string $locale, string $currency, mixed $value): void {
    expect(validateValue(localizedAmount($value, $locale, $currency), new MoneyString($currency, $locale))->passes())->toBeTrue();
})->with([
    'us number'            => ['en_US', 'USD', '1,234.56'],
    'german number'        => ['de_DE', 'EUR', '1.234,56'],
    'swedish number'       => ['sv_SE', 'SEK', 'localized'],
    'no separators'        => ['en_US', 'USD', '1234.56'],
    'no decimals'          => ['en_US', 'USD', '100'],
    'negative'             => ['en_US', 'USD', '-1234.56'],
    'an int'               => ['en_US', 'USD', 100],
    'a float'              => ['en_US', 'USD', 1.5],
    'forgiven separator'   => ['en_US', 'USD', '2,00'],
    'a dot as the decimal' => ['sv_SE', 'SEK', '1.5'],
]);

it('fails an amount no locale can read', function (mixed $value): void {
    expect(validateValue($value, new MoneyString('USD', 'en_US'))->passes())->toBeFalse();
})->with([
    'a word'                 => ['nonsense'],
    'trailing text'          => ['12 dollars'],
    'another currency'       => ['12 EUR'],
    'two decimal separators' => ['1.2.3'],
    'not finite'             => ['NaN'],
    'an array'               => [['1234']],
    'another locale'         => ['1.234,56'],
]);

it('leaves an empty amount to required and nullable', function (mixed $value): void {
    expect(validateValue($value, new MoneyString('USD', 'en_US'))->passes())->toBeTrue()
        ->and(Validator::make(['amount' => $value], ['amount' => ['required', new MoneyString('USD', 'en_US')]])->passes())
        ->toBeFalse();
})->with([
    'null'         => [null],
    'empty string' => [''],
]);

it('defaults the locale to the application locale', function (): void {
    app()->setLocale('sv_SE');

    expect(validateValue(localizedAmount('localized', 'sv_SE', 'SEK'), new MoneyString('SEK'))->passes())->toBeTrue()
        ->and(validateValue('1,234.56', new MoneyString('SEK'))->passes())->toBeFalse();
});

it('defaults the currency to the configured default', function (): void {
    config(['larapara.default_currency' => 'SEK']);

    expect(validateValue('1234.56', new MoneyString(locale: 'en_US'))->passes())->toBeTrue();
});

it('accepts either currency object', function (): void {
    expect(validateValue('1234.56', new MoneyString(Currency::fromCode('USD'), 'en_US'))->passes())->toBeTrue()
        ->and(validateValue('1234.56', new MoneyString(new MoneyCurrency('USD'), 'en_US'))->passes())->toBeTrue();
});

// The scale of a currency does not decide whether a string is a number, so an unsupported currency is
// left for SupportedCurrency to report rather than failing the amount too.
it('judges the amount on its own when the currency is not supported', function (): void {
    expect(validateValue('1234.56', new MoneyString('GBP', 'en_US'))->passes())->toBeTrue()
        ->and(validateValue('nonsense', new MoneyString('GBP', 'en_US'))->passes())->toBeFalse();
});

// The idiomatic call passes a request value straight in, and a client is free to send an array or a
// number there, so the constructor takes whatever arrives: the amount is judged on the default
// currency rather than the request 500ing before validation has run.
it('takes a currency of any shape a request can carry', function (mixed $currency): void {
    expect(validateValue('1234.56', new MoneyString($currency, 'en_US'))->passes())->toBeTrue()
        ->and(validateValue('nonsense', new MoneyString($currency, 'en_US'))->passes())->toBeFalse();
})->with([
    'an array'     => [['USD']],
    'a nested one' => [['code' => 'USD']],
    'a number'     => [840],
    'a boolean'    => [true],
    'null'         => [null],
]);

it('shows the shape it expects in the message', function (string $locale, string $currency, string $expectedExample): void {
    $validator = validateValue('nonsense', new MoneyString($currency, $locale));

    expect(replaceNonBreakingSpaces($validator->errors()->first('amount')))
        ->toBe('The amount field must be a valid amount, such as '.$expectedExample.'.');
})->with([
    'en_US' => ['en_US', 'USD', '1,234.56'],
    'de_DE' => ['de_DE', 'EUR', '1.234,56'],
    'sv_SE' => ['sv_SE', 'SEK', '1 234,56'],
]);

/*
|--------------------------------------------------------------------------
| MoneyString, strict mode
|--------------------------------------------------------------------------
*/

it('forgives a separator out of place unless it is strict', function (string $locale, string $currency, string $value): void {
    expect(validateValue($value, new MoneyString($currency, $locale))->passes())->toBeTrue()
        ->and(validateValue($value, new MoneyString($currency, $locale, strict: true))->passes())->toBeFalse();
})->with([
    'dropped grouping separator' => ['en_US', 'USD', '2,00'],
    'a dot in a space locale'    => ['sv_SE', 'SEK', '1.5'],
    'a dot in a dot locale'      => ['de_DE', 'EUR', '1.5'],
]);

it('accepts what the locale itself writes even when it is strict', function (string $locale, string $currency, string $value): void {
    expect(validateValue(localizedAmount($value, $locale, $currency), new MoneyString($currency, $locale, strict: true))->passes())->toBeTrue();
})->with([
    'us number'         => ['en_US', 'USD', '1,234.56'],
    'german number'     => ['de_DE', 'EUR', '1.234,56'],
    'swedish number'    => ['sv_SE', 'SEK', 'localized'],
    'without grouping'  => ['en_US', 'USD', '1234.56'],
    'german without it' => ['de_DE', 'EUR', '1234,56'],
]);

it('takes strictness from the config when it is not given', function (): void {
    config(['larapara.parse.strict' => true]);

    expect(validateValue('2,00', new MoneyString('USD', 'en_US'))->passes())->toBeFalse()
        ->and(validateValue('2,00', new MoneyString('USD', 'en_US', strict: false))->passes())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| SupportedCurrency
|--------------------------------------------------------------------------
*/

it('passes a supported currency', function (mixed $value): void {
    expect(validateValue($value, new SupportedCurrency)->passes())->toBeTrue();
})->with([
    'a code'              => ['SEK'],
    'lower case'          => ['sek'],
    'mixed case'          => ['Sek'],
    'padded'              => [' SEK '],
    'a LaraPara currency' => [fn (): Currency => Currency::fromCode('USD')],
    'a Money currency'    => [fn (): MoneyCurrency => new MoneyCurrency('USD')],
]);

it('fails a currency this application does not support', function (mixed $value): void {
    expect(validateValue($value, new SupportedCurrency)->passes())->toBeFalse();
})->with([
    'not configured'    => ['GBP'],
    'crypto without it' => ['BTC'],
    'not a currency'    => ['nonsense'],
    'a number'          => ['123'],
    'too long'          => ['USDUSD'],
    'an array'          => [['SEK']],
]);

it('narrows the allow list further when given one', function (): void {
    expect(validateValue('USD', new SupportedCurrency(['USD', 'EUR']))->passes())->toBeTrue()
        ->and(validateValue('eur', new SupportedCurrency(['USD', 'EUR']))->passes())->toBeTrue()
        ->and(validateValue('SEK', new SupportedCurrency(['USD', 'EUR']))->passes())->toBeFalse();
});

it('normalizes the allow list it is given', function (): void {
    expect(validateValue('SEK', new SupportedCurrency([' sek ']))->passes())->toBeTrue();
});

// A list narrowed to something the configuration does not have would leave a field nothing can
// satisfy, so it is refused where it is written rather than at every submission.
it('refuses an allow list the configuration does not support', function (): void {
    expect(fn (): SupportedCurrency => new SupportedCurrency(['USD', 'GBP']))
        ->toThrow(UnsupportedCurrency::class, 'GBP');
});

it('leaves an empty currency to required and nullable', function (mixed $value): void {
    expect(validateValue($value, new SupportedCurrency)->passes())->toBeTrue()
        ->and(Validator::make(['amount' => $value], ['amount' => ['required', new SupportedCurrency]])->passes())
        ->toBeFalse();
})->with([
    'null'         => [null],
    'empty string' => [''],
]);

it('says which field is not a supported currency', function (): void {
    $validator = Validator::make(['price_currency' => 'GBP'], ['price_currency' => [new SupportedCurrency]]);

    expect($validator->errors()->first('price_currency'))
        ->toBe('The selected price currency is not a supported currency.');
});

// Anything the rule passes has to be storable, or validation and the casts disagree.
it('passes only currencies the casts accept', function (string $value): void {
    expect(validateValue($value, new SupportedCurrency)->passes())->toBeTrue()
        ->and(Currency::toCode($value))->toBe('SEK');
})->with(['SEK', 'sek', ' SEK ']);

it('registers its translations under the package namespace', function (): void {
    expect(trans('larapara::validation.supported_currency'))
        ->toBe('The selected :attribute is not a supported currency.');
});

// A currency ISO 4217 has no data for used to raise UnknownCurrencyException from inside the parser,
// which is not a failure a request can report — the rule either passes or fails now.
it('validates an amount in a currency outside ISO 4217', function (mixed $value, bool $passes): void {
    config([
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', 'BTC'],
    ]);

    expect(validateValue($value, new MoneyString('BTC', 'en_US'))->passes())->toBe($passes);
})->with([
    'the amount of a whole coin' => ['1.00000000', true],
    'the smallest unit'          => ['0.00000001', true],
    'not a number'               => ['not a number', false],
]);
