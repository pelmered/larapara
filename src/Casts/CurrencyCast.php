<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;
use PhpStaticAnalysis\Attributes\Param;

/**
 * @implements CastsAttributes<Currency, Currency>
 */
class CurrencyCast implements CastsAttributes
{
    /**
     * Cast the given value.
     */
    #[Param(value: '?non-empty-string')]
    #[Param(attributes: 'array<string, mixed>')]
    public function get(Model $model, string $key, mixed $value, array $attributes): Currency|\Money\Currency|null
    {
        if ($value === null) {
            return null;
        }

        return match (config('larapara.currency_cast_to')) {
            \Money\Currency::class => new \Money\Currency($value),
            default                => Currency::fromCode($value)
        };
    }

    /**
     * The value as it appears in the model's array form.
     *
     * Laravel only asks a cast for this when the method exists, and without it toArray() carries the
     * Currency object itself, so an array and the JSON built from it disagreed about the shape.
     */
    #[Param(value: 'Currency|\Money\Currency|string|null')]
    #[Param(attributes: 'array<string, mixed>')]
    public function serialize(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        return $value === null ? null : Currency::toCode($value);
    }

    /**
     * Prepare the given value for storage.
     */
    #[Param(value: 'Currency|\Money\Currency|string|null')]
    #[Param(attributes: 'array<string, mixed>')]
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        // The currency column is not nullable — an amount with no unit means nothing, and a row with
        // no amount still records the unit it would have been in — so a null is the default currency.
        if ($value === null) {
            return MoneyFormatter::getDefaultCurrency()->getCode();
        }

        // Validated and normalized on the way in, since get() resolves the column through
        // Currency::fromCode() and would throw for a code this configuration does not know.
        return Currency::toCode($value);
    }
}
