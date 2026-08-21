<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Config;
use Money\Currency as MoneyCurrency;
use Money\Money;
use Pelmered\LaraPara\Casts\MoneyCast;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Currencies\CurrencyRepository;
use Pelmered\LaraPara\Currencies\Providers\CurrenciesProvider;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;
use Pelmered\LaraPara\Tests\Support\Models\Post;

/**
 * A provider that gives an ISO currency a scale of its own, which per-unit pricing needs: USD with
 * four decimals, where ISO 4217 says two.
 */
class FineGrainedDollarProvider implements CurrenciesProvider
{
    public function loadCurrencies(): array
    {
        return [
            'USD' => [
                'alphabeticCode' => 'USD',
                'currency'       => 'US Dollar',
                'minorUnit'      => 4,
                'numericCode'    => 840,
            ],
        ];
    }
}

beforeEach(function (): void {
    CurrencyRepository::clearCache();
    Config::set('larapara.currency_cache.type', false);
    Config::set('larapara.currency_provider', FineGrainedDollarProvider::class);
    Config::set('larapara.available_currencies', ['USD']);
});

// ISO 4217 was consulted before the configured provider, so a provider that gives an ISO currency a
// scale of its own was honoured for the currency's existence and its name but not for its scale:
// amounts were rendered, parsed and stored two decimals wide whatever it said.
it('takes the minor unit of a currency from its provider', function (): void {
    expect(MoneyFormatter::getMinorUnit(Currency::fromCode('USD')))->toBe(4);
});

it('reads the minor unit of a provider through a bare money currency too', function (): void {
    expect(MoneyFormatter::getMinorUnit(new MoneyCurrency('USD')))->toBe(4);
});

it('formats an amount with the decimals its provider gives it', function (): void {
    expect(MoneyFormatter::formatFromMinor(12345678, Currency::fromCode('USD'), 'en_US', showCurrencySymbol: false))
        ->toBe('1,234.5678');
});

it('parses an amount into the decimals its provider gives it', function (): void {
    expect(MoneyFormatter::parseToMinor('1,234.5678', Currency::fromCode('USD'), 'en_US'))
        ->toBe('12345678');
});

// The symbol-ful path places the point through the money library rather than through ICU alone, and
// read its scale from a currency list ISO 4217 sat in front of.
it('formats an amount with its symbol and the decimals of its provider', function (): void {
    expect(MoneyFormatter::formatFromMinor(12345678, Currency::fromCode('USD'), 'en_US'))
        ->toBe('$1,234.5678');
});

// The cast reads its scale through the same resolver, so the column holds the decimals the provider
// declares rather than the two ISO would have written.
it('stores an amount with the decimals its provider gives it', function (): void {
    Config::set('larapara.store.format', 'decimal');
    Config::set('larapara.store.decimal_scale', 4);

    $model = new Post;
    $money = new Money('12345678', new MoneyCurrency('USD'));

    expect((new MoneyCast)->set($model, 'price', $money, [])['price'])->toBe('1234.5678');
});

// Nothing names a minor unit for a currency built by hand, so ISO still answers for it.
it('falls back to ISO for a currency that carries no minor unit', function (string $code, int $expected): void {
    expect(MoneyFormatter::getMinorUnit(new Currency($code, '')))->toBe($expected);
})->with([
    'two decimals' => ['EUR', 2],
    'no decimals'  => ['JPY', 0],
    'three'        => ['BHD', 3],
    'outside ISO'  => ['XYZ', 2],
]);
