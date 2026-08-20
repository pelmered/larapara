<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Commands;

use Illuminate\Console\Command;
use Pelmered\LaraPara\Currencies\CurrencyRepository;

class ClearCacheCommand extends Command
{
    protected $signature = 'money:clear';

    public function handle(): void
    {
        CurrencyRepository::clearCache();

        $this->info('Currencies cache cleared.');
    }
}
