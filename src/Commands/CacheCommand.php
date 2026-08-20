<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Commands;

use Illuminate\Console\Command;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Currencies\CurrencyRepository;

class CacheCommand extends Command
{
    protected $signature = 'money:cache';

    public function handle(): void
    {
        $currencies = CurrencyRepository::getAvailableCurrencies();

        if (config('larapara.currency_cache.type')) {
            $this->info($currencies->count().' Currencies cached.');
        } else {
            $this->warn('The currency cache is disabled, so nothing was cached. Set larapara.currency_cache.type to enable it.');
        }

        if ($this->option('verbose')) {
            $this->table(
                ['Name', 'Code', 'Minor Unit Decimals'],
                $currencies->map(fn (Currency $currency): array => [$currency->name, $currency->code, $currency->minorUnit])
            );
        }
    }
}
