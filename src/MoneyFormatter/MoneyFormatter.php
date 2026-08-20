<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\MoneyFormatter;

use Locale;
use Money\Currencies;
use Money\Currencies\AggregateCurrencies;
use Money\Currencies\CurrencyList;
use Money\Currencies\ISOCurrencies;
use Money\Currency as MoneyCurrency;
use Money\Exception\ParserException;
use Money\Formatter\IntlMoneyFormatter;
use Money\Money;
use Money\Parser\DecimalMoneyParser;
use NumberFormatter;
use Pelmered\LaraPara\Currencies\Currency;
use Pelmered\LaraPara\Exceptions\InvalidAmount;
use PhpStaticAnalysis\Attributes\Param;
use PhpStaticAnalysis\Attributes\Returns;
use PhpStaticAnalysis\Attributes\Throws;

class MoneyFormatter
{
    /**
     * Magnitude suffixes used by formatShort(), indexed by power of one thousand.
     */
    private const ABBREVIATIONS = ['', 'K', 'M', 'B', 'T', 'Q'];

    /**
     * Decimal places a parsed amount is rendered with before it is rounded to its minor unit.
     *
     * Enough to carry any amount a double holds meaningfully, and few enough to absorb the noise of
     * the binary representation — 1.005 is stored as 1.00499999999999989, and rendering it to fewer
     * places than this would round it down to 1.00 rather than to the 1.01 that was typed.
     */
    private const PARSE_DECIMAL_PLACES = 14;

    /**
     * Decimals an abbreviated mantissa carries when the caller names none.
     *
     * A property of the abbreviation rather than of the currency: ¥1.23M keeps three significant
     * digits that the zero minor units of the yen would drop.
     */
    private const ABBREVIATED_DECIMALS = 2;

    /**
     * The character ICU substitutes the currency symbol for in a pattern.
     */
    private const CURRENCY_PLACEHOLDER = "\u{a4}";

    /**
     * Characters that stand for one another as a grouping separator.
     *
     * A locale's grouping separator is one member of a class: sv_SE groups with a no-break space,
     * fr_FR with a narrow one, de_CH with a right single quotation mark. Keyboards produce the plain
     * member — a space, an apostrophe — and ICU reads any of them as grouping where the grouping
     * belongs, so the second reading has to know them all too. Otherwise which member CLDR happens to
     * name decides whose input is forgiven, and that differs between ICU releases.
     */
    /**
     * Formatters built so far, by everything they were built from.
     *
     * @var array<string, NumberFormatter>
     */
    private static array $numberFormatters = [];

    /**
     * Formatters the currency rules are read from, by locale and currency code.
     *
     * @var array<string, NumberFormatter>
     */
    private static array $currencyFormatters = [];

    private const GROUPING_SEPARATOR_CLASSES = [
        ["\u{0020}", "\u{00a0}", "\u{2009}", "\u{202f}"],
        ["\u{0027}", "\u{2019}", "\u{02bc}"],
    ];

    public static function formatMoney(
        Money $money,
        string $locale,
        int $outputStyle = NumberFormatter::CURRENCY,
        ?int $decimals = null,
    ): string {
        $numberFormatter = self::getNumberFormatter($locale, $outputStyle, $decimals ?? self::getMinorUnit($money->getCurrency()));
        $moneyFormatter  = new IntlMoneyFormatter($numberFormatter, new ISOCurrencies);

        return $moneyFormatter->format($money);  // Outputs something like "$1.234,56"
    }

    public static function formatAmount(
        null|int|string|Money $value,
        Currency|MoneyCurrency $currency,
        string $locale,
        int $outputStyle = NumberFormatter::CURRENCY,
        ?int $decimals = null,
        bool $showCurrencySymbol = true,
    ): string {
        if ($value === '' || $value === null) {
            return '';
        }

        if ($value instanceof Money) {
            $currency = $value->getCurrency();
            $value    = $value->getAmount();
        }

        $minorUnit = self::getMinorUnit($currency);
        $decimals ??= $minorUnit;

        if (! $showCurrencySymbol) {
            // Nothing here needs ICU's data for the currency — only the minor unit, to place the
            // decimal point — so this reads a currency ICU has never heard of, crypto included.
            return static::formatNumber((float) self::toMinorUnits($value) / 10 ** $minorUnit, $locale, $decimals);
        }

        $money = new Money(
            self::toMinorUnits($value),
            $currency instanceof Currency ? $currency->toMoneyCurrency() : $currency
        );

        return static::formatMoney($money, $locale, $outputStyle, $decimals);
    }

