<?php

declare(strict_types=1);

use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Currencies\CurrencyCollection;
use Pelmered\LaraPara\Currencies\CurrencyRepository;

// toSelectArray() is the only thing this collection adds to Illuminate's, so it is the only thing
// worth asserting here — the shapes are the ones CurrencyRepository builds.
it('renders a select array of the currencies it holds', function (): void {
    $collection = new CurrencyCollection([
        'USD' => new Currency('USD', 'US Dollar', 2),
        'EUR' => new Currency('EUR', 'Euro', 2),
        'SEK' => new Currency('SEK', 'Swedish Krona', 2),
    ]);

    expect($collection->toSelectArray())->toBe([
        'USD' => 'USD - US Dollar',
        'EUR' => 'EUR - Euro',
        'SEK' => 'SEK - Swedish Krona',
    ]);
});

it('renders an empty select array for an empty collection', function (): void {
    expect((new CurrencyCollection)->toSelectArray())->toBe([]);
});

// A provider that gives no name for a code leaves it empty, since CurrencyRepository defaults it to ''.
it('renders a currency that has no name', function (): void {
    $collection = new CurrencyCollection([
        'BTC' => new Currency('BTC', '', 8),
    ]);

    expect($collection->toSelectArray())->toBe(['BTC' => 'BTC - ']);
});

// The select array is keyed by code, so a list holding the same code twice renders it once.
it('renders a duplicated code once', function (): void {
    $collection = new CurrencyCollection([
        new Currency('USD', 'US Dollar', 2),
        new Currency('USD', 'United States Dollar', 2),
    ]);

    expect($collection->toSelectArray())->toBe(['USD' => 'USD - United States Dollar']);
});

it('renders the select array of the configured registry', function (): void {
    config(['larapara.available_currencies' => ['USD', 'SEK']]);

    expect(CurrencyRepository::getAvailableCurrencies()->toSelectArray())->toBe([
        'USD' => 'USD - US Dollar',
        'SEK' => 'SEK - Swedish Krona',
    ]);
});
