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
     * @param  int|null  $scale  Decimals the column keeps, where they are not the configured ones:
     *                           `MoneyCast::class.':8'` beside `$table->money('price', scale: 8)`.
     *                           The scale an amount is refused for carrying more of, so a column
     *                           given its own scale has to say so here as well — otherwise the
     *                           amounts this cast accepts are not the ones the column holds.
     */
    public function __construct(private readonly ?int $scale = null) {}

    /**
     * Cast the given value.
     */
    #[Param(value: 'int|float|string|null')]
    #[Param(attributes: 'array<string, mixed>')]
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if ($value === null) {
            return null;
        }

        $currency = $this->getCurrencyFromModel($model, $key);

        $amount = config('larapara.store.format') === 'decimal'
            ? $this->fromDecimal((string) $value, $currency->getCode())
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
        $amount   = $this->getAmount($model, $key, $value);
        $currency = $this->getCurrency($model, $key, $value);

        $stored = match (true) {
            // Before the format branch, since dividing null by the scale factor would store a zero.
            $amount                         === null      => null,
            config('larapara.store.format') === 'decimal' => $this->toDecimal($amount, $currency),
            default                                       => $amount,
        };

        return [
            $key                                             => $stored,
            LaraParaServiceProvider::currencyColumnFor($key) => $currency,
        ];
    }

    #[Param(value: 'array{0?: int, 1?: string, amount?: int, currency?: string}|Money|int|string|null')]
    #[Returns('int|null')]
    #[Throws(InvalidAmount::class)]
    protected function getAmount(Model $model, string $key, Money|array|int|string|null $value): ?int
    {
        $amount = match (true) {
            $value instanceof Money => $value->getAmount(),
            is_array($value)        => $value['amount'] ?? $value[0] ?? null,
            default                 => $value,
        };

        if ($amount === null) {
            return null;
        }

        // By the formatter's rule rather than by a second one here, since both are given the same
        // amounts: (int) read "1234.56" as 1234 and stored $12.34 for the amount whoever wrote it
        // meant, which is the value the formatter refuses outright.
        return (int) MoneyFormatter::toMinorUnits($amount);
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
        $currency = $model->{LaraParaServiceProvider::currencyColumnFor($name)} ?? config('larapara.default_currency');

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
     * exactly is not deformed on its way to the column, and refused outright when the scale in
     * effect would round a digit away instead of letting the database drop it silently.
     */
    #[Throws(InvalidAmount::class)]
    protected function toDecimal(int $amount, string $currency): string
    {
        $minorUnit = $this->getDecimals($currency);
        $scale     = LaraParaServiceProvider::decimalScale($this->scale);

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

    /**
     * The minor units a decimal column holds: "1234.56" in USD is 123456.
     *
     * The inverse of toDecimal(), and exact the same way: the point is moved rather than the value
     * multiplied, so an amount larger than a double holds exactly reads back as it was written. The
     * column keeps `store.decimal_scale` decimals, which is at least the minor unit of most
     * currencies, so the zeros it pads a shorter amount with are dropped again here.
     */
    #[Returns('numeric-string')]
    #[Throws(InvalidAmount::class)]
    protected function fromDecimal(string $value, string $currency): string
    {
        $minorUnit = $this->getDecimals($currency);

        [$whole, $fraction] = array_pad(explode('.', trim($value), 2), 2, '');

        $sign     = str_starts_with($whole, '-') ? '-' : '';
        $whole    = ltrim($whole, '+-');
        $fraction = rtrim($fraction, '0');

        if (strlen($fraction) <= $minorUnit) {
            $digits = ltrim($whole.str_pad($fraction, $minorUnit, '0'), '0');
            $amount = $digits === '' ? '0' : $sign.$digits;

            // Digits, and not merely numeric: exponent notation is numeric and pads to a digit
            // string that is not — "1E+25" becomes "1E+2500" — which Money refuses a character at a
            // time rather than reading. The reading below is the one written for that notation.
            if (($digits === '' || ctype_digit($digits)) && is_numeric($amount)) {
                return $amount;
            }
        }

        // Not a plain decimal carrying the decimals of its currency: a float in exponent notation
        // from a driver that hands one back, or a row written by hand with more decimals than the
        // currency has. Read as the number it is, which rounds rather than reads the amount short.
        $minorAmount = round((float) $value * 10 ** $minorUnit);

        // Cast beyond the integer range, the amount wraps to an unrelated — usually negative — one,
        // so a column holding more than this cast can read says so instead of reading it wrongly.
        if (! is_finite($minorAmount) || abs($minorAmount) >= (float) PHP_INT_MAX) {
            throw InvalidAmount::exceedsIntegerRange($value, $currency);
        }

        return (string) (int) $minorAmount;
    }

    public function getDecimals(string $currencyCode): int
    {
        // The formatter's resolver, so the scale an amount is stored at is the scale it is
        // formatted and parsed at — two lookups here would let the two drift apart.
        return MoneyFormatter::getMinorUnit(Currency::fromCode($currencyCode));
    }
}
