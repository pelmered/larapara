<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Money\Currency as MoneyCurrency;
use Money\Exception\ParserException;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;
use Pelmered\LaraPara\MoneyFormatter\MoneyFormatter;

/**
 * Validates that a localized amount string is one MoneyFormatter::parseToMinor() can read.
 *
 * The locale defaults to the application's, the currency to the configured default, and whether a
 * separator out of place is forgiven to `larapara.parse.strict`. Whether the currency itself is
 * supported is SupportedCurrency's business, so an unsupported one here does not fail the amount —
 * the scale of a currency does not decide whether a number is one.
 */
class MoneyString implements ValidationRule
{
    /**
     * @param  mixed  $currency  A currency object or a code. Deliberately untyped, because the
     *                           idiomatic call passes `$request->input('price_currency')` straight
     *                           in, and a client is free to send an array there: anything that is
     *                           not a code is the default currency rather than a TypeError.
     */
    public function __construct(
        protected mixed $currency = null,
        protected ?string $locale = null,
        protected ?bool $strict = null,
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Whether the field may be empty is required/nullable's business.
        if ($value === null || $value === '') {
            return;
        }

        $currency = $this->currency();
        $locale   = $this->locale ?? app()->getLocale();

        if (! is_string($value) && ! is_int($value) && ! is_float($value) && ! $value instanceof \Stringable) {
            $this->fail($fail, $currency, $locale);

            return;
        }

        try {
            MoneyFormatter::parseToMinor((string) $value, $currency, $locale, strict: $this->strict);
        } catch (ParserException) {
            $this->fail($fail, $currency, $locale);
        }
    }

    protected function currency(): Currency|MoneyCurrency
    {
        if ($this->currency instanceof Currency || $this->currency instanceof MoneyCurrency) {
            return $this->currency;
        }

        $code = is_string($this->currency) || $this->currency instanceof \Stringable
            ? trim((string) $this->currency)
            : '';

        try {
            return Currency::fromCode($code !== '' ? $code : (string) config('larapara.default_currency'));
        } catch (UnsupportedCurrency) {
            return MoneyFormatter::getDefaultCurrency();
        }
    }

    protected function fail(Closure $fail, Currency|MoneyCurrency $currency, string $locale): void
    {
        // An amount the locale writes the way this one does, so the message shows the shape expected
        // rather than describing it.
        $example = MoneyFormatter::formatFromMinor(123456, $currency, $locale, showCurrencySymbol: false);

        $fail('larapara::validation.money')->translate(['example' => $example]);
    }
}
