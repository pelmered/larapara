<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Money\Currency as MoneyCurrency;
use Money\Money;
use Pelmered\LaraPara\Tests\Support\Models\Post;

beforeEach(function (): void {
    config(['larapara.currency_cache.type' => false]);
    config(['larapara.available_currencies' => ['USD', 'EUR', 'SEK', 'JPY', 'BHD']]);
});

/**
 * The table is created per test, since the money macros branch on the store format.
 */
function createPostsTable(): void
{
    Schema::create('posts', function (Blueprint $table): void {
        $table->id();
        $table->unsignedBigInteger('author_id')->nullable();
        $table->string('title')->nullable();
        $table->text('content')->nullable();
        $table->boolean('is_published')->default(false);
        $table->json('tags')->nullable();
        $table->unsignedInteger('rating')->nullable();
        $table->money('price');
        $table->nullableMoney('amount');
        $table->timestamps();
        $table->softDeletes();
    });
}

function storePrice(int $amount, string $currency): Post
{
    createPostsTable();

    $post = Post::create(['price' => new Money($amount, new MoneyCurrency($currency))]);

    return Post::findOrFail($post->id);
}

it('round trips an amount through the database', function (string $format, string $currency, int $amount): void {
    config(['larapara.store.format' => $format]);

    $post = storePrice($amount, $currency);

    expect($post->price->getAmount())->toBe((string) $amount)
        ->and($post->price->getCurrency()->getCode())->toBe($currency);
})->with(function (): array {
    $amounts = [
        'whole'              => 500000,
        'with minor units'   => 1999,
        'one minor unit'     => 1,
        'zero'               => 0,
        'large'              => 999999999,
        'negative'           => -1999,
        'documented example' => 123456,
    ];

    $cases = [];

    foreach (['int', 'decimal'] as $format) {
        foreach ($amounts as $name => $amount) {
            $cases[$format.', USD, '.$name] = [$format, 'USD', $amount];
        }

        // Zero minor units: scaled by two anyway, these were stored a hundred times too small and
        // read back a unit short.
        $cases[$format.', JPY'] = [$format, 'JPY', 29];
        // Three minor units.
        $cases[$format.', BHD'] = [$format, 'BHD', 1234567];
    }

    return $cases;
});

// A decimal column cannot always be scaled back to minor units exactly in binary floating point,
// which silently truncated a minor unit off roughly one amount in twenty.
it('round trips every amount in a range through a decimal column', function (): void {
    config(['larapara.store.format' => 'decimal']);
    createPostsTable();

    $amounts = range(1980, 2020);

    foreach ($amounts as $amount) {
        Post::create(['price' => new Money($amount, new MoneyCurrency('USD'))]);
    }

    expect(Post::orderBy('id')->get()->map(fn (Post $post): int => (int) $post->price->getAmount())->all())
        ->toBe($amounts);
});

it('stores a null amount as null', function (string $format): void {
    config(['larapara.store.format' => $format]);
    createPostsTable();

    $post = Post::create([
        'price'  => new Money(5000, new MoneyCurrency('USD')),
        'amount' => null,
    ]);

    $post = Post::findOrFail($post->id);

    expect($post->getRawOriginal('amount'))->toBeNull()
        ->and($post->amount)->toBeNull();
})->with(['int', 'decimal']);

it('clears an amount that was set before', function (string $format): void {
    config(['larapara.store.format' => $format]);
    createPostsTable();

    $post = Post::create([
        'price'  => new Money(5000, new MoneyCurrency('USD')),
        'amount' => new Money(5000, new MoneyCurrency('USD')),
    ]);

    $post->amount = null;
    $post->save();

    $post = Post::findOrFail($post->id);

    expect($post->getRawOriginal('amount'))->toBeNull()
        ->and($post->amount)->toBeNull();
})->with(['int', 'decimal']);

it('stores the amount in the column shape of the store format', function (string $format, string $currency, int $amount, string $expectedColumn): void {
    config(['larapara.store.format' => $format]);

    expect((string) storePrice($amount, $currency)->getRawOriginal('price'))->toBe($expectedColumn);
})->with([
    'int'                        => ['int', 'USD', 123456, '123456'],
    'decimal'                    => ['decimal', 'USD', 123456, '1234.56'],
    'decimal, no minor units'    => ['decimal', 'JPY', 1234, '1234'],
    'decimal, three minor units' => ['decimal', 'BHD', 1234567, '1234.567'],
]);
