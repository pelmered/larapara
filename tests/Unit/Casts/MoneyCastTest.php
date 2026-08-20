<?php

use Illuminate\Database\Eloquent\Model;
use Money\Currency;
use Money\Money;
use Pelmered\LaraPara\Casts\CurrencyCast;
use Pelmered\LaraPara\Casts\MoneyCast;
use Pelmered\LaraPara\Exceptions\InvalidAmount;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;
use Pelmered\LaraPara\Tests\Support\Models\Post;

class TestModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'price'                 => MoneyCast::class,
        'price_currency'        => CurrencyCast::class,
        'price_custom'          => MoneyCast::class,
        'price_custom_currency' => CurrencyCast::class,
        'amount_currency'       => CurrencyCast::class,
    ];

    protected $fillable = ['amount', 'currency'];
}

// Casting only the amount is a supported configuration too — nothing in MoneyCast requires
// CurrencyCast — and it is the only one that can hold a currency code the registry would refuse.
class AmountOnlyModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'price' => MoneyCast::class,
    ];
}

it('casts to Money object', function (): void {
    $cast  = new MoneyCast;
    $model = new TestModel;

    // The actual multiplication depends on configuration
    $value      = '123';
    $attributes = [];
    $key        = 'amount';

    $casted = $cast->get($model, $key, $value, $attributes);

    if (! $casted instanceof Money) {
        $this->fail('MoneyCast->get did not return Money object');
    }

    expect($casted)->toBeInstanceOf(Money::class)
        ->and($casted->getAmount())->toBe('123')
        ->and($casted->getCurrency()->getCode())->toBe('USD'); // Default currency
});

it('casts from Money object', function (): void {
    $cast  = new MoneyCast;
    $model = new TestModel;

    $money      = new Money('12345', new Currency('USD'));
    $attributes = [];
    $key        = 'amount';

    $casted = $cast->set($model, $key, $money, $attributes);

    expect($casted)->toBeArray()
        ->and($casted[$key])->toBe(12345); // Integer amount, not decimal string
});

it('casts null to null', function (): void {
    $cast  = new MoneyCast;
    $model = new TestModel;

    $value      = null;
    $attributes = [];
    $key        = 'amount';

    $casted = $cast->get($model, $key, $value, $attributes);

    expect($casted)->toBeNull();
});

it('casts to Money with the currency set on the model', function (): void {
    $cast = new MoneyCast;

    $model                  = new TestModel;
    $model->amount_currency = 'EUR'; // Explicitly set the currency field

    $value      = '123';
    $attributes = ['amount_currency' => 'EUR']; // Include the currency attribute
    $key        = 'amount';

    $casted = $cast->get($model, $key, $value, $attributes);

    if (! $casted instanceof Money) {
        $this->fail('MoneyCast->get did not return Money object');
    }

    expect($casted)->toBeInstanceOf(Money::class)
        ->and($casted->getAmount())->toBe('123')
        ->and($casted->getCurrency()->getCode())->toBe('EUR');
});

it('casts to Money with default currency when only amount is provided', function (): void {
    // Create a suitable setup for the test model
    config(['larapara.default_currency' => 'USD']);

    $model        = new TestModel;
    $model->price = 12345;

    expect($model->price)->toBeInstanceOf(Money::class)
        ->and($model->price->getAmount())->toBe('12345');
});

it('casts to Money with currency from another field', function (): void {
    // Create custom setup for the currency field
    $model                        = new TestModel;
    $model->price_custom          = 12345;
    $model->price_custom_currency = 'SEK';

    expect($model->price_custom)->toBeInstanceOf(Money::class)
        ->and($model->price_custom->getAmount())->toBe('12345')
        ->and($model->price_custom->getCurrency()->getCode())->toBe('SEK');
});

// An empty currency column has no currency to read, and a Money\Currency built from an empty code
// only fails later, somewhere that cannot say which column it came from.
it('refuses to read an amount whose currency column is empty', function (): void {
    $model                 = new AmountOnlyModel;
    $model->price          = 12345;
    $model->price_currency = '';

    expect(fn (): ?Money => $model->price)->toThrow(UnsupportedCurrency::class);
});

it('sets value from Money object', function (): void {
    $model        = new TestModel;
    $model->price = new Money('54321', new Currency('EUR'));

    $money = $model->getAttribute('price');
    expect($money)->toBeInstanceOf(Money::class);
    expect($money->getAmount())->toEqual('54321');
    expect($money->getCurrency()->getCode())->toEqual('EUR');
});

