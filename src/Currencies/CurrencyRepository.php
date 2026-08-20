<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Currencies;

use Illuminate\Cache\Repository;
use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Pelmered\LaraPara\Currencies\Providers\CryptoCurrenciesProvider;
use Pelmered\LaraPara\Currencies\Providers\ISOCurrenciesProvider;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;
use PhpStaticAnalysis\Attributes\Returns;
use PhpStaticAnalysis\Attributes\Throws;

class CurrencyRepository
{
    public const CACHE_KEY = 'larapara_currencies';

    public static function isValid(Currency $currency): bool
    {
        // By code, since Collection::contains() compares whole objects: a currency built from just a
        // code would not equal the one in the registry, which carries its name and minor unit too.
        return static::isValidCode($currency->getCode());
    }

    public static function isValidCode(string $currencyCode): bool
    {
        try {
            Currency::fromCode($currencyCode);

            return true;
        } catch (UnsupportedCurrency) {
            return false;
        }
    }

    public static function getAvailableCurrencies(): CurrencyCollection
    {
        $config = Config::get('larapara.currency_cache', []);
        $ttl    = data_get($config, 'ttl', 0);

        $callback = function (): CurrencyCollection {
            return static::loadAvailableCurrencies();
        };

        // Read defensively and coerced per type: the TTL can be set from the environment, where
        // everything is a string, and each type takes a different shape of it. Passing the wrong
        // shape does not fail loudly — a string TTL is read one character at a time and an array one
        // becomes the int 1, so the cache quietly lives for seconds instead of months.
        return match (data_get($config, 'type')) {
            'remember' => Cache::remember(static::CACHE_KEY, static::secondsTtl($ttl), $callback),
            'flexible' => Cache::flexible(static::CACHE_KEY, static::flexibleTtl($ttl), $callback),
            'forever'  => Cache::rememberForever(static::CACHE_KEY, $callback),
            default    => $callback(),
        };
    }

    public static function clearCache(): void
    {
        Cache::forget(static::CACHE_KEY);

        // The flexible type keeps the age of the entry under a companion key of its own.
        Cache::forget(Repository::FLEXIBLE_CREATED_KEY_PREFIX.static::CACHE_KEY);
    }

    /**
     * The TTL as a number of seconds, which is what the `remember` type takes.
     */
    protected static function secondsTtl(mixed $ttl): int
    {
        return (int) (is_array($ttl) ? reset($ttl) : $ttl);
    }

    /**
     * The TTL as the [fresh, stored] pair `flexible` takes, from either shape of the config value.
     */
    #[Returns('array{0: int, 1: int}')]
    protected static function flexibleTtl(mixed $ttl): array
    {
        if (is_array($ttl)) {
            return [(int) ($ttl[0] ?? 0), (int) ($ttl[1] ?? $ttl[0] ?? 0)];
        }

        return [(int) $ttl, (int) $ttl];
    }

    #[Throws(BindingResolutionException::class)]
    #[Throws(UnsupportedCurrency::class)]
    protected static function loadAvailableCurrencies(): CurrencyCollection
    {
        $currencyProvider    = Config::get('larapara.currency_provider', ISOCurrenciesProvider::class);
        $availableCurrencies = Config::get('larapara.available_currencies', []);

        $currencies = app()->make($currencyProvider)->loadCurrencies();

        if (Config::get('larapara.load_crypto_currencies', false)) {
            $cryptoCurrencies = app()->make(CryptoCurrenciesProvider::class)->loadCurrencies();

            $currencies = array_merge(
                $currencies,
                $cryptoCurrencies
            );
        }

        if (! $availableCurrencies) {
            $availableCurrencies = array_keys($currencies);

            // Filter out excluded currencies
            $availableCurrencies = array_diff(
                $availableCurrencies,
                Config::get('larapara.excluded_currencies', [])
            );
        }

        if (is_string($availableCurrencies)) {
            $availableCurrencies = explode(',', $availableCurrencies);
        }

        // Codes come from configuration and from the provider, so neither side can be trusted to be
        // normalized. Both are keyed the same way here so the lookup below cannot silently miss.
        $currencies = array_change_key_case($currencies, CASE_UPPER);

        return new CurrencyCollection(
            Arr::mapWithKeys($availableCurrencies,
                static function (string $currencyCode) use ($currencies): array {
                    $currencyCode = strtoupper(trim($currencyCode));

                    if (! array_key_exists($currencyCode, $currencies)) {
                        throw new UnsupportedCurrency($currencyCode);
                    }

                    return [
                        $currencyCode => new Currency(
                            $currencyCode,
                            $currencies[$currencyCode]['currency'] ?? '',
                            $currencies[$currencyCode]['minorUnit'],
                        ),
                    ];
                }
            )
        );
    }
}
