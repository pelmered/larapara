<?php

namespace Pelmered\LaraPara\MoneyFormatter;

class CurrencyFormattingRules
{
    public function __construct(
        public string $currencySymbol,
        public int $fractionDigits,
        public string $decimalSeparator,
        public string $groupingSeparator,
    ) {}
}
