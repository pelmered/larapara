<?php

use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Currencies\CurrencyCollection;

it('creates a collection with currencies', function (): void {
    $currencies = [
        new Currency('USD', 'US Dollar'),
        new Currency('EUR', 'Euro'),
        new Currency('GBP', 'British Pound'),
    ];

    $collection = new CurrencyCollection($currencies);

    expect($collection)->toHaveCount(3);
});

it('filters currencies by a callback', function (): void {
    $currencies = [
        new Currency('USD', 'US Dollar'),
        new Currency('EUR', 'Euro'),
        new Currency('GBP', 'British Pound'),
    ];

    $collection = new CurrencyCollection($currencies);

    $filtered = $collection->filter(function (Currency $currency): bool {
        return $currency->getCode() === 'USD';
    });

    expect($filtered)->toHaveCount(1)
        ->and($filtered->first()->getCode())->toBe('USD');
});

it('maps currencies to values', function (): void {
    $currencies = [
        new Currency('USD', 'US Dollar'),
        new Currency('EUR', 'Euro'),
    ];

    $collection = new CurrencyCollection($currencies);

    $mapped = $collection->map(function (Currency $currency): string {
        return $currency->getCode();
    });

    expect($mapped->toArray())->toBe(['USD', 'EUR']);
});

it('plucks values from currencies', function (): void {
    $currencies = [
        new Currency('USD', 'US Dollar'),
        new Currency('EUR', 'Euro'),
    ];

    $collection = new CurrencyCollection($currencies);

    $plucked = $collection->pluck('code');

    expect($plucked->toArray())->toBe(['USD', 'EUR']);
});

it('finds a currency by code', function (): void {
    $currencies = [
        new Currency('USD', 'US Dollar'),
        new Currency('EUR', 'Euro'),
    ];

    $collection = new CurrencyCollection($currencies);

    $usd = $collection->firstWhere('code', 'USD');

    expect($usd)->toBeInstanceOf(Currency::class)
        ->and($usd->getCode())->toBe('USD');

    $notFound = $collection->firstWhere('code', 'GBP');

    expect($notFound)->toBeNull();
});

it('can be converted to select array', function (): void {
    $currencies = [
        new Currency('USD', 'US Dollar'),
        new Currency('EUR', 'Euro'),
    ];

    $collection = new CurrencyCollection($currencies);

    $array = $collection->toSelectArray();

    expect($array)->toBeArray()
        ->and($array)->toHaveCount(2)
        ->and($array['USD'])->toBe('USD - US Dollar')
        ->and($array['EUR'])->toBe('EUR - Euro');
});

it('sorts currencies by a key', function (): void {
    $currencies = [
        new Currency('GBP', 'British Pound'),
        new Currency('USD', 'US Dollar'),
        new Currency('EUR', 'Euro'),
    ];

    $collection = new CurrencyCollection($currencies);

    $sorted = $collection->sortBy('code');

    expect($sorted->pluck('code')->toArray())->toBe(['EUR', 'GBP', 'USD']);
});
