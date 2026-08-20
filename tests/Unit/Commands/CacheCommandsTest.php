<?php

use Illuminate\Support\Facades\Cache;
use Pelmered\LaraPara\Currencies\CurrencyCollection;
use Pelmered\LaraPara\Currencies\CurrencyRepository;

test('cache command runs successfully', function (): void {

    config(['larapara.currency_cache.type' => 'remember']);
    config(['larapara.currency_cache.ttl' => '500']);

    CurrencyRepository::clearCache();

    expect(Cache::has('larapara_currencies'))->toBeFalse();

    test()->artisan('money:cache')
        ->assertExitCode(0);

    expect(Cache::has('larapara_currencies'))->toBeTrue();

    $currencies  = Cache::get('larapara_currencies');
    $currencies2 = CurrencyRepository::getAvailableCurrencies();

    expect($currencies->count())->toBe($currencies2->count());
    expect($currencies)->toBeInstanceOf(CurrencyCollection::class);
});

test('clear cache command runs successfully', function (): void {

    config(['larapara.currency_cache.type' => 'remember']);
    config(['larapara.currency_cache.ttl' => '500']);

    $currencies = CurrencyRepository::getAvailableCurrencies();

    expect(Cache::has('larapara_currencies'))->toBeTrue();

    test()->artisan('money:clear')
        ->expectsOutput('Currencies cache cleared.')
        ->assertExitCode(0);

    expect(Cache::has('larapara_currencies'))->toBeFalse();
});

// The command reported a cache write that the disabled cache never made.
test('cache command reports that a disabled cache was not written', function (): void {
    config(['larapara.currency_cache.type' => false]);

    CurrencyRepository::clearCache();

    test()->artisan('money:cache')
        ->expectsOutputToContain('The currency cache is disabled')
        ->assertExitCode(0);

    expect(Cache::has(CurrencyRepository::CACHE_KEY))->toBeFalse();
});

test('cache command in verbose mode shows currency table', function (): void {
    config(['larapara.currency_cache.type' => 'remember']);
    config(['larapara.currency_cache.ttl' => '500']);

    CurrencyRepository::clearCache();

    $currencies = CurrencyRepository::getAvailableCurrencies();

    $tableData = $currencies->map(fn ($currency): array => [
        $currency->name,
        $currency->code,
        $currency->minorUnit,
    ])->toArray();

    test()->artisan('money:cache --verbose')
        ->expectsTable(
            ['Name', 'Code', 'Minor Unit Decimals'],
            $tableData
        )
        ->assertExitCode(0);
});
