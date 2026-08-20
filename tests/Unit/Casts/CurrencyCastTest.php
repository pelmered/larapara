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
    $model                 = new Post;
    $model->price_currency = null;

    expect($model->getAttributes()['price_currency'])->toBeNull()
        ->and($model->price_currency)->toBeNull();
});

// MoneyCast::set() writes the currency column alongside the amount, so which of the two is assigned
// last decides what the column holds. Pinned here rather than left to the order a factory defines.
it('writes the default currency with a null amount assigned after a null currency', function (): void {
    $model                 = new Post;
    $model->price_currency = null;
    $model->price          = null;

    expect($model->getAttributes())->toMatchArray([
        'price'          => null,
        'price_currency' => 'USD',
    ]);
});

it('keeps a null currency when the amount is assigned first', function (): void {
    $model                 = new Post;
    $model->price          = null;
    $model->price_currency = null;

    expect($model->getAttributes())->toMatchArray([
        'price'          => null,
        'price_currency' => null,
    ]);
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

// The set tests above assert the raw column, so nothing reached get() with a code this configuration
// does not know — the state a row written before the code was removed from available_currencies is in.
it('refuses a currency the configuration does not know when hydrating', function (): void {
    $model = (new Post)->newFromBuilder(['price_currency' => 'GBP']);

    expect(fn (): mixed => $model->price_currency)->toThrow(UnsupportedCurrency::class);
});

it('casts a currency it knows when hydrating', function (): void {
    $model = (new Post)->newFromBuilder(['price_currency' => 'SEK']);

    expect($model->price_currency)
        ->toBeInstanceOf(Currency::class)
        ->getCode()->toBe('SEK');
});
