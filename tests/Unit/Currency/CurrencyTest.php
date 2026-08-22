<?php

declare(strict_types=1);

use Money\Currency as MoneyCurrency;
use Money\Money;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;

/**
 * @test
 */
it('can be created from currency code', function (string $code): void {
    config(['larapara.available_currencies' => ['USD', 'EUR', 'SEK']]);

    $currency = Currency::fromCode($code);

    expect($currency)->toBeInstanceOf(Currency::class)
        ->and($currency->getCode())->toBe(strtoupper($code));
})->with(['USD', 'EUR', 'SEK']);

/**
 * @test
 */
it('throws exception for unsupported currency', function (): void {
    config(['larapara.available_currencies' => ['USD', 'EUR', 'SEK']]);

    expect(fn (): Currency => Currency::fromCode('PHP'))->toThrow(UnsupportedCurrency::class);
    expect(fn (): Currency => Currency::fromCode('INR'))->toThrow(UnsupportedCurrency::class);
});

/**
 * @test
 */
it('handles different case inputs', function (): void {
    config(['larapara.available_currencies' => ['USD']]);

    $currencyLower = Currency::fromCode('usd');
    $currencyUpper = Currency::fromCode('USD');
    $currencyMixed = Currency::fromCode('UsD');

    expect($currencyLower->getCode())->toBe('USD')
        ->and($currencyUpper->getCode())->toBe('USD')
        ->and($currencyMixed->getCode())->toBe('USD');
});

/**
 * @test
 */
it('maintains case consistency in toString', function (): void {
    config(['larapara.available_currencies' => ['USD']]);

    $currency = Currency::fromCode('usd');

    expect((string) $currency)->toBe('USD');
});

// A Money carries a currency that is a code and nothing else, so the name and the minor unit of the
// currency an amount is counted in are read back out of the registry through that code.
it('resolves a currency from a Money currency and from a Money', function (): void {
    config(['larapara.available_currencies' => ['USD', 'SEK']]);

    $money = new Money('123456', new MoneyCurrency('SEK'));

    expect(Currency::fromMoneyCurrency(new MoneyCurrency('SEK')))
        ->toBeInstanceOf(Currency::class)
        ->getCode()->toBe('SEK')
        ->and(Currency::fromMoney($money))
        ->toBeInstanceOf(Currency::class)
        ->getCode()->toBe('SEK');
});

// A Money can be built in any currency the money library knows, which is every ISO code — this
// configuration is the narrower list, and the code is checked against it here as everywhere else.
it('refuses a Money in a currency this configuration does not know', function (): void {
    config(['larapara.available_currencies' => ['USD', 'SEK']]);

    $money = new Money('123456', new MoneyCurrency('GBP'));

    expect(fn (): Currency => Currency::fromMoney($money))->toThrow(UnsupportedCurrency::class);
});

// Two currencies are the same currency when they are the same code: the registry hands out an object
// per lookup, so comparing the objects themselves would make a currency unequal to itself.
it('compares currencies by their code', function (): void {
    config(['larapara.available_currencies' => ['USD', 'SEK']]);

    expect(Currency::fromCode('SEK')->equals(Currency::fromCode('sek')))->toBeTrue()
        ->and(Currency::fromCode('SEK')->equals(Currency::fromCode('USD')))->toBeFalse();
});

it('converts to a Money currency of the same code', function (): void {
    config(['larapara.available_currencies' => ['USD', 'SEK']]);

    expect(Currency::fromCode('SEK')->toMoneyCurrency())
        ->toBeInstanceOf(MoneyCurrency::class)
        ->getCode()->toBe('SEK');
});

// A code is the whole of what a Money currency is, and an empty one names no currency at all, so USD
// stands in rather than an empty code travelling into the money library — the assumption to know
// about if a provider ever supplies a currency with no code.
it('converts a currency with no code to the fallback', function (): void {
    expect((new Currency('', 'Nothing'))->toMoneyCurrency())
        ->toBeInstanceOf(MoneyCurrency::class)
        ->getCode()->toBe('USD');
});
