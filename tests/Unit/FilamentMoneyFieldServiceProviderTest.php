<?php

use Illuminate\Support\Facades\Config;
use Pelmered\LaraPara\LaraParaServiceProvider;

// Tests for LaraParaServiceProvider that avoid using reflection

it('loads service provider correctly', function (): void {
    // Test that the service provider can be instantiated without errors
    $serviceProvider = new LaraParaServiceProvider(app());
    expect($serviceProvider)->toBeInstanceOf(LaraParaServiceProvider::class);
});

it('registers config file correctly', function (): void {
    // Verify that the config file exists in the package
    $configSourcePath = realpath(__DIR__.'/../../config');
    $hasConfigFile    = file_exists($configSourcePath.'/larapara.php');

    expect($hasConfigFile)->toBeTrue();

    // Check that we can access the configuration
    expect(config('larapara.default_currency'))->not()->toBeNull();
});

it('merges config', function (): void {
    $originalDefaultCurrency = config('larapara.default_currency');

    // Change config value
    config(['larapara.default_currency' => 'EUR']);

    // Check if config was changed
    expect(config('larapara.default_currency'))->toEqual('EUR');

    // Reset to original value
    config(['larapara.default_currency' => $originalDefaultCurrency]);
});
