<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Currencies;

class CurrencyFormattingRules
{
    public function __construct(
        public string $currencySymbol,
        public int $fractionDigits,
        public string $decimalSeparator,
        public string $groupingSeparator,
    ) {}
}
