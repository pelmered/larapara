<?php

declare(strict_types=1);

namespace Pelmered\LaraPara;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\ColumnDefinition;
use Pelmered\LaraPara\Commands\CacheCommand;
use Pelmered\LaraPara\Commands\ClearCacheCommand;
use Pelmered\LaraPara\Exceptions\InvalidColumnScale;
use PhpStaticAnalysis\Attributes\Throws;
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
    #[Throws(InvalidColumnScale::class)]
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
        $currencyColumn = static::currencyColumnFor($name);

        if (config('larapara.store.format') === 'decimal') {
            $scale = static::decimalScale($scale);

            // A decimal column cannot keep more decimals than it holds digits at all: MySQL and
            // PostgreSQL both refuse the column, while SQLite takes it and every amount written to
            // it — so a project whose tests run on SQLite would hear this from its own database, at
            // deploy time. The narrow macro is the one that runs out of digits: eight decimals, the
            // scale a crypto amount needs, leave nothing of smallMoney()'s six.
            if ($scale >= $decimalTotal) {
                throw InvalidColumnScale::exceedsColumnDigits($name, $scale, $decimalTotal);
            }

            $amount = $table->decimal($name, $decimalTotal, $scale);
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
     * The name of the currency column beside an amount column, which the macros write and the
     * casts read, so the two sides cannot disagree about it.
     */
    public static function currencyColumnFor(string $name): string
    {
        return $name.config('larapara.currency_column_suffix', '_currency');
    }

    /**
     * Decimals a decimal amount column keeps, which is what a stored amount is written with.
     *
     * Takes the scale a caller names — a macro argument or a cast parameter — and answers with the
     * configured one otherwise, so the one gate below sees every scale either side works from. A
     * scale is a count of decimals: a negative one is not a wider column but a nonsense one, and it
     * moved the point the wrong way rather than being refused.
     */
    #[Throws(InvalidColumnScale::class)]
    public static function decimalScale(?int $scale = null): int
    {
        $scale ??= (int) config('larapara.store.decimal_scale', self::DEFAULT_DECIMAL_SCALE);

        return $scale >= 0 ? $scale : throw InvalidColumnScale::negative($scale);
    }
}
