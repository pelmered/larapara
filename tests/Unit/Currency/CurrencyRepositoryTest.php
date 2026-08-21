<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Mockery as M;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Currencies\CurrencyCollection;
use Pelmered\LaraPara\Currencies\CurrencyRepository;
use Pelmered\LaraPara\Currencies\Providers\CurrenciesProvider;
use Pelmered\LaraPara\Currencies\Providers\ISOCurrenciesProvider;
use Pelmered\LaraPara\Exceptions\InvalidConfiguration;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;
use Pelmered\LaraPara\Rules\SupportedCurrency;

class LowerCasedCurrenciesProvider implements CurrenciesProvider
{
    public function loadCurrencies(): array
    {
        return [
            'usd' => [
                'alphabeticCode' => 'usd',
                'currency'       => 'US Dollar',
                'minorUnit'      => 2,
                'numericCode'    => 840,
            ],
        ];
    }
}

beforeEach(function (): void {
    CurrencyRepository::clearCache();
    Cache::shouldReceive('remember')->andReturnUsing(fn ($key, $ttl, $callback) => $callback());
    Cache::shouldReceive('flexible')->andReturnUsing(fn ($key, $ttl, $callback) => $callback());
    Cache::shouldReceive('rememberForever')->andReturnUsing(fn ($key, $callback) => $callback());
});

it('checks if a currency is valid', function (): void {
    // Configure available currencies
    Config::set('larapara.available_currencies', ['USD']);

    // Create test currencies
    $validCurrency   = new Currency('USD', 'US Dollar', 2);
    $invalidCurrency = new Currency('EUR', 'Euro', 2);

    // Test with a valid currency
    expect(CurrencyRepository::isValid($validCurrency))->toBeTrue();

    // Test with an invalid currency
    expect(CurrencyRepository::isValid($invalidCurrency))->toBeFalse();
});

// Validity is a property of the code: the registry's own currency carries a name and a minor unit
// that a currency built from just a code does not, and comparing whole objects made it invalid.
it('checks a currency that carries nothing but its code', function (): void {
    Config::set('larapara.available_currencies', ['USD']);

    expect(CurrencyRepository::isValid(new Currency('USD', '')))->toBeTrue()
        ->and(CurrencyRepository::isValid(new Currency('EUR', '')))->toBeFalse();
});

it('checks if a currency code is valid', function (): void {
    Config::set('larapara.available_currencies', ['USD']);
    expect(CurrencyRepository::isValidCode('USD'))->toBeTrue();
});

it('checks if a currency code is invalid', function (): void {
    Config::set('larapara.available_currencies', ['USD']);
    expect(CurrencyRepository::isValidCode('EUR'))->toBeFalse();
});

it('loads available currencies without caching', function (): void {
    // Configure to not use cache
    Config::set('larapara.currency_cache.type', false);
    Config::set('larapara.currency_provider', ISOCurrenciesProvider::class);
    Config::set('larapara.available_currencies', ['USD', 'EUR']);

    // Mock the ISOCurrenciesProvider
    $mockProvider = M::mock(ISOCurrenciesProvider::class);
    $mockProvider->shouldReceive('loadCurrencies')
        ->andReturn([
            'USD' => ['alphabeticCode' => 'USD', 'currency' => 'US Dollar', 'minorUnit' => 2, 'numericCode' => 840],
            'EUR' => ['alphabeticCode' => 'EUR', 'currency' => 'Euro', 'minorUnit' => 2, 'numericCode' => 978],
        ]);
    app()->instance(ISOCurrenciesProvider::class, $mockProvider);

    $currencies = CurrencyRepository::getAvailableCurrencies();

    expect($currencies)->toBeInstanceOf(CurrencyCollection::class)
        ->and($currencies->count())->toBe(2)
        ->and($currencies->has('USD'))->toBeTrue()
        ->and($currencies->has('EUR'))->toBeTrue()
        ->and($currencies->get('USD')->name)->toBe('US Dollar')
        ->and($currencies->get('EUR')->name)->toBe('Euro');
});

