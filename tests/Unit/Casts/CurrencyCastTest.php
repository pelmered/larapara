<?php

namespace Pelmered\LaraPara\Tests\Unit\Casts;

use Money\Currency as MoneyCurrency;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;
use Pelmered\LaraPara\Tests\Support\Models\Post;

beforeEach(function (): void {
    config(['larapara.currency_cast_to' => Currency::class]);
});

it('casts to currency object', function (): void {
    $post = Post::factory()->make([
        'price'          => 23523,
        'price_currency' => 'USD',
    ]);

    expect($post->price_currency)
        ->toBeInstanceOf(Currency::class)
        ->and($post->price_currency->getCode())->toBe('USD');
});

it('casts to money currency when configured', function (): void {
    config(['larapara.currency_cast_to' => MoneyCurrency::class]);

    $model = Post::factory()->make(['price_currency' => 'EUR']);

    expect($model->price_currency)
        ->toBeInstanceOf(MoneyCurrency::class)
        ->and($model->price_currency->getCode())->toBe('EUR');
});

it('handles null values', function (): void {
    $model = Post::factory()->make([
        'price'          => null,
        'price_currency' => null,
    ]);

    expect($model->price_currency)->toBeNull();
});

it('sets currency from currency instance', function (): void {
    $model                 = Post::factory()->make();
    $model->price_currency = Currency::fromCode('SEK');

    expect($model->getAttributes()['price_currency'])->toBe('SEK');
});

it('sets currency from money currency instance', function (): void {
    $model                 = Post::factory()->make();
    $model->price_currency = new MoneyCurrency('EUR');

    expect($model->getAttributes()['price_currency'])->toBe('EUR');
});

it('normalizes the currency code it stores', function (mixed $value): void {
    $model                 = Post::factory()->make();
    $model->price_currency = $value;

    expect($model->getAttributes()['price_currency'])->toBe('SEK');
})->with([
    'lower case' => ['sek'],
    'mixed case' => ['Sek'],
    'padded'     => [' SEK '],
]);

// get() resolves the column through Currency::fromCode(), so a code this configuration does not
// know has to be refused on the way in rather than read back as an exception.
it('refuses a currency that is not available', function (mixed $value): void {
    $model = Post::factory()->make();

    expect(function () use ($model, $value): void {
        $model->price_currency = $value;
    })->toThrow(UnsupportedCurrency::class);
})->with([
    'money currency instance' => [new MoneyCurrency('GBP')],
    'string'                  => ['GBP'],
    'not a currency at all'   => ['nonsense'],
]);
