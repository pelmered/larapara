<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Currencies;

use Money\Currency as MoneyCurrency;
use Money\Money;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;
use PhpStaticAnalysis\Attributes\Throws;

class Currency implements \JsonSerializable, \Stringable
{
    public function __construct(
        public string $code,
        public string $name,
        public ?int $minorUnit = null,
    ) {
        $this->code = strtoupper($code);
    }

    public static function fromCode(string $currencyCode): self
    {
        // The one place a string becomes a code, so the trimming and upper-casing every caller
        // relies on live here rather than at each call site.
        $currencyCode = strtoupper(trim($currencyCode));

        return CurrencyRepository::getAvailableCurrencies()->get($currencyCode)
               ?? throw new UnsupportedCurrency($currencyCode);
    }

    /**
     * Resolves any supported currency representation to a validated, normalized currency code.
     */
    #[Throws(UnsupportedCurrency::class)]
    public static function toCode(self|MoneyCurrency|\Stringable|string $currency): string
    {
        // Every accepted representation stringifies to its code, Money\Currency included.
        return static::fromCode((string) $currency)->getCode();
    }

    public static function fromMoneyCurrency(MoneyCurrency $currency): self
    {
        return static::fromCode($currency->getCode());
    }

    public static function fromMoney(Money $money): self
    {
        return static::fromMoneyCurrency($money->getCurrency());
    }

    public function getCode(): string
    {
        return $this->code;
    }

    public function equals(Currency $other): bool
    {
        return $this->code === $other->code;
    }

    public function __toString(): string
    {
        return $this->code;
    }

    /**
     * The code alone, so a serialized currency reads as the value it is rather than as a record of the
     * registry: `"USD"` beside the `{amount, currency}` of a serialized Money, not a second object
     * repeating the code with a display name next to it.
     */
    public function jsonSerialize(): string
    {
        return $this->code;
    }

    public function toMoneyCurrency(): MoneyCurrency
    {
        if ($this->code === '' || $this->code === '0') {
            return new MoneyCurrency('USD');
        }

        return new MoneyCurrency($this->code);
    }
}
