<?php

use Illuminate\Database\Eloquent\Model;
use Money\Currency;
use Money\Money;
use Pelmered\LaraPara\Casts\CurrencyCast;
use Pelmered\LaraPara\Casts\MoneyCast;
use Pelmered\LaraPara\Exceptions\InvalidAmount;
use Pelmered\LaraPara\Exceptions\InvalidColumnScale;
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

// A column given a scale of its own by the macro — `$table->money('price', scale: 8)` — needs the
// cast told the same, since the cast is the side that refuses an amount the column cannot hold.
class FineScaleModel extends Model
{
    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'price'          => MoneyCast::class.':8',
        'price_currency' => CurrencyCast::class,
    ];
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

// And reads it back the same way, by moving the point rather than by multiplying: a double carries
// 2**53 exactly and nothing above it, so the read used to hand back a different amount than was
// written for anything larger.
it('round trips a decimal column exactly', function (string $currency, string $amount, string $expectedColumn): void {
    config([
        'larapara.store.format'           => 'decimal',
        'larapara.store.decimal_scale'    => 8,
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', 'JPY', 'BHD', 'BTC'],
    ]);

    $cast                  = new MoneyCast;
    $model                 = new TestModel;
    $model->price_currency = $currency;

    $stored = $cast->set($model, 'price', new Money($amount, new Currency($currency)), [])['price'];

    expect($stored)->toBe($expectedColumn)
        ->and($cast->get($model, 'price', $stored, [])->getAmount())->toBe($amount);
})->with([
    'past what a double holds' => ['USD', '10000000000000001', '100000000000000.01'],
    'the largest int'          => ['USD', '92233720368547758', '922337203685477.58'],
    'an ordinary amount'       => ['USD', '123456', '1234.56'],
    'below one'                => ['USD', '5', '0.05'],
    'negative'                 => ['USD', '-123456', '-1234.56'],
    'zero'                     => ['USD', '0', '0.00'],
    'no minor units'           => ['JPY', '1234', '1234'],
    'three minor units'        => ['BHD', '1234567', '1234.567'],
    'eight minor units'        => ['BTC', '2100000000000000', '21000000.00000000'],
]);

// A column keeps its own scale, so it hands back an amount padded past the minor unit of its
// currency — and a row written by hand can carry more decimals than the currency has at all.
it('reads a decimal column whatever scale it kept', function (string $currency, string $column, string $expectedAmount): void {
    config([
        'larapara.store.format'         => 'decimal',
        'larapara.available_currencies' => ['USD', 'JPY'],
    ]);

    $model                 = new TestModel;
    $model->price_currency = $currency;

    expect((new MoneyCast)->get($model, 'price', $column, [])->getAmount())->toBe($expectedAmount);
})->with([
    'padded to the column scale'   => ['USD', '1234.560', '123456'],
    'padded with nothing to spare' => ['USD', '1234.500', '123450'],
    'no fractional part at all'    => ['USD', '1234', '123400'],
    'zero padded'                  => ['USD', '0.000', '0'],
    'no minor units, padded'       => ['JPY', '1234.000', '1234'],
    'more decimals than the unit'  => ['USD', '1234.567', '123457'],
]);

// The scale used to come from the configuration alone, so a column the macro gave eight decimals to
// still refused a satoshi while the configured scale was three, and a column given fewer decimals
// than the configuration accepted amounts the database would round away.
it('refuses an amount by the scale the cast was given', function (): void {
    config([
        'larapara.store.format'           => 'decimal',
        'larapara.store.decimal_scale'    => 3,
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', 'BTC'],
    ]);

    $model = new TestModel;
    $money = new Money('123456789', new Currency('BTC'));

    expect((new MoneyCast(8))->set($model, 'price', $money, [])['price'])->toBe('1.23456789')
        ->and(fn (): array => (new MoneyCast)->set($model, 'price', $money, []))
        ->toThrow(InvalidAmount::class);
});

it('refuses an amount the narrower column of the cast cannot hold', function (): void {
    config([
        'larapara.store.format'         => 'decimal',
        'larapara.store.decimal_scale'  => 8,
        'larapara.available_currencies' => ['USD', 'BHD'],
    ]);

    $model = new TestModel;
    $cast  = new MoneyCast(2);

    expect($cast->set($model, 'price', new Money('1234560', new Currency('BHD')), [])['price'])->toBe('1234.56')
        ->and(fn (): array => $cast->set($model, 'price', new Money('1234567', new Currency('BHD')), []))
        ->toThrow(InvalidAmount::class);
});

// Eloquent hands a cast its parameters as strings, so the scale arrives as '8' rather than as 8.
it('takes the scale as a cast parameter', function (): void {
    config([
        'larapara.store.format'           => 'decimal',
        'larapara.store.decimal_scale'    => 3,
        'larapara.load_crypto_currencies' => true,
        'larapara.available_currencies'   => ['USD', 'BTC'],
    ]);

    $model        = new FineScaleModel;
    $model->price = new Money('123456789', new Currency('BTC'));

    expect($model->getAttributes()['price'])->toBe('1.23456789');
});

