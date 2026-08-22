<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Throws;

/**
 * Validates that a currency code is one this application supports.
 *
 * The code is trimmed and upper-cased before it is checked, the same way the casts normalize it on
 * write, so anything this rule passes can be stored.
 */
class SupportedCurrency implements ValidationRule
{
    #[Param(currencyCodes: 'list<string>|null')]
    #[Throws(UnsupportedCurrency::class)]
    public function __construct(protected ?array $currencyCodes = null)
    {
        // Narrowing the allow list to a code the configuration does not have would leave a field
        // nothing can satisfy, so it is refused here rather than at every submission.
        $this->currencyCodes = $currencyCodes === null
            ? null
            : array_map(Currency::toCode(...), $currencyCodes);
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // Whether the field may be empty is required/nullable's business.
        if ($value === null || $value === '') {
            return;
        }

        try {
            // The same normalization the casts apply on write, so anything this rule passes can be
            // stored. Currency objects stringify to their code, so one type guard reads them all.
            $currencyCode = is_string($value) || $value instanceof \Stringable
                ? Currency::toCode($value)
                : null;
        } catch (UnsupportedCurrency) {
            $currencyCode = null;
        }

        $isSupported = $currencyCode !== null
            && ($this->currencyCodes === null || in_array($currencyCode, $this->currencyCodes, true));

        if (! $isSupported) {
            $fail('larapara::validation.supported_currency')->translate();
        }
    }
}