it('caches currencies with remember', function (): void {
    // Configure to use remember cache
    Config::set('larapara.currency_cache.type', 'remember');
    Config::set('larapara.currency_cache.ttl', 60);
    Config::set('larapara.currency_provider', ISOCurrenciesProvider::class);
    Config::set('larapara.available_currencies', ['USD', 'EUR']);

    // Mock the ISOCurrenciesProvider
    $mockProvider = M::mock(ISOCurrenciesProvider::class);
    $mockProvider->shouldReceive('loadCurrencies')
        ->andReturn([
            'USD' => ['alphabeticCode' => 'USD', 'currency' => 'US Dollar', 'minorUnit' => 2, 'numericCode' => 840],
            'EUR' => ['alphabeticCode' => 'EUR', 'currency' => 'Euro', 'minorUnit' => 2, 'numericCode' => 978],
        ]);
    app()->instance(ISOCurrenciesProvider::class, $mockProvider);

    $currencies = CurrencyRepository::getAvailableCurrencies();

    expect($currencies)->toBeInstanceOf(CurrencyCollection::class)
        ->and($currencies->count())->toBe(2);
});

it('caches currencies with flexible', function (): void {
    // Configure to use flexible cache
    Config::set('larapara.currency_cache.type', 'flexible');
    Config::set('larapara.currency_cache.ttl', [60, 3600]);
    Config::set('larapara.currency_provider', ISOCurrenciesProvider::class);
    Config::set('larapara.available_currencies', ['USD', 'EUR']);

    // Mock the ISOCurrenciesProvider
    $mockProvider = M::mock(ISOCurrenciesProvider::class);
    $mockProvider->shouldReceive('loadCurrencies')
        ->andReturn([
            'USD' => ['alphabeticCode' => 'USD', 'currency' => 'US Dollar', 'minorUnit' => 2, 'numericCode' => 840],
            'EUR' => ['alphabeticCode' => 'EUR', 'currency' => 'Euro', 'minorUnit' => 2, 'numericCode' => 978],
        ]);
    app()->instance(ISOCurrenciesProvider::class, $mockProvider);

    $currencies = CurrencyRepository::getAvailableCurrencies();

    expect($currencies)->toBeInstanceOf(CurrencyCollection::class)
        ->and($currencies->count())->toBe(2);
});

it('caches currencies forever', function (): void {
    // Configure to use forever cache
    Config::set('larapara.currency_cache.type', 'forever');
    Config::set('larapara.currency_provider', ISOCurrenciesProvider::class);
    Config::set('larapara.available_currencies', ['USD', 'EUR']);

    // Mock the ISOCurrenciesProvider
    $mockProvider = M::mock(ISOCurrenciesProvider::class);
    $mockProvider->shouldReceive('loadCurrencies')
        ->andReturn([
            'USD' => ['alphabeticCode' => 'USD', 'currency' => 'US Dollar', 'minorUnit' => 2, 'numericCode' => 840],
            'EUR' => ['alphabeticCode' => 'EUR', 'currency' => 'Euro', 'minorUnit' => 2, 'numericCode' => 978],
        ]);
    app()->instance(ISOCurrenciesProvider::class, $mockProvider);

    $currencies = CurrencyRepository::getAvailableCurrencies();

    expect($currencies)->toBeInstanceOf(CurrencyCollection::class)
        ->and($currencies->count())->toBe(2);
});

it('loads all available currencies when none specified', function (): void {
    Config::set('larapara.currency_cache.type', false);
    Config::set('larapara.currency_provider', ISOCurrenciesProvider::class);
    Config::set('larapara.available_currencies', []);

    // Mock the ISOCurrenciesProvider with more currencies
    $mockProvider = M::mock(ISOCurrenciesProvider::class);
    $mockProvider->shouldReceive('loadCurrencies')
        ->andReturn([
            'USD' => ['alphabeticCode' => 'USD', 'currency' => 'US Dollar', 'minorUnit' => 2, 'numericCode' => 840],
            'EUR' => ['alphabeticCode' => 'EUR', 'currency' => 'Euro', 'minorUnit' => 2, 'numericCode' => 978],
            'GBP' => ['alphabeticCode' => 'GBP', 'currency' => 'British Pound', 'minorUnit' => 2, 'numericCode' => 826],
        ]);
    app()->instance(ISOCurrenciesProvider::class, $mockProvider);

    $currencies = CurrencyRepository::getAvailableCurrencies();

    expect($currencies)->toBeInstanceOf(CurrencyCollection::class)
        ->and($currencies->count())->toBe(3)
        ->and($currencies->has('USD'))->toBeTrue()
        ->and($currencies->has('EUR'))->toBeTrue()
        ->and($currencies->has('GBP'))->toBeTrue();
});

