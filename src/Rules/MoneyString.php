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
 * Validates that a localized amount string is one MoneyFormatter::parseDecimal() can read.
 *
 * The locale defaults to the application's, the currency to the configured default, and whether a
 * separator out of place is forgiven to `larapara.parse.strict`. Whether the currency itself is
 * supported is SupportedCurrency's business, so an unsupported one here does not fail the amount —
 * the scale of a currency does not decide whether a number is one.
 */
class MoneyString implements ValidationRule
{
    public function __construct(
        protected Currency|MoneyCurrency|string|null $currency = null,
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
            MoneyFormatter::parseDecimal((string) $value, $currency, $locale, strict: $this->strict);
        } catch (ParserException) {
            $this->fail($fail, $currency, $locale);
        }
    }

    protected function currency(): Currency|MoneyCurrency
    {
        if ($this->currency instanceof Currency || $this->currency instanceof MoneyCurrency) {
            return $this->currency;
        }

        try {
            return Currency::fromCode(trim((string) ($this->currency ?? config('larapara.default_currency'))));
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
