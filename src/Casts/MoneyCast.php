<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Money\Currency as MoneyCurrency;
use Money\Money;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Exceptions\InvalidAmount;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;
use Pelmered\LaraPara\LaraParaServiceProvider;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;
use PhpStaticAnalysis\Attributes\Throws;

/**
 * @implements CastsAttributes<Money, Money>
 */
class MoneyCast implements CastsAttributes
{
    /**
     * Cast the given value.
     */
    #[Param(value: '?int')]
    #[Param(attributes: 'array<string, mixed>')]
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currency = $this->getCurrencyFromModel($model, $key);

        $amount = config('larapara.store.format') === 'decimal'
            // Rounded, because scaling the stored decimal back is not exact in binary floating
            // point: 19.99 * 100 is 1998.9999999999998, which truncates to a cent too little.
            ? (int) round((float) $value * 10 ** $this->getDecimals($currency->getCode()))
            : (int) $value;

        return new Money($amount, $currency);
    }

    /**
     * Prepare the given value for storage.
     */
    #[Param(value: 'Money|string')]
    #[Param(attributes: 'array<string, mixed>')]
    #[Returns('array<string, int|float|string|null>')]
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        $amount      = $this->getAmount($model, $key, $value);
        $currency    = $this->getCurrency($model, $key, $value);
        $currencyKey = $key.config('larapara.currency_column_suffix', '_currency');

        // Before the format branch, since dividing null by the scale factor would store a zero.
        if ($amount === null) {
            return [
                $key         => null,
                $currencyKey => $currency,
            ];
        }

        return [
            $key => config('larapara.store.format') === 'decimal'
                ? $this->toDecimal($amount, $currency)
                : $amount,
            $currencyKey => $currency,
        ];
    }

    #[Param(value: 'array{0?: int, 1?: string, amount?: int, currency?: string}|Money|int|string|null')]
    #[Returns('int|null')]
    protected function getAmount(Model $model, string $key, Money|array|int|string|null $value): ?int
    {
        $amount = match (true) {
            $value instanceof Money => $value->getAmount(),
            is_array($value)        => $value['amount'] ?? $value[0] ?? null,
            default                 => $value,
        };

        return $amount !== null ? (int) $amount : null;
    }

    #[Param(value: 'array{0?: int, 1?: string, amount?: int, currency?: string}|Money|int|string|null')]
    protected function getCurrency(Model $model, string $key, Money|array|int|string|null $value): string
    {
        $currency = match (true) {
            $value instanceof Money => $value->getCurrency(),
            is_array($value)        => $value['currency'] ?? $value[1] ?? null,
            default                 => $this->getCurrencyFromModel($model, $key),
        } ?? MoneyFormatter::getDefaultCurrency();

        // Validated on the way in, since the read path resolves the column through
        // Currency::fromCode() and would throw for a code this configuration does not know.
        return Currency::toCode($currency);
    }

    protected function getCurrencyFromModel(Model $model, string $name): MoneyCurrency
    {
        $currency = $model->{$name.config('larapara.currency_column_suffix', '_currency')} ?? config('larapara.default_currency');

        if ($currency instanceof MoneyCurrency) {
            return $currency;
        }

        // Cast explicitly: where the currency column is cast with CurrencyCast, as the README
        // prescribes, the attribute is a Currency object, and it used to reach Money\Currency's string
        // parameter only because this file did not declare strict types.
        $code = trim((string) $currency);

        return new MoneyCurrency($code !== '' ? $code : throw new UnsupportedCurrency($code));
    }

    /**
     * The amount as the decimal string a decimal column stores.
     *
     * Built by placing the point rather than by dividing, so an amount larger than a double holds
     * exactly is not deformed on its way to the column, and refused outright when the configured
     * scale would round a digit away instead of letting the database drop it silently.
     */
    #[Throws(InvalidAmount::class)]
    protected function toDecimal(int $amount, string $currency): string
    {
        $minorUnit = $this->getDecimals($currency);
        $scale     = LaraParaServiceProvider::decimalScale();

        if ($minorUnit > $scale) {
            $unrepresentable = 10 ** ($minorUnit - $scale);

            if ($amount % $unrepresentable !== 0) {
                throw InvalidAmount::exceedsStoredScale((string) $amount, $currency, $minorUnit, $scale);
            }

            // Only zeros beyond the scale, so the amount is written with the decimals the column
            // keeps rather than with more for the database to drop.
            $amount    = intdiv($amount, $unrepresentable);
            $minorUnit = $scale;
        }

        $sign   = $amount < 0 ? '-' : '';
        $digits = str_pad((string) abs($amount), $minorUnit + 1, '0', STR_PAD_LEFT);

        return $minorUnit === 0
            ? $sign.$digits
            : $sign.substr($digits, 0, -$minorUnit).'.'.substr($digits, -$minorUnit);
    }

    public function getDecimals(string $currencyCode): int
    {
        return Currency::fromCode($currencyCode)->minorUnit ?? 2;
    }
}