// A driver is free to hand back a decimal column as a float in exponent notation, and the reading
// written for that notation was unreachable: the guard above it asked is_numeric(), which exponent
// notation satisfies, so "1E+25" was padded to the digit string "1E+2500" and handed to Money —
// which refused it a character at a time rather than reading the amount.
it('reads a column handed back in exponent notation', function (string $column, string $expectedAmount): void {
    config([
        'larapara.store.format'         => 'decimal',
        'larapara.available_currencies' => ['USD'],
    ]);

    $model                 = new TestModel;
    $model->price_currency = 'USD';

    expect((new MoneyCast)->get($model, 'price', $column, [])->getAmount())->toBe($expectedAmount);
})->with([
    'a whole number of units'     => ['1E+3', '100000'],
    'lower case, with a fraction' => ['1.5e3', '150000'],
    'a negative exponent'         => ['1.2E-1', '12'],
    'negative'                    => ['-1E+3', '-100000'],
]);

// Read through a float, an amount past the integer range wrapped to an unrelated negative one, so a
// column holding more than the cast can read handed back a plausible-looking wrong amount.
it('refuses a column holding more minor units than an integer', function (): void {
    config([
        'larapara.store.format'         => 'decimal',
        'larapara.available_currencies' => ['USD'],
    ]);

    $model                 = new TestModel;
    $model->price_currency = 'USD';

    expect(fn (): ?Money => (new MoneyCast)->get($model, 'price', '1.0E+25', []))
        ->toThrow(InvalidAmount::class);
});

// An amount is whole minor units, and a decimal string was cast to an int instead: '1234.56' was
// read as 1234 and stored as $12.34, the amount nobody meant, while the formatter refuses the same
// string outright. The two are given the same amounts, so they answer the same way.
it('refuses an amount that is not whole minor units', function (mixed $value): void {
    $model = new TestModel;

    expect(fn (): array => (new MoneyCast)->set($model, 'price', $value, []))
        ->toThrow(InvalidAmount::class);
})->with([
    'a decimal string'        => ['1234.56'],
    'a decimal string, minor' => ['0.5'],
    'exponent notation'       => ['1.2E+3'],
    'not a number at all'     => ['twelve'],
    'in the array form'       => [['amount' => '1234.56', 'currency' => 'USD']],
]);

it('takes an amount in minor units however it is written', function (mixed $value, int $expected): void {
    $model = new TestModel;

    expect((new MoneyCast)->set($model, 'price', $value, [])['price'])->toBe($expected);
})->with([
    'an int'            => [123456, 123456],
    'a string'          => ['123456', 123456],
    'a padded string'   => [' 123456 ', 123456],
    'a negative string' => ['-123456', -123456],
    'zero'              => ['0', 0],
]);

// The cast moved the point the wrong way for a negative scale instead of refusing it, and only for
// the amounts it could: $1,230.00 was stored as 1.23, a thousandth of the amount, while $1,234.56
// threw. A scale is a count of decimals, so neither side of the column takes a negative one.
it('refuses a negative scale', function (): void {
    config([
        'larapara.store.format'         => 'decimal',
        'larapara.available_currencies' => ['USD'],
    ]);

    $model = new TestModel;
    $money = new Money('123000', new Currency('USD'));

    expect(fn (): array => (new MoneyCast(-1))->set($model, 'price', $money, []))
        ->toThrow(InvalidColumnScale::class);

    config(['larapara.store.decimal_scale' => -1]);

    expect(fn (): array => (new MoneyCast)->set($model, 'price', $money, []))
        ->toThrow(InvalidColumnScale::class);
});

// A Money holds its amount as a string and the money library counts in arbitrary precision, so an
// amount can be larger than the integer a column stores. Cast to one, it clamped to the largest
// integer there is: 99999999999999999999 was stored as 9223372036854775807 in an integer column and
// as 92233720368547758.07 in a decimal one, both silently, and both a different amount.
it('refuses an amount larger than the integer a column stores', function (string $format): void {
    config(['larapara.store.format' => $format]);

    $model = new TestModel;
    $money = new Money('99999999999999999999', new Currency('USD'));

    expect(fn (): array => (new MoneyCast)->set($model, 'price', $money, []))
        ->toThrow(InvalidAmount::class, '99999999999999999999');
})->with(['int', 'decimal']);

it('refuses an amount past the integer range whichever way it is written', function (mixed $value): void {
    expect(fn (): array => (new MoneyCast)->set(new TestModel, 'price', $value, []))
        ->toThrow(InvalidAmount::class);
})->with([
    'one past the largest'  => ['9223372036854775808'],
    'one past the smallest' => ['-9223372036854775809'],
    'far past it'           => ['99999999999999999999'],
    'in the array form'     => [['amount' => '99999999999999999999', 'currency' => 'USD']],
]);

it('stores the largest and smallest amounts an integer holds', function (string $amount): void {
    expect((new MoneyCast)->set(new TestModel, 'price', new Money($amount, new Currency('USD')), [])['price'])
        ->toBe((int) $amount);
})->with([
    'the largest'  => [(string) PHP_INT_MAX],
    'the smallest' => [(string) PHP_INT_MIN],
]);