// The bundled crypto data is shaped differently from the ISO data, which left every crypto currency
// with an empty name where it is displayed.
it('names the crypto currencies it loads', function (): void {
    Config::set('larapara.currency_cache.type', false);
    Config::set('larapara.load_crypto_currencies', true);
    Config::set('larapara.available_currencies', ['USD', 'BTC']);

    expect(Currency::fromCode('BTC'))
        ->name->toBe('BTC')
        ->minorUnit->toBe(8)
        ->and(CurrencyRepository::getAvailableCurrencies()->toSelectArray())
        ->toBe(['USD' => 'USD - US Dollar', 'BTC' => 'BTC - BTC']);
});

it('loads crypto currencies when enabled', function (): void {
    Config::set('larapara.currency_cache.type', false);
    Config::set('larapara.currency_provider', ISOCurrenciesProvider::class);
    Config::set('larapara.available_currencies', []);
    Config::set('larapara.load_crypto_currencies', true);

    // Using regular provider to test actual functionality
    $currencies = CurrencyRepository::getAvailableCurrencies();

    expect($currencies)->toBeInstanceOf(CurrencyCollection::class)
        ->and($currencies->has('USD'))->toBeTrue()
        ->and($currencies->has('EUR'))->toBeTrue();
});

// A configured code is used as an array key against the provider's list, so an unnormalized one used
// to take every request down with "Undefined array key" on the first currency read.
it('normalizes the configured currency codes', function (mixed $availableCurrencies): void {
    Config::set('larapara.available_currencies', $availableCurrencies);

    expect(CurrencyRepository::getAvailableCurrencies()->keys()->all())->toBe(['USD', 'EUR'])
        ->and(Currency::fromCode('EUR')->minorUnit)->toBe(2);
})->with([
    'as configured'               => [['USD', 'EUR']],
    'lower case'                  => [['usd', 'eur']],
    'padded'                      => [[' USD ', 'EUR ']],
    'comma separated'             => ['USD,EUR'],
    'comma separated with spaces' => ['USD, EUR'],
]);

it('refuses a configured currency the provider does not know', function (mixed $availableCurrencies): void {
    Config::set('larapara.available_currencies', $availableCurrencies);

    expect(fn (): CurrencyCollection => CurrencyRepository::getAvailableCurrencies())
        ->toThrow(InvalidConfiguration::class);
})->with([
    'unknown code'          => [['USD', 'XXY']],
    'crypto without crypto' => [['USD', 'BTC']],
]);

// A misconfigured entry used to raise UnsupportedCurrency, which is what "this code is not one of
// the configured currencies" means and what every caller asking that question catches to answer no:
// one typo in available_currencies reported every currency in the registry as invalid, USD included,
// with nothing anywhere naming the entry that was wrong.
it('does not report every currency as invalid for one misconfigured entry', function (): void {
    Config::set('larapara.available_currencies', ['USD', 'EUR', 'XYZ']);

    expect(fn (): bool => CurrencyRepository::isValidCode('USD'))
        ->toThrow(InvalidConfiguration::class, 'XYZ');
});

it('names the misconfigured entry when a rule validates a currency', function (): void {
    Config::set('larapara.available_currencies', ['USD', 'EUR', 'XYZ']);

    expect(function (): void {
        (new SupportedCurrency)->validate('currency', 'USD', function (): void {});
    })->toThrow(InvalidConfiguration::class, 'XYZ');
});

it('takes the currency codes of a provider that keys them differently', function (): void {
    Config::set('larapara.currency_provider', LowerCasedCurrenciesProvider::class);
    Config::set('larapara.available_currencies', ['USD']);

    expect(Currency::fromCode('USD'))
        ->getCode()->toBe('USD')
        ->minorUnit->toBe(2);
});

// The exclusion was diffed against the provider's own keys and the codes were upper-cased after it,
// so a provider that keys its currencies in lower case kept every currency the configuration
// excluded: the code survived the diff, was upper-cased two lines later, and appeared in the
// collection as if nothing had asked for it to be gone.
it('excludes a currency from a provider that keys them differently', function (): void {
    Config::set('larapara.currency_provider', LowerCasedCurrenciesProvider::class);
    Config::set('larapara.available_currencies', []);
    Config::set('larapara.excluded_currencies', ['USD']);

    expect(CurrencyRepository::getAvailableCurrencies())->toHaveCount(0);
});

it('excludes a currency written the way the configuration happens to spell it', function (string $excluded): void {
    Config::set('larapara.available_currencies', []);
    Config::set('larapara.excluded_currencies', [$excluded]);

    expect(CurrencyRepository::isValidCode('USD'))->toBeFalse()
        ->and(CurrencyRepository::isValidCode('EUR'))->toBeTrue();
})->with([
    'the code itself' => ['USD'],
    'lower case'      => ['usd'],
    'padded'          => [' USD '],
]);
