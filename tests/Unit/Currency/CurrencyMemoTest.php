<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Currencies\CurrencyRepository;
use Pelmered\LaraPara\Currencies\Providers\CurrenciesProvider;

/**
 * Counts how often the list is actually built, which is what the memo is there to reduce.
 */
class CountingCurrenciesProvider implements CurrenciesProvider
{
    public static int $calls = 0;

    public function loadCurrencies(): array
    {
        self::$calls++;

        return [
            'USD' => ['alphabeticCode' => 'USD', 'currency' => 'US Dollar', 'minorUnit' => 2, 'numericCode' => 840],
            'EUR' => ['alphabeticCode' => 'EUR', 'currency' => 'Euro', 'minorUnit' => 2, 'numericCode' => 978],
        ];
    }
}

beforeEach(function (): void {
    CurrencyRepository::clearCache();
    CountingCurrenciesProvider::$calls = 0;

    Config::set('larapara.currency_cache.type', false);
    Config::set('larapara.currency_provider', CountingCurrenciesProvider::class);
    Config::set('larapara.available_currencies', ['USD', 'EUR']);
});

// The read path resolves the scale of every amount through the registry, so rendering a page of rows
// asked for the currencies once or twice per row: with the cache off that rebuilt the ISO list — and
// the crypto one, where it is enabled — every time, and with it on it was a round trip to the cache
// store per row.
it('builds the currency list once for a configuration', function (): void {
    Currency::fromCode('USD');
    Currency::fromCode('EUR');
    CurrencyRepository::getAvailableCurrencies();

    expect(CountingCurrenciesProvider::$calls)->toBe(1);
});

it('builds it again once the configuration it was built from changes', function (string $key, mixed $value): void {
    CurrencyRepository::getAvailableCurrencies();

    Config::set($key, $value);

    CurrencyRepository::getAvailableCurrencies();

    expect(CountingCurrenciesProvider::$calls)->toBe(2);
})->with([
    'available currencies' => ['larapara.available_currencies', ['USD']],
    'excluded currencies'  => ['larapara.excluded_currencies', ['EUR']],
    'crypto currencies'    => ['larapara.load_crypto_currencies', true],
]);

it('builds it again once the provider itself changes', function (): void {
    expect(CurrencyRepository::getAvailableCurrencies()->pluck('name')->all())
        ->toBe(['US Dollar', 'Euro']);

    Config::set('larapara.currency_provider', RenamingCurrenciesProvider::class);

    expect(CurrencyRepository::getAvailableCurrencies()->pluck('name')->all())
        ->toBe(['Dollar of the United States', 'Euro']);
});

it('builds it again after the cache is cleared', function (): void {
    CurrencyRepository::getAvailableCurrencies();

    CurrencyRepository::clearCache();

    CurrencyRepository::getAvailableCurrencies();

    expect(CountingCurrenciesProvider::$calls)->toBe(2);
});

// The list a changed configuration asks for is the list it gets, rather than the one memoized for
// the configuration before it.
it('hands back the currencies of the configuration in force', function (): void {
    expect(CurrencyRepository::getAvailableCurrencies()->pluck('code')->all())->toBe(['USD', 'EUR']);

    Config::set('larapara.available_currencies', ['USD']);

    expect(CurrencyRepository::getAvailableCurrencies()->pluck('code')->all())->toBe(['USD']);
});

/**
 * A second provider, naming a currency differently so a switch to it is visible in the collection.
 */
class RenamingCurrenciesProvider implements CurrenciesProvider
{
    public function loadCurrencies(): array
    {
        return [
            'USD' => ['alphabeticCode' => 'USD', 'currency' => 'Dollar of the United States', 'minorUnit' => 2, 'numericCode' => 840],
            'EUR' => ['alphabeticCode' => 'EUR', 'currency' => 'Euro', 'minorUnit' => 2, 'numericCode' => 978],
        ];
    }
}
