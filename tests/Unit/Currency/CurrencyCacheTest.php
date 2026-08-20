<?php

declare(strict_types=1);

use Illuminate\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Pelmered\LaraPara\Currencies\CurrencyRepository;

beforeEach(function (): void {
    CurrencyRepository::clearCache();
});

afterEach(function (): void {
    CurrencyRepository::clearCache();
});

// The TTL can be set from the environment, where everything is a string, and each cache type takes a
// different shape of it. The wrong shape used to be read a character at a time (or cast to the int 1),
// so the entry lived for seconds instead of months with nothing to show that it had.
it('keeps a cached registry for the configured time', function (mixed $type, mixed $ttl): void {
    Config::set('larapara.currency_cache.type', $type);
    Config::set('larapara.currency_cache.ttl', $ttl);

    CurrencyRepository::getAvailableCurrencies();

    expect(Cache::has(CurrencyRepository::CACHE_KEY))->toBeTrue();

    $this->travel(30)->seconds();

    expect(Cache::has(CurrencyRepository::CACHE_KEY))->toBeTrue();
})->with([
    'remember, seconds'         => ['remember', 3600],
    'remember, seconds as text' => ['remember', '3600'],
    'remember, the config pair' => ['remember', [2592000, 31556926]],
    'flexible, the config pair' => ['flexible', [2592000, 31556926]],
    'flexible, seconds as text' => ['flexible', '3600'],
    'flexible, seconds'         => ['flexible', 3600],
    'forever'                   => ['forever', null],
]);

it('clears the companion key the flexible type writes', function (): void {
    Config::set('larapara.currency_cache.type', 'flexible');
    Config::set('larapara.currency_cache.ttl', [60, 3600]);

    CurrencyRepository::getAvailableCurrencies();

    $createdKey = Repository::FLEXIBLE_CREATED_KEY_PREFIX.CurrencyRepository::CACHE_KEY;

    expect(Cache::has(CurrencyRepository::CACHE_KEY))->toBeTrue()
        ->and(Cache::has($createdKey))->toBeTrue();

    CurrencyRepository::clearCache();

    expect(Cache::has(CurrencyRepository::CACHE_KEY))->toBeFalse()
        ->and(Cache::has($createdKey))->toBeFalse();
});

it('does not cache when the cache is disabled', function (): void {
    Config::set('larapara.currency_cache.type', false);

    expect(CurrencyRepository::getAvailableCurrencies()->count())->toBe(3)
        ->and(Cache::has(CurrencyRepository::CACHE_KEY))->toBeFalse();
});
