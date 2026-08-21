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

// The command only read the currencies, and a read writes through the cache on a miss alone: a
// `flexible` entry that was still fresh came back as it stood, so `php artisan optimize` after a
// configuration change reported the currencies from before it as the ones it had just cached.
test('cache command replaces an entry that is still fresh', function (): void {
    config([
        'larapara.currency_cache.type'  => 'flexible',
        'larapara.currency_cache.ttl'   => [2592000, 31556926],
        'larapara.available_currencies' => ['USD', 'EUR'],
    ]);

    CurrencyRepository::clearCache();

    expect(CurrencyRepository::getAvailableCurrencies())->toHaveCount(2);

    config(['larapara.available_currencies' => ['USD', 'EUR', 'SEK']]);

    test()->artisan('money:cache')
        ->expectsOutputToContain('3 Currencies cached.')
        ->assertExitCode(0);

    expect(Cache::get(CurrencyRepository::CACHE_KEY))->toHaveCount(3);
});
