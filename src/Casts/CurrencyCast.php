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

        // Resolved through the registry whichever object the configuration asks for, since
        // `currency_cast_to` chooses the type a read hands back and not whether the code is one this
        // configuration knows. Built straight from the column, a code available_currencies does not
        // list read cleanly and then threw out of set(), which Eloquent calls to merge a cast
        // attribute back into the model — a write validator failing on what a read had handed out.
        $currency = Currency::fromCode($value);

        return match (config('larapara.currency_cast_to')) {
            \Money\Currency::class => $currency->toMoneyCurrency(),
            default                => $currency,
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
        if ($value === null) {
            return null;
        }

        // The code of the object get() built, read rather than resolved a second time: get() hands
        // back a \Money\Currency unvalidated where the configuration asks for one, so resolving it
        // here would throw for a stored code this configuration no longer lists — a row that reads
        // cleanly would fail on toArray(), and every serialized attribute would cost a lookup.
        if ($value instanceof Currency || $value instanceof \Money\Currency) {
            return (string) $value;
        }

        return Currency::toCode($value);
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
