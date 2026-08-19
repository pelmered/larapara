<?php

namespace Pelmered\LaraPara\MoneyFormatter;

use Money\Currencies\ISOCurrencies;
use Money\Currency as MoneyCurrency;
use Money\Exception\ParserException;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;
use NumberFormatter;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Exceptions\InvalidAmount;
use PhpStaticAnalysis\Attributes\Returns;
use PhpStaticAnalysis\Attributes\Throws;

class MoneyFormatter
{
    /**
     * Magnitude suffixes used by formatShort(), indexed by power of one thousand.
     */
    private const ABBREVIATIONS = ['', 'K', 'M', 'B', 'T', 'Q'];

    public static function formatMoney(
        Money $money,
        string $locale,
        int $outputStyle = NumberFormatter::CURRENCY,
        int $decimals = 2,
    ): string {
        $numberFormatter = self::getNumberFormatter($locale, $outputStyle, $decimals);
        $moneyFormatter  = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies);

        return $moneyFormatter->format($money);  // Outputs something like "$1.234,56"
    }

    public static function format(
        null|int|string|Money $value,
        Currency|MoneyCurrency $currency,
        string $locale,
        int $outputStyle = NumberFormatter::CURRENCY,
        int $decimals = 2,
        bool $showCurrencySymbol = true,
    ): string {
        if ($value === '' || $value === null) {
            return '';
        }

        $money = $value instanceof Money
            ? $value
            : new Money(
                self::toMinorUnits($value),
                $currency instanceof Currency ? $currency->toMoneyCurrency() : $currency
            );

        // The decimal style leaves out the currency symbol while still placing the decimal point
        // by the minor unit of the currency.
        return static::formatMoney($money, $locale, $showCurrencySymbol ? $outputStyle : NumberFormatter::DECIMAL, $decimals);
    }

    public static function numberFormat(
        null|int|float|string $value,
        string $locale,
        int $decimals = 2,
        int $minorDecimals = 2
    ): string {
        if (! is_numeric($value)) {
            return '';
        }

        // An int is minor units, anything with a decimal point is already a major amount.
        if (is_int($value)) {
            $value /= 10 ** $minorDecimals;
        }

        $numberFormatter = self::getNumberFormatter($locale, NumberFormatter::DECIMAL, $decimals);

        return (string) $numberFormatter->format((float) $value);  // Outputs something like "1.234,56"
    }

    public static function formatShort(
        null|int|string|Money $value,
        Currency|MoneyCurrency $currency,
        string $locale,
        int $decimals = 2,
        bool $showCurrencySymbol = true
    ): string {
        if ($value instanceof Money) {
            $value = $value->getAmount();
        }

        if ($value === '' || $value === null) {
            return '';
        }

        $major = (float) self::toMinorUnits($value) / 10 ** self::getMinorUnit($currency);

        // No need to abbreviate if the amount is less than 1000
        if (abs($major) < 1000) {
            return static::format($value, $currency, $locale, decimals: $decimals, showCurrencySymbol: $showCurrencySymbol);
        }

        [$mantissa, $suffix] = self::abbreviate($major, $decimals);

        if (! $showCurrencySymbol) {
            return static::numberFormat($mantissa, $locale, decimals: $decimals).$suffix;
        }

        // The suffix goes into the ICU pattern rather than into the formatted output, which leaves
        // the symbol, its placement, the digits of the locale and its directional marks to ICU.
        $moneyCurrency = $currency instanceof Currency ? $currency->toMoneyCurrency() : $currency;

        return (string) self::getNumberFormatter($locale, NumberFormatter::CURRENCY, $decimals, numberSuffix: $suffix)
            ->formatCurrency($mantissa, $moneyCurrency->getCode());
    }

    public static function parseDecimal(
        ?string $moneyString,
        Currency|MoneyCurrency $currency,
        string $locale,
        int $decimals = 2
    ): string {
        if (is_null($moneyString) || $moneyString === '') {
            return '';
        }

        $currency    = $currency instanceof Currency ? $currency->toMoneyCurrency() : $currency;
        $moneyString = trim($moneyString);

        $numberFormatter = self::getNumberFormatter($locale, NumberFormatter::DECIMAL, $decimals);
        $parsed          = self::parseLocalizedNumber($numberFormatter, $moneyString);

        if ($parsed === false) {
            $formattingRules = self::getFormattingRules($locale, $currency);

            // A grouping separator out of position is the most common way for user input to miss its
            // locale, so give it a second reading before giving up.
            // See: https://github.com/pelmered/larapara/issues/20
            if ($formattingRules->groupingSeparator !== '' && str_contains($moneyString, $formattingRules->groupingSeparator)) {
                // A grouping separator carries no value of its own, so it is dropped — except a dot,
                // which is the decimal separator of nearly every keyboard and spreadsheet people
                // type numbers into. Dropping that would turn 1.5 into 15 and leave no way to type a
                // decimal point at all, so in the locales that group with dots it is read as one.
                $reading = $formattingRules->groupingSeparator === '.'
                    ? $formattingRules->decimalSeparator
                    : '';

                $parsed = self::parseLocalizedNumber($numberFormatter, str_replace(
                    $formattingRules->groupingSeparator,
                    $reading,
                    $moneyString
                ));
            }
        }

        if ($parsed === false) {
            throw new ParserException('The value must be a valid numeric value.');
        }

        try {
            // Formatted rather than cast to a string: (string) on a float goes through the
            // `precision` ini setting, which deforms anything above 14 significant digits.
            $decimalString = sprintf('%.'.(new ISOCurrencies)->subunitFor($currency).'F', $parsed);

            return (new DecimalMoneyParser(new ISOCurrencies))->parse($decimalString, $currency)->getAmount();
        } catch (ParserException $e) {
            throw new ParserException('The value must be a valid numeric value.', 0, $e);
        }
    }

    public static function getFormattingRules(string $locale, Currency|MoneyCurrency $currency): CurrencyFormattingRules
    {
        $config          = config('larapara');
        $currencyCode    = $currency->getCode();
        $numberFormatter = new NumberFormatter($locale.'@currency='.$currencyCode, NumberFormatter::CURRENCY);

        $currencySymbol = $currencyCode;

        if (! $config['intl_currency_symbol']) {
            // ICU locale keywords only accept 3 character currency codes, so longer ones (most crypto
            // currencies) are dropped and the formatter falls back to the currency of the locale's
            // region. Its symbol would be the wrong one, so keep the code in that case.
            $icuKnowsCurrency = $numberFormatter->getSymbol(NumberFormatter::INTL_CURRENCY_SYMBOL) === $currencyCode;

            if ($icuKnowsCurrency) {
                $currencySymbol = $numberFormatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL);
            }
        }

        return new CurrencyFormattingRules(
            currencySymbol: $currencySymbol,
            fractionDigits: $numberFormatter->getAttribute(NumberFormatter::FRACTION_DIGITS),
            decimalSeparator: $numberFormatter->getSymbol(NumberFormatter::DECIMAL_SEPARATOR_SYMBOL),
            groupingSeparator: $numberFormatter->getSymbol(NumberFormatter::GROUPING_SEPARATOR_SYMBOL),
        );
    }

    public static function getDefaultCurrency(): Currency
    {
        $defaultCurrencyCode = (string) (config('larapara.default_currency'));

        return Currency::fromCode($defaultCurrencyCode);
    }

    /**
     * The minor unit of the currency, which is how many decimals its amounts carry.
     */
    private static function getMinorUnit(Currency|MoneyCurrency $currency): int
    {
        $isoCurrencies = new ISOCurrencies;
        $moneyCurrency = $currency instanceof Currency ? $currency->toMoneyCurrency() : $currency;

        if ($isoCurrencies->contains($moneyCurrency)) {
            return $isoCurrencies->subunitFor($moneyCurrency);
        }

        // Crypto currencies are not part of ISO 4217, so their minor unit comes from our own data.
        return $currency instanceof Currency ? $currency->minorUnit ?? 2 : 2;
    }

    /**
     * Amounts are whole minor units. Anything else is a mistake we should not silently truncate.
     */
    #[Returns('int|numeric-string')]
    #[Throws(InvalidAmount::class)]
    private static function toMinorUnits(int|string $value): int|string
    {
        if (is_int($value)) {
            return $value;
        }

        $amount = trim($value);

        // Numeric, and whole rather than fractional or in exponent notation.
        if (! is_numeric($amount) || ! ctype_digit(ltrim($amount, '-'))) {
            throw InvalidAmount::notMinorUnits($value);
        }

        return $amount;
    }

    /**
     * Splits a major amount into a mantissa below one thousand and its magnitude suffix.
     */
    #[Returns('array{0: float, 1: string}')]
    private static function abbreviate(float $major, int $decimals): array
    {
        $lastMagnitude = count(self::ABBREVIATIONS) - 1;
        $magnitude     = min((int) (log10(abs($major)) / 3), $lastMagnitude);
        $mantissa      = $major / 10 ** ($magnitude * 3);

        // Rounding to the requested decimals can carry the mantissa into the next magnitude.
        if ($magnitude < $lastMagnitude && abs(round($mantissa, max($decimals, 0))) >= 1000) {
            $magnitude++;
            $mantissa /= 1000;
        }

        return [$mantissa, self::ABBREVIATIONS[$magnitude]];
    }

    /**
     * Parses a localized number, or returns false unless the whole string is one.
     */
    private static function parseLocalizedNumber(NumberFormatter $numberFormatter, string $value): float|false
    {
        $position = 0;
        $parsed   = $numberFormatter->parse($value, NumberFormatter::TYPE_DOUBLE, $position);

        // ICU stops at the first character it cannot read, so a partial parse would silently drop
        // the rest of the string. The position counts characters rather than bytes.
        if ($parsed === false || $position !== mb_strlen($value) || ! is_finite($parsed)) {
            return false;
        }

        return $parsed;
    }

    private static function getNumberFormatter(
        string $locale,
        int $style,
        int $decimals = 2,
        string $numberSuffix = '',
    ): NumberFormatter {
        $config = config('larapara');

        $numberFormatter = new NumberFormatter($locale, $style);

        $isCurrencyStyle = $style === NumberFormatter::CURRENCY || $style === NumberFormatter::CURRENCY_ACCOUNTING;

        // Before the decimals, since the pattern carries its own fraction digits.
        if ($isCurrencyStyle && $config['intl_currency_symbol']) {
            $numberFormatter->setPattern(self::intlCurrencyPattern($numberFormatter->getPattern()));
        }

        if ($numberSuffix !== '') {
            $numberFormatter->setPattern(self::numberSuffixPattern($numberFormatter->getPattern(), $numberSuffix));
        }

        if ($decimals < 0) {
            $numberFormatter->setAttribute(NumberFormatter::MAX_SIGNIFICANT_DIGITS, abs($decimals));
        } else {
            $numberFormatter->setAttribute(NumberFormatter::FRACTION_DIGITS, $decimals);
        }

        return $numberFormatter;
    }

    /**
     * Switches the currency placeholder of a pattern to the international one.
     *
     * ICU renders "\u{a4}" as the currency symbol and "\u{a4}\u{a4}" as its ISO code, which is what
     * the international symbol is, and substitutes the currency it is asked to format rather than
     * the one of the locale. Leaving the substitution to ICU keeps the placement, spacing,
     * directional marks and negative pattern of the locale.
     */
    private static function intlCurrencyPattern(string $pattern): string
    {
        return preg_replace('/\x{00A4}+/u', "\u{a4}\u{a4}", $pattern) ?? $pattern;
    }

    /**
     * Appends a literal to the number of a pattern, in every subpattern it has.
     *
     * The number is the only part of the pattern built from "#" and "0", so the affixes carrying the
     * currency symbol, the sign and the directional marks of the locale stay where the locale put them.
     */
    private static function numberSuffixPattern(string $pattern, string $suffix): string
    {
        return preg_replace('/[#0][#0.,]*/', '$0\''.$suffix.'\'', $pattern) ?? $pattern;
    }
}
