<?php

declare(strict_types=1);

namespace Pelmered\LaraPara;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Pelmered\LaraPara\Commands\CacheCommand;
use Pelmered\LaraPara\Commands\ClearCacheCommand;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class LaraParaServiceProvider extends PackageServiceProvider
{
    public static string $name = 'larapara';

    /**
     * Length of the currency column.
     *
     * Wider than the three characters of an ISO code, because a currency provider is free to bring
     * longer ones: the bundled crypto list has 1000SATS and AUCTION, which six characters truncate
     * on a permissive database and reject on a strict one.
     */
    public const CURRENCY_CODE_LENGTH = 12;

    /**
     * Digits of a decimal amount column, and of the small variant.
     */
    public const DECIMAL_DIGITS = 12;

    public const SMALL_DECIMAL_DIGITS = 6;

    /**
     * Decimals a decimal amount column keeps unless the configuration says otherwise.
     *
     * Three covers every ISO currency but CLF and UYW, which carry four, and no crypto currency,
     * which carry eight. MoneyCast refuses an amount the configured scale would round away.
     */
    public const DEFAULT_DECIMAL_SCALE = 3;

    public function configurePackage(Package $package): void
    {
        $package->name(static::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasCommands([
                CacheCommand::class,
                ClearCacheCommand::class,
            ]);
    }

    public function boot(): void
    {
        parent::boot();

        $this->optimizes(
            optimize: CacheCommand::class,
            clear: ClearCacheCommand::class,
        );

        $this->registerMacros();
    }

    protected function registerMacros(): void
    {
        Blueprint::macro('money', function (string $name, ?string $indexName = null, ?int $scale = null): ColumnDefinition {
            return LaraParaServiceProvider::moneyColumns($this, $name, $indexName, scale: $scale);
        });

        Blueprint::macro('nullableMoney', function (string $name, ?string $indexName = null, ?int $scale = null): ColumnDefinition {
            return LaraParaServiceProvider::moneyColumns($this, $name, $indexName, nullable: true, scale: $scale);
        });

        Blueprint::macro('smallMoney', function (string $name, ?string $indexName = null, ?int $scale = null): ColumnDefinition {
            return LaraParaServiceProvider::moneyColumns(
                $this,
                $name,
                $indexName,
                integerType: 'smallInteger',
                decimalTotal: LaraParaServiceProvider::SMALL_DECIMAL_DIGITS,
                unsigned: true,
                nullable: true,
                scale: $scale,
            );
        });

        Blueprint::macro('unsignedMoney', function (string $name, ?string $indexName = null, ?int $scale = null): ColumnDefinition {
            return LaraParaServiceProvider::moneyColumns($this, $name, $indexName, unsigned: true, scale: $scale);
        });
    }

    /**
     * Writes the two columns an amount needs, and the index over them.
     *
     * The currency column is never nullable, in any of the macros: an amount with no unit means
     * nothing, and a row whose amount is null still records the unit it would have been in. It
     * carries the configured default currency as its column default, and the casts hold the same
     * rule from the other side, writing that default for a null.
     *
     * Returns the amount column, so `->nullable()`, `->default()` and the rest of the chain land on
     * the column they read as landing on.
     */
    public static function moneyColumns(
        Blueprint $table,
        string $name,
        ?string $indexName = null,
        string $integerType = 'bigInteger',
        int $decimalTotal = self::DECIMAL_DIGITS,
        bool $unsigned = false,
        bool $nullable = false,
        ?int $scale = null,
    ): ColumnDefinition {
        $currencyColumn = $name.config('larapara.currency_column_suffix', '_currency');

        if (config('larapara.store.format') === 'decimal') {
            $decimalScale = $scale ?? static::decimalScale();

            if ($decimalScale > $decimalTotal) {
                throw new \InvalidArgumentException(
                    'The decimal scale '.$decimalScale.' does not fit a decimal('.$decimalTotal.') column.'
                );
            }

            $amount = $table->decimal($name, $decimalTotal, $decimalScale);
        } else {
            $amount = $table->{$integerType}($name);
        }

        if ($unsigned) {
            $amount->unsigned();
        }

        if ($nullable) {
            $amount->nullable();
        }

        $currency = $table->string($currencyColumn, self::CURRENCY_CODE_LENGTH);

        // Defaulted, so that a row which never mentions the amount still satisfies the column: a
        // table with an optional price is inserted into by code that knows nothing about money.
        if ($defaultCurrency = (string) config('larapara.default_currency')) {
            $currency->default($defaultCurrency);
        }

        $table->index([$currencyColumn, $name], $indexName);

        return $amount;
    }

    /**
     * Decimals a decimal amount column keeps, which is what a stored amount is rounded to.
     */
    public static function decimalScale(): int
    {
        return (int) config('larapara.store.decimal_scale', self::DEFAULT_DECIMAL_SCALE);
    }
}
