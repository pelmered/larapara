<?php

namespace Pelmered\LaraPara\Currencies;

use Illuminate\Contracts\Container\BindingResolutionException;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Pelmered\LaraPara\Currencies\Providers\CryptoCurrenciesProvider;
use Pelmered\LaraPara\Currencies\Providers\ISOCurrenciesProvider;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;
use PhpStaticAnalysis\Attributes\Throws;

class CurrencyRepository
{
    public static function isValid(Currency $currency): bool
    {
        return static::getAvailableCurrencies()->contains($currency);
    }

    public static function isValidCode(string $currencyCode): bool
    {
        try {
            return static::isValid(Currency::fromCode($currencyCode));
        } catch (UnsupportedCurrency) {
            return false;
        }
    }

    public static function getAvailableCurrencies(): CurrencyCollection
    {
        $config = Config::get('larapara.currency_cache', [
            'type' => false,
            'ttl'  => 0,
        ]);

        $callback = function (): \Pelmered\LaraPara\Currencies\CurrencyCollection {
            return static::loadAvailableCurrencies();
        };

        return match ($config['type']) {
            'remember' => Cache::remember('filament_money_currencies', $config['ttl'], $callback),
            'flexible' => Cache::flexible('filament_money_currencies', $config['ttl'], $callback),
            'forever'  => Cache::forever('filament_money_currencies', $callback),
            default    => $callback(),
        };
    }

    public static function clearCache(): void
    {
        Cache::forget('filament_money_currencies');
    }

    #[Throws(BindingResolutionException::class)]
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

        return new CurrencyCollection(
            Arr::mapWithKeys($availableCurrencies,
                static function (string $currencyCode) use ($currencies) {
                    return [
                        $currencyCode => new Currency(
                            strtoupper($currencyCode),
                            $currencies[$currencyCode]['currency'] ?? '',
                            $currencies[$currencyCode]['minorUnit'],
                        ),
                    ];
                }
            )
        );
    }
}