    /**
     * Formats the number it is given, in the locale it is given.
     *
     * A number rather than an amount: nothing here is scaled, since a count of minor units means
     * nothing without the currency that says how many of them make a unit. Amounts go through
     * formatAmount(), which takes that currency.
     */
    public static function formatNumber(
        null|int|float|string $value,
        string $locale,
        int $decimals = 2,
    ): string {
        if (! is_numeric($value)) {
            return '';
        }

        $numberFormatter = self::getNumberFormatter($locale, NumberFormatter::DECIMAL, $decimals);

        return (string) $numberFormatter->format((float) $value);  // Outputs something like "1.234,56"
    }

    public static function formatShort(
        null|int|string|Money $value,
        Currency|MoneyCurrency $currency,
        string $locale,
        ?int $decimals = null,
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
            return static::formatAmount($value, $currency, $locale, decimals: $decimals, showCurrencySymbol: $showCurrencySymbol);
        }

        $mantissaDecimals = $decimals ?? self::ABBREVIATED_DECIMALS;

        [$mantissa, $suffix] = self::abbreviate($major, $mantissaDecimals);

        if (! $showCurrencySymbol) {
            return static::formatNumber($mantissa, $locale, decimals: $mantissaDecimals).$suffix;
        }

        // The suffix goes into the ICU pattern rather than into the formatted output, which leaves
        // the symbol, its placement, the digits of the locale and its directional marks to ICU.
        $moneyCurrency = $currency instanceof Currency ? $currency->toMoneyCurrency() : $currency;

