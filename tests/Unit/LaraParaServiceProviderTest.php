<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\ServiceProvider;
use Pelmered\LaraPara\Commands\CacheCommand;
use Pelmered\LaraPara\Commands\ClearCacheCommand;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\LaraParaServiceProvider;

it('registers the package commands', function (): void {
    expect(Artisan::all())
        ->toHaveKeys(['money:cache', 'money:clear'])
        ->and(Artisan::all()['money:cache'])->toBeInstanceOf(CacheCommand::class)
        ->and(Artisan::all()['money:clear'])->toBeInstanceOf(ClearCacheCommand::class);
});

// The commands are wired into `optimize` and `optimize:clear`, which is what makes a deployment
// rebuild the currency cache without knowing this package's commands by name.
it('wires the commands into the optimization commands', function (): void {
    expect(ServiceProvider::$optimizeCommands)->toContain(CacheCommand::class)
        ->and(ServiceProvider::$optimizeClearCommands)->toContain(ClearCacheCommand::class);
});

it('publishes its config file', function (): void {
    $paths = ServiceProvider::pathsToPublish(LaraParaServiceProvider::class, 'larapara-config');

    expect($paths)->toHaveCount(1)
        ->and(array_key_first($paths))->toEndWith('config/larapara.php');
});

// The validation rules address their messages as larapara::validation.*, which only resolves while
// the lang directory is registered.
it('publishes its translations', function (): void {
    $paths = ServiceProvider::pathsToPublish(LaraParaServiceProvider::class, 'larapara-translations');

    expect($paths)->not->toBeEmpty()
        ->and(array_key_first($paths))->toEndWith('resources/lang');
});

it('merges its config with the application config', function (): void {
    expect(config('larapara.currency_column_suffix'))->toBe('_currency')
        ->and(config('larapara.store.format'))->toBe('int')
        ->and(config('larapara.currency_cast_to'))->toBe(Currency::class);
});
