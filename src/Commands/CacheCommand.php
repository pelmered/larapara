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
        // Cleared first, since the read below writes through the cache only on a miss: an entry the
        // `flexible` type still counts as fresh would be returned as it stands, and the command
        // would report the currencies from before the configuration changed as the ones it cached.
        CurrencyRepository::clearCache();

        $currencies = CurrencyRepository::getAvailableCurrencies();

        if (CurrencyRepository::isCacheEnabled()) {
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
