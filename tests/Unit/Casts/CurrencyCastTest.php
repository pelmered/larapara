<?php

namespace Pelmered\LaraPara\Tests\Unit\Casts;

use Money\Currency as MoneyCurrency;
use Pelmered\LaraPara\Casts\CurrencyCast;
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

// The currency column is not nullable, so a null code is the configured default: a row with no
// amount still records the unit it would have been in.
it('writes the default currency for a null', function (): void {
    $model                 = new Post;
    $model->price_currency = null;

    expect($model->getAttributes()['price_currency'])->toBe('USD')
        ->and($model->price_currency)->toBeInstanceOf(Currency::class)
        ->and($model->price_currency->getCode())->toBe('USD');
});

// MoneyCast::set() writes the currency column alongside the amount, so the two used to disagree
// about a null depending on which was assigned last.
it('writes the default currency whichever of the pair is assigned last', function (): void {
    $currencyFirst                 = new Post;
    $currencyFirst->price_currency = null;
    $currencyFirst->price          = null;

    $amountFirst                 = new Post;
    $amountFirst->price          = null;
    $amountFirst->price_currency = null;

    expect($currencyFirst->getAttributes())->toMatchArray(['price' => null, 'price_currency' => 'USD'])
        ->and($amountFirst->getAttributes())->toMatchArray(['price' => null, 'price_currency' => 'USD']);
});

// A row written before the column was made non-nullable still reads back.
it('reads a null column as null', function (): void {
    $model = (new Post)->newFromBuilder(['price' => null, 'price_currency' => null]);

    expect($model->price_currency)->toBeNull()
        ->and($model->price)->toBeNull();
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

// A model with these casts serializes its currency as a value, the way the Money beside it does,
// rather than as a dump of the registry's record for that code.
it('serializes a currency as its code', function (): void {
    $model = (new Post)->newFromBuilder(['price' => 123456, 'price_currency' => 'SEK']);

    expect($model->toArray()['price_currency'])->toBe('SEK')
        ->and(json_decode($model->toJson(), true)['price_currency'])->toBe('SEK')
        ->and(json_encode($model->price_currency))->toBe('"SEK"');
});

// get() hands back a \Money\Currency unvalidated where the configuration asks for one, so
// serialize() reads the code of the object it is given rather than resolving it a second time: it
// threw for a code the configuration no longer lists, failing to serialize a value it had just
// handed out, and paid a registry lookup per serialized attribute per row to do it.
it('serializes a code the configuration no longer lists', function (): void {
    config(['larapara.currency_cast_to' => MoneyCurrency::class]);

    $cast     = new CurrencyCast;
    $model    = new Post;
    $currency = $cast->get($model, 'price_currency', 'GBP', []);

    expect($currency)->toBeInstanceOf(MoneyCurrency::class)
        ->and($cast->serialize($model, 'price_currency', $currency, []))->toBe('GBP');
});

// The code carried by the object get() built, whatever the configuration casts to.
it('serializes the currency the configuration casts to', function (string $castTo, string $code): void {
    config(['larapara.currency_cast_to' => $castTo]);

    $model = (new Post)->newFromBuilder(['price' => 123456, 'price_currency' => $code]);

    expect($model->toArray()['price_currency'])->toBe($code);
})->with([
    'currency'       => [Currency::class, 'SEK'],
    'money currency' => [MoneyCurrency::class, 'SEK'],
]);
