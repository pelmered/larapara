<?php

use Pelmered\LaraPara\Currencies\Providers\ISOCurrenciesProvider;

return [

    /*
    |---------------------------------------------------------------------------
    | Store format
    |---------------------------------------------------------------------------
    |
    | The format to store the value in the database.
    |
    */
    'store' => [
        'format' => 'int', // Allowed values: 'int' or 'decimal'
    ],

    /*
    |---------------------------------------------------------------------------
    | Default currency
    |---------------------------------------------------------------------------
    |
    | The currency ISO code to use if not set on the field.
    | For example: USD, EUR, SEK, etc.
    |
    */
    'default_currency' => env('MONEY_DEFAULT_CURRENCY', 'USD'),

    /*
    |---------------------------------------------------------------------------
    | International currency symbol
    |---------------------------------------------------------------------------
    |
    | Use international currency symbols. For example: USD, EUR, SEK instead of $, €, kr etc.
    |
    */
    'intl_currency_symbol' => env('MONEY_INTL_CURRENCY_SYMBOL', false),

    /*
    |---------------------------------------------------------------------------
    | Currency list
    |---------------------------------------------------------------------------
    |
    | Provide your own custom currency list provider.
    | It must implement the Money\Currencies interface
    |
    */
    'currency_provider' => ISOCurrenciesProvider::class,

    /*
    |---------------------------------------------------------------------------
    | Available Currencies list
    |---------------------------------------------------------------------------
    |
    | Provide a list of available currencies for selection.
    | It should be a list of ISO 4217 currency codes.
    | For example: ['USD', 'EUR']
    | If you want to include all currencies, leave this as an empty array.
    | If you include all with an empty array, you may exclude currencies with 'excluded_currencies'.
    | TIP: In your .env file, you can set MONEY_AVAILABLE_CURRENCIES as a comma-separated string like this:
    | MONEY_AVAILABLE_CURRENCIES="USD,EUR,SEK"
    |
    */
    'available_currencies' => env('MONEY_AVAILABLE_CURRENCIES', []),
    'excluded_currencies'  => [],

    /*
    |---------------------------------------------------------------------------
    | Currency column suffix
    |---------------------------------------------------------------------------
    |
    | Provide a suffix for the currency column.
    | For example: if the money amount is stored as 'amount', the currency column
    | would be 'amount_currency' with the default suffix.
    |
    */
    'currency_column_suffix' => env('MONEY_CURRENCY_COLUMN_SUFFIX', '_currency'),

    /*
    |---------------------------------------------------------------------------
    | Caching
    |---------------------------------------------------------------------------
    |
    | Set to false to disable the currency cache.
    |
    */
    'currency_cache' => [
        'type' => env('MONEY_CURRENCY_CACHE', 'flexible'), // 'remember', 'flexible', 'forever', false
        'ttl'  => env('MONEY_CURRENCY_CACHE_TTL', [2592000, 31556926]), // 1 month, 1 year
    ],

    /*
    |---------------------------------------------------------------------------
    | Load crypto currencies
    |---------------------------------------------------------------------------
    |
    | Set to true to enable support for crypto currencies.
    |
    */
    'load_crypto_currencies' => env('MONEY_LOAD_CRYPTO_CURRENCIES', false),

    /*
    |---------------------------------------------------------------------------
    | Currency cast
    |---------------------------------------------------------------------------
    |
    | What currency object should Pelmered\LaraPara\Casts\CurrencyCast should cast to.
    | Supported values are:
    | - 'Pelmered\LaraPara\Currencies\Currency::class' (default and recommended)
    | - 'Money\Currency::class'
    */
    'currency_cast_to' => env('MONEY_CURRENCY_CAST', Pelmered\LaraPara\Currencies\Currency::class),
];
