<?php

namespace Pelmered\LaraPara\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Pelmered\LaraPara\Currencies\Currency;
use PhpStaticAnalysis\Attributes\Param;
use Spatie\LaravelData\Casts\Cast;

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
     * Prepare the given value for storage.
     */
    #[Param(value: 'Currency|\Money\Currency|string|null')]
    #[Param(attributes: 'array<string, mixed>')]
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof \Money\Currency) {
            return $value->getCode();
        }

        if ($value instanceof Currency) {
            return $value->getCode();
        }

        return (string) $value;
    }
}