it('sets value to null', function (): void {
    $model = new TestModel([
        'price'          => 12345,
        'price_currency' => 'USD',
    ]);

    $model->price = null;

    expect($model->getAttribute('price'))->toBeNull();
});

it('handles array input', function (): void {
    config(['larapara.available_currencies' => ['USD', 'EUR', 'SEK', 'JPY']]);

    $model        = new TestModel;
    $model->price = [
        'amount'   => '98765',
        'currency' => 'JPY',
    ];

    $money = $model->getAttribute('price');
    expect($money)->toBeInstanceOf(Money::class);
    expect($money->getAmount())->toEqual('98765');
    expect($money->getCurrency()->getCode())->toEqual('JPY');
});

it('handles zero values create', function (): void {
    $model = new Post([
        'price_currency' => 'USD',
        'price'          => 0,
    ]);

    $money = $model->getAttribute('price');
    expect($money)->toBeInstanceOf(Money::class);
    expect($money->getAmount())->toEqual('0');
    expect($money->getCurrency()->getCode())->toEqual('USD');
});

it('handles zero values', function (): void {
    $model        = new TestModel;
    $model->price = new Money('0', new Currency('USD'));

    $money = $model->getAttribute('price');
    expect($money)->toBeInstanceOf(Money::class);
    expect($money->getAmount())->toEqual('0');
    expect($money->getCurrency()->getCode())->toEqual('USD');
});

it('casts to Money object from decimal', function (): void {
    config(['larapara.store.format' => 'decimal']);

    $cast  = new MoneyCast;
    $model = new TestModel;

    // The actual multiplication depends on configuration
    $value      = '123';
    $attributes = [];
    $key        = 'amount';

    $casted = $cast->get($model, $key, $value, $attributes);

    if (! $casted instanceof Money) {
        $this->fail('MoneyCast->get did not return Money object');
    }

    expect($casted)->toBeInstanceOf(Money::class)
        ->and($casted->getAmount())->toBe('12300')
        ->and($casted->getCurrency()->getCode())->toBe('USD'); // Default currency
});

it('casts from Money object to decimal', function (): void {
    config(['larapara.store.format' => 'decimal']);

    $cast  = new MoneyCast;
    $model = new TestModel;

    $money      = new Money('12345', new Currency('USD'));
    $attributes = [];
    $key        = 'amount';

    $casted = $cast->set($model, $key, $money, $attributes);

    // A string rather than a float: the point is placed rather than divided, so an amount larger
    // than a double holds exactly reaches the column intact.
    expect($casted)->toBeArray()
        ->and($casted[$key])->toBe('123.45');
});

// Decimal storage keeps a fixed number of decimals, and an amount with more of them used to be
// rounded away by the database with nothing said about it.
it('refuses an amount that decimal storage would round', function (): void {
    config([
        'larapara.store.format'           => 'decimal',
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', 'BTC'],
    ]);

    $model = new TestModel;

    expect(function () use ($model): void {
        $model->price = new Money('123456789', new Currency('BTC'));
    })->toThrow(InvalidAmount::class);
});

it('stores an amount the scale can represent', function (string $currency, int $amount, int $scale, string $expected): void {
    config([
        'larapara.store.format'           => 'decimal',
        'larapara.store.decimal_scale'    => $scale,
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', 'BHD', 'BTC'],
    ]);

    $model        = new TestModel;
    $model->price = new Money((string) $amount, new Currency($currency));

    expect($model->getAttributes()['price'])->toBe($expected);
})->with([
    'two minor units'           => ['USD', 123456, 3, '1234.56'],
    'three minor units'         => ['BHD', 1234567, 3, '1234.567'],
    'whole units of a fine one' => ['BTC', 100000000, 3, '1.000'],
    'a scale that fits'         => ['BTC', 123456789, 8, '1.23456789'],
    'negative'                  => ['USD', -123456, 3, '-1234.56'],
    'below one'                 => ['USD', 5, 3, '0.05'],
]);

// The point is placed rather than divided, so an amount a double cannot hold exactly is not deformed
// on its way to the column.
it('stores a large amount exactly', function (): void {
    config(['larapara.store.format' => 'decimal']);

    $model        = new TestModel;
    $model->price = new Money('92233720368547758', new Currency('USD'));

    expect($model->getAttributes()['price'])->toBe('922337203685477.58');
});
