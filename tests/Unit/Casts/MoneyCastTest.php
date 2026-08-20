<?php

use Illuminate\Database\Eloquent\Model;
use Money\Currency;
use Money\Money;
use Pelmered\LaraPara\Casts\CurrencyCast;
use Pelmered\LaraPara\Casts\MoneyCast;
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

    expect($casted)->toBeArray()
        ->and($casted[$key])->toBe(123.45);
});
