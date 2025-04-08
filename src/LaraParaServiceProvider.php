<?php

namespace Pelmered\LaraPara;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Pelmered\LaraPara\Commands\CacheCommand;
use Pelmered\LaraPara\Commands\ClearCacheCommand;
use Pelmered\LaraPara\Currencies\CurrencyCollection;

class LaraParaServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->publishes([
            __DIR__.'/../config/larapara.php' => config_path('larapara.php'),
        ], 'larapara');
        $this->mergeConfigFrom(
            __DIR__.'/../config/larapara.php', 'larapara'
        );

        // Requires Laravel 11.27.1
        // See: https://github.com/laravel/framework/pull/52928
        /** @phpstan-ignore function.alreadyNarrowedType  */
        if (method_exists($this, 'optimizes')) {
            $this->optimizes(
                optimize: CacheCommand::class,
                clear: ClearCacheCommand::class,
            );
        }

        $this->commands([
            CacheCommand::class,
            ClearCacheCommand::class,
        ]);

        Blueprint::macro('money', function (string $name, ?string $indexName = null) {
            $currencySuffix = config('larapara.currency_column_suffix');

            $column = config('larapara.store.format') === 'decimal'
                ? $this->decimal($name, 12, 3)
                : $this->bigInteger($name);

            $this->string($name.$currencySuffix, 6);

            $this->index([$name.$currencySuffix, $name], $indexName);

            return $column;
        });

        Blueprint::macro('nullableMoney', function (string $name, ?string $indexName = null) {
            $currencySuffix = config('larapara.currency_column_suffix');

            $column = config('larapara.store.format') === 'decimal'
                ? $this->decimal($name, 12, 3)->nullable()
                : $this->unsignedBigInteger($name)->nullable();

            $this->string($name.$currencySuffix, 6)->nullable();

            $this->index([$name.$currencySuffix, $name], $indexName);

            return $column;
        });

        Blueprint::macro('smallMoney', function (string $name, ?string $indexName = null) {
            $currencySuffix = config('larapara.currency_column_suffix');

            $column = config('larapara.store.format') === 'decimal'
                ? $this->decimal($name, 6, 3)->nullable()
                : $this->unsignedSmallInteger($name)->nullable();
            $this->string($name.$currencySuffix, 6)->nullable();

            $this->index([$name.$currencySuffix, $name], $indexName);

            return $column;
        });

        Blueprint::macro('unsignedMoney', function (string $name, ?string $indexName = null) {
            $currencySuffix = config('larapara.currency_column_suffix');

            $column = config('larapara.store.format') === 'decimal'
                ? $this->decimal($name, 12, 3)->unsigned()
                : $this->unsignedBigInteger($name);
            $this->string($name.$currencySuffix, 6)->nullable();

            $this->index([$name.$currencySuffix, $name], $indexName);

            return $column;
        });
    }

    public function register(): void
    {
        $this->app->bind(CurrencyCollection::class, function (): CurrencyCollection {
            return new CurrencyCollection;
        });

    }
}