        return (string) self::getNumberFormatter($locale, NumberFormatter::CURRENCY, $mantissaDecimals, numberSuffix: $suffix)
            ->formatCurrency($mantissa, $moneyCurrency->getCode());
    }

    public static function parseDecimal(
        ?string $moneyString,
        Currency|MoneyCurrency $currency,
        string $locale,
        ?bool $strict = null,
    ): string {
        if (is_null($moneyString) || $moneyString === '') {
            return '';
        }

        $strict ??= (bool) config('larapara.parse.strict', false);

        // Read before the currency is narrowed to a Money one, which carries no minor unit of its own.
        $minorUnit   = self::getMinorUnit($currency);
        $currency    = $currency instanceof Currency ? $currency->toMoneyCurrency() : $currency;
        $moneyString = trim($moneyString);

        // The scale of the result comes from the currency, in the parser below: a number formatter
        // reads every decimal the string carries whatever its fraction digits are set to.
        $numberFormatter = self::getNumberFormatter($locale, NumberFormatter::DECIMAL, $minorUnit);
        $parsed          = self::parseLocalizedNumber($numberFormatter, $moneyString);

        if ($parsed === false && ! $strict) {
            // Separators are the most common way for user input to miss its locale, so give them a
            // second reading before giving up. See: https://github.com/pelmered/larapara/issues/20
            $rewritten = self::rewriteSeparators($moneyString, self::getFormattingRules($locale, $currency));

            if ($rewritten !== $moneyString) {
                $parsed = self::parseLocalizedNumber($numberFormatter, $rewritten);
            }
        }

        if ($parsed === false) {
            throw new ParserException('The value must be a valid numeric value.');
        }

        try {
            // Formatted rather than cast to a string: (string) on a float goes through the `precision`
            // ini setting, which deforms anything above 14 significant digits. The rounding to the
            // minor unit is left to the parser, which rounds half up, rather than done here, where the
            // last representable digit of the double would decide it instead.
            $decimalString = sprintf('%.'.self::PARSE_DECIMAL_PLACES.'F', $parsed);

            return (new DecimalMoneyParser(self::parseCurrencies($currency, $minorUnit)))
                ->parse($decimalString, $currency)
                ->getAmount();
        } catch (ParserException $parserException) {
            throw new ParserException('The value must be a valid numeric value.', 0, $parserException);
        }
    }

    /**
     * The currency data the parser scales its result by.
     *
     * ISO 4217 first, since it is authoritative for the currencies it covers, with the minor unit of
     * the currency being parsed behind it — otherwise a currency ISO has never heard of could be
     * formatted but not read back, and the exception for it is not one a caller can catch as a
     * parse failure.
     */
    #[Param(minorUnit: 'int<0, max>')]
    private static function parseCurrencies(MoneyCurrency $currency, int $minorUnit): Currencies
    {
        return new AggregateCurrencies([
            new ISOCurrencies,
            new CurrencyList([$currency->getCode() => $minorUnit]),
        ]);
    }

    public static function getFormattingRules(string $locale, Currency|MoneyCurrency $currency): CurrencyFormattingRules
    {
        $config          = config('larapara');
        $currencyCode    = $currency->getCode();
        $numberFormatter = self::$currencyFormatters[$locale.'|'.$currencyCode] ??= new NumberFormatter(
            self::currencyKeywordLocale($locale, $currencyCode),
            NumberFormatter::CURRENCY,
        );

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
            fractionDigits: self::getMinorUnit($currency),
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
    #[Returns('int<0, max>')]
    private static function getMinorUnit(Currency|MoneyCurrency $currency): int
    {
        $isoCurrencies = new ISOCurrencies;
        $moneyCurrency = $currency instanceof Currency ? $currency->toMoneyCurrency() : $currency;

        if ($isoCurrencies->contains($moneyCurrency)) {
            return max($isoCurrencies->subunitFor($moneyCurrency), 0);
        }

        // Crypto currencies are not part of ISO 4217, so their minor unit comes from our own data.
        return $currency instanceof Currency ? max($currency->minorUnit ?? 2, 0) : 2;
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
     * Reads the separators of a string that is not a number in its locale the way it was likely meant.
     *
     * A dot becomes the decimal separator of the locale, since that is what a dot means on nearly
     * every keyboard, spreadsheet and programming language people type numbers into. Only a dot that
     * ICU already refused gets here — one in a genuine grouping position parses on the first attempt —
     * so this is safe even in the locales that group with dots, and it is why such a locale must not
     * also have its dots dropped as grouping below.
     *
     * A grouping separator out of position carries no value of its own, so it is dropped: "2,00" in
     * en_US is the same amount as "200".
     */
    private static function rewriteSeparators(string $value, CurrencyFormattingRules $formattingRules): string
    {
        $separators = [];

        if ($formattingRules->decimalSeparator !== '.') {
            $separators['.'] = $formattingRules->decimalSeparator;
        }

        if ($formattingRules->groupingSeparator !== '' && $formattingRules->groupingSeparator !== '.') {
            foreach (self::groupingSeparators($formattingRules->groupingSeparator) as $groupingSeparator) {
                // Grouping never follows the decimal separator, so a string where it does is malformed
                // rather than merely out of position, and dropping it would move the decimal point.
                $decimalPosition  = strpos($value, $formattingRules->decimalSeparator);
                $groupingPosition = strrpos($value, $groupingSeparator);

                if ($decimalPosition !== false && $groupingPosition > $decimalPosition) {
                    return $value;
                }

                $separators[$groupingSeparator] = '';
            }
        }

        return $separators === [] ? $value : strtr($value, $separators);
    }

    /**
     * Every character that stands for the given grouping separator, itself included.
     *
     * @return list<string>
     */
    private static function groupingSeparators(string $groupingSeparator): array
    {
        foreach (self::GROUPING_SEPARATOR_CLASSES as $separatorClass) {
            if (in_array($groupingSeparator, $separatorClass, true)) {
                return $separatorClass;
            }
        }

        return [$groupingSeparator];
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
        $intlCurrencySymbol = (bool) config('larapara.intl_currency_symbol');

        // Building one costs more than everything else a format call does put together, and the same
        // few combinations come back on every row of a listing. The key carries everything the object
        // is built from, the config among it, since a formatter that is right for one setting writes
        // the wrong symbol under the other. Nothing mutates one after it is stored.
        $key = implode('|', [$locale, $style, $decimals, $numberSuffix, (int) $intlCurrencySymbol]);

        return self::$numberFormatters[$key] ??= self::buildNumberFormatter(
            $locale,
            $style,
            $decimals,
            $numberSuffix,
            $intlCurrencySymbol,
        );
    }

    private static function buildNumberFormatter(
        string $locale,
        int $style,
        int $decimals,
        string $numberSuffix,
        bool $intlCurrencySymbol,
    ): NumberFormatter {
        $numberFormatter = new NumberFormatter($locale, $style);

        // Decided by the pattern rather than by an enumeration of styles, since ICU has more currency
        // styles than the two most common ones — cash rounding and the standard variant among them —
        // and every one of them renders the currency placeholder.
        // Before the decimals, since the pattern carries its own fraction digits.
        if ($intlCurrencySymbol && str_contains($numberFormatter->getPattern(), self::CURRENCY_PLACEHOLDER)) {
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
        return preg_replace('/\x{00A4}+/u', self::CURRENCY_PLACEHOLDER.self::CURRENCY_PLACEHOLDER, $pattern) ?? $pattern;
    }

    /**
     * The locale ICU needs to report the rules of a currency the locale itself does not use.
     *
     * An empty locale stands for the default one to every other intl call, but appending a keyword to
     * it makes an identifier ICU refuses outright, so it is resolved before the keyword goes on.
     */
    private static function currencyKeywordLocale(string $locale, string $currencyCode): string
    {
        return ($locale === '' ? Locale::getDefault() : $locale).'@currency='.$currencyCode;
    }

    /**
     * Appends a literal to the number of a pattern, in every subpattern it has.
     *
     * The number is the only part of the pattern built from "#" and "0", so the affixes carrying the
     * currency symbol, the sign and the directional marks of the locale stay where the locale put them.
     */
    private static function numberSuffixPattern(string $pattern, string $suffix): string
    {
        return preg_replace('/[#0][#0.,]*/', '$0\''.$suffix."'", $pattern) ?? $pattern;
    }
}
