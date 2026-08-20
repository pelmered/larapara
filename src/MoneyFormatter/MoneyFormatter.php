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
use Pelmered\LaraPara\Exceptions\InvalidNumber;
use Pelmered\LaraPara\Exceptions\UnsupportedCurrency;
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
    private const GROUPING_SEPARATOR_CLASSES = [
        ["\u{0020}", "\u{00a0}", "\u{2009}", "\u{202f}"],
        ["\u{0027}", "\u{2019}", "\u{02bc}"],
    ];

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

    /**
     * Formats a Money object, which carries both the amount and the currency it is counted in.
     */
    public static function format(
        Money $money,
        string $locale,
        int $outputStyle = NumberFormatter::CURRENCY,
        ?int $decimals = null,
        ?int $significantDigits = null,
        bool $showCurrencySymbol = true,
    ): string {
        return static::formatFromMinor(
            $money->getAmount(),
            $money->getCurrency(),
            $locale,
            $outputStyle,
            $decimals,
            $significantDigits,
            $showCurrencySymbol,
        );
    }

    /**
     * Formats an amount given in the minor units of a currency: 123456 in USD is $1,234.56.
     *
     * Which is what every amount in this package is — what MoneyCast stores, what Money::getAmount()
     * returns and what parseToMinor() reads a string into. An amount that is not whole minor units
     * throws rather than being truncated into a plausible wrong one.
     */
    public static function formatFromMinor(
        null|int|string $value,
        Currency|MoneyCurrency $currency,
        string $locale,
        int $outputStyle = NumberFormatter::CURRENCY,
        ?int $decimals = null,
        ?int $significantDigits = null,
        bool $showCurrencySymbol = true,
    ): string {
        if ($value === '' || $value === null) {
            return '';
        }

        self::assertDigits($decimals, $significantDigits);

        $minorUnit = self::getMinorUnit($currency);

        // An amount carries the decimals of its currency unless the caller asks for others, which is
        // what makes ¥1,000 and BHD 1,234.567 come out right without being asked for.
        $decimals ??= $significantDigits === null ? $minorUnit : null;

        if (! $showCurrencySymbol) {
            // Nothing here needs ICU's data for the currency — only the minor unit, to place the
            // decimal point — so this reads a currency ICU has never heard of, crypto included.
            return static::formatNumber(
                (float) self::toMinorUnits($value) / 10 ** $minorUnit,
                $locale,
                $decimals,
                $significantDigits,
            );
        }

        $moneyCurrency = $currency instanceof Currency ? $currency->toMoneyCurrency() : $currency;

        $moneyFormatter = new IntlMoneyFormatter(
            self::getNumberFormatter($locale, $outputStyle, $decimals, $significantDigits),
            self::currenciesFor($moneyCurrency, $minorUnit),
        );

        return $moneyFormatter->format(new Money(self::toMinorUnits($value), $moneyCurrency));  // "$1.234,56"
    }

    /**
     * Formats a number in a locale: 1234.5 in de_DE is "1.234,5".
     *
     * A number rather than an amount — nothing here is scaled, since a count of minor units means
     * nothing without the currency that says how many of them make a unit, and amounts go through
     * formatFromMinor(), which takes that currency. The value is a PHP number, not a localized
     * string: reading one of those is parseToMinor()'s job.
     *
     * Without `decimals` or `significantDigits` the number keeps the decimals it has, which is what
     * the locale would print. Empty in, empty out; anything else that is not a number is a mistake
     * in the caller rather than a number to render as nothing.
     */
    #[Throws(InvalidNumber::class)]
    public static function formatNumber(
        null|int|float|string $value,
        string $locale,
        ?int $decimals = null,
        ?int $significantDigits = null,
    ): string {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_numeric($value)) {
            throw InvalidNumber::notNumeric($value);
        }

        self::assertDigits($decimals, $significantDigits);

        $numberFormatter = self::getNumberFormatter($locale, NumberFormatter::DECIMAL, $decimals, $significantDigits);

        return (string) $numberFormatter->format((float) $value);  // Outputs something like "1.234,56"
    }

    /**
     * Abbreviates a Money object: $1.23M rather than $1,234,567.89.
     */
    public static function formatShort(
        Money $money,
        string $locale,
        ?int $decimals = null,
        ?int $significantDigits = null,
        bool $showCurrencySymbol = true,
    ): string {
        return static::formatShortFromMinor(
            $money->getAmount(),
            $money->getCurrency(),
            $locale,
            $decimals,
            $significantDigits,
            $showCurrencySymbol,
        );
    }

    /**
     * Abbreviates an amount given in the minor units of a currency.
     */
    public static function formatShortFromMinor(
        null|int|string $value,
        Currency|MoneyCurrency $currency,
        string $locale,
        ?int $decimals = null,
        ?int $significantDigits = null,
        bool $showCurrencySymbol = true,
    ): string {
        if ($value === '' || $value === null) {
            return '';
        }

        self::assertDigits($decimals, $significantDigits);

        $major = (float) self::toMinorUnits($value) / 10 ** self::getMinorUnit($currency);

        // No need to abbreviate if the amount is less than 1000
        if (abs($major) < 1000) {
            return static::formatFromMinor(
                $value,
                $currency,
                $locale,
                decimals: $decimals,
                significantDigits: $significantDigits,
                showCurrencySymbol: $showCurrencySymbol,
            );
        }

        // Two decimals unless the caller says otherwise, since ¥1.23M keeps three significant digits
        // that the zero minor units of the yen would drop.
        $mantissaDecimals = $significantDigits === null ? $decimals ?? self::ABBREVIATED_DECIMALS : null;

        [$mantissa, $suffix] = self::abbreviate($major, $mantissaDecimals, $significantDigits);

        if (! $showCurrencySymbol) {
            return static::formatNumber($mantissa, $locale, $mantissaDecimals, $significantDigits).$suffix;
        }

        // The suffix goes into the ICU pattern rather than into the formatted output, which leaves
        // the symbol, its placement, the digits of the locale and its directional marks to ICU.
        $moneyCurrency = $currency instanceof Currency ? $currency->toMoneyCurrency() : $currency;

        return (string) self::getNumberFormatter($locale, NumberFormatter::CURRENCY, $mantissaDecimals, $significantDigits, numberSuffix: $suffix)
            ->formatCurrency($mantissa, $moneyCurrency->getCode());
    }

    /**
     * Reads a localized amount string into the minor units of a currency: "1,234.56" in USD is 123456.
     *
     * A numeric string rather than an int, since that is what a Money holds and what the casts store.
     * The string is read through a double on the way, so an amount above 2**53 minor units is exact
     * only to the precision a double has.
     */
    #[Returns("numeric-string|''")]
    public static function parseToMinor(
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

        if ($parsed === false) {
            // The formatter writes the symbol of the currency, so the parser reads it back: an
            // application that shows $1,234.56 in a field gets that string in the request, and a
            // parser that refuses its own output is a trap. Strict mode accepts this, since this is
            // what the locale writes — in the notation this configuration writes it in.
            $parsed = self::parseCurrencyAmount($moneyString, $currency, $locale, $minorUnit, anyNotation: false);
        }

        if ($parsed === false && ! $strict) {
            $formattingRules = self::getFormattingRules($locale, $currency);

            // Separators are the most common way for user input to miss its locale, so give them a
            // second reading before giving up. See: https://github.com/pelmered/larapara/issues/20
            $rewritten = self::rewriteSeparators($moneyString, $formattingRules);

            if ($rewritten !== $moneyString) {
                $parsed = self::parseLocalizedNumber($numberFormatter, $rewritten);
            }

            if ($parsed === false) {
                $parsed = self::parseCurrencyAmount($moneyString, $currency, $locale, $minorUnit, anyNotation: true);
            }

            if ($parsed === false) {
                // The currency written where the locale does not put it. ICU reads a currency only
                // where the formatter would have written it, and a person filling in a form is not a
                // formatter: "12 USD" in a locale that writes "$12" has one reading and no other, so
                // it is read like every other separator the locale itself would refuse.
                $withoutCurrency = self::withoutCurrency($moneyString, $locale, $currency);

                if ($withoutCurrency !== $moneyString) {
                    $parsed = self::parseLocalizedNumber($numberFormatter, $withoutCurrency);

                    if ($parsed === false) {
                        $rewritten = self::rewriteSeparators($withoutCurrency, $formattingRules);

                        if ($rewritten !== $withoutCurrency) {
                            $parsed = self::parseLocalizedNumber($numberFormatter, $rewritten);
                        }
                    }
                }
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

            return (new DecimalMoneyParser(self::currenciesFor($currency, $minorUnit)))
                ->parse($decimalString, $currency)
                ->getAmount();
        } catch (ParserException $parserException) {
            throw new ParserException('The value must be a valid numeric value.', 0, $parserException);
        }
    }

    /**
     * The currency data a formatter or a parser places the decimal point by.
     *
     * ISO 4217 first, since it is authoritative for the currencies it covers, with the minor unit of
     * the currency in hand behind it — otherwise a currency ISO has never heard of throws an
     * exception that is neither a parse failure a caller can catch nor anything ICU could not have
     * rendered: ICU writes the code as the symbol for a currency it has no symbol for.
     */
    #[Param(minorUnit: 'int<0, max>')]
    private static function currenciesFor(MoneyCurrency $currency, int $minorUnit): Currencies
    {
        return new AggregateCurrencies([
            new ISOCurrencies,
            new CurrencyList([$currency->getCode() => $minorUnit]),
        ]);
    }

    /**
     * Reads a localized amount string into a Money object, which is the inverse of format().
     *
     * A currency given as a code is resolved through the registry, so one this configuration does not
     * support is refused here rather than stored and read back as an exception — and a code carries
     * the minor unit of a currency ICU has no data for, which a bare Money\Currency does not.
     *
     * Null for an empty string, since no amount is not an amount of anything.
     */
    #[Throws(ParserException::class)]
    #[Throws(UnsupportedCurrency::class)]
    public static function parseToMoney(
        ?string $moneyString,
        Currency|MoneyCurrency|string $currency,
        string $locale,
        ?bool $strict = null,
    ): ?Money {
        $currency = is_string($currency) ? Currency::fromCode(trim($currency)) : $currency;
        $amount   = static::parseToMinor($moneyString, $currency, $locale, $strict);

        if ($amount === '') {
            return null;
        }

        return new Money(
            $amount,
            $currency instanceof Currency ? $currency->toMoneyCurrency() : $currency,
        );
    }

    public static function getFormattingRules(string $locale, Currency|MoneyCurrency $currency): CurrencyFormattingRules
    {
        $config          = config('larapara');
        $currencyCode    = $currency->getCode();
        $numberFormatter = self::currencyFormatter($locale, $currencyCode);

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

    /**
     * The formatter the rules and symbols of a currency are read from, in a locale.
     */
    private static function currencyFormatter(string $locale, string $currencyCode): NumberFormatter
    {
        $locale = self::resolveLocale($locale);

        return self::$currencyFormatters[$locale.'|'.$currencyCode] ??= new NumberFormatter(
            $locale.'@currency='.$currencyCode,
            NumberFormatter::CURRENCY,
        );
    }

    public static function getDefaultCurrency(): Currency
    {
        $defaultCurrencyCode = (string) (config('larapara.default_currency'));

        return Currency::fromCode($defaultCurrencyCode);
    }

    /**
     * Refuses a request for two kinds of precision at once, or for a negative amount of it.
     *
     * A negative `decimals` used to mean significant digits, which no signature said and no reader
     * could guess, so it is a mistake now rather than a second meaning.
     */
    #[Throws(InvalidNumber::class)]
    private static function assertDigits(?int $decimals, ?int $significantDigits): void
    {
        if ($decimals !== null && $significantDigits !== null) {
            throw InvalidNumber::conflictingDigits();
        }

        if ($decimals !== null && $decimals < 0) {
            throw InvalidNumber::negativeDecimals($decimals);
        }

        if ($significantDigits !== null && $significantDigits < 1) {
            throw InvalidNumber::significantDigitsBelowOne($significantDigits);
        }
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

        // Crypto currencies are not part of ISO 4217, so their minor unit comes from our own data —
        // read from the registry for a bare Money\Currency, which carries a code and nothing else.
        // Otherwise the same code means eight decimals as a Currency and two as a Money\Currency,
        // and the amount a call renders would depend on which object the caller happened to hold.
        $minorUnit = $currency instanceof Currency
            ? $currency->minorUnit
            : self::registeredMinorUnit($moneyCurrency);

        return max($minorUnit ?? 2, 0);
    }

    /**
     * The minor unit the registry holds for a code, or null where this configuration has no such
     * currency and there is nothing left to read it from.
     */
    private static function registeredMinorUnit(MoneyCurrency $currency): ?int
    {
        try {
            return Currency::fromCode($currency->getCode())->minorUnit;
        } catch (UnsupportedCurrency) {
            return null;
        }
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
    private static function abbreviate(float $major, ?int $decimals, ?int $significantDigits): array
    {
        $lastMagnitude = count(self::ABBREVIATIONS) - 1;
        $magnitude     = min((int) (log10(abs($major)) / 3), $lastMagnitude);
        $mantissa      = $major / 10 ** ($magnitude * 3);

        // Rounding to the precision the output carries can take the mantissa into the next
        // magnitude, and 1,000K is not an abbreviation of anything.
        $rounded = self::roundToPrecision($mantissa, $decimals, $significantDigits);

        if ($magnitude < $lastMagnitude && abs($rounded) >= 1000) {
            $magnitude++;
            $mantissa /= 1000;
        }

        return [$mantissa, self::ABBREVIATIONS[$magnitude]];
    }

    /**
     * A number rounded the way the output will write it, in decimals or in significant digits.
     */
    private static function roundToPrecision(float $value, ?int $decimals, ?int $significantDigits): float
    {
        if ($significantDigits === null) {
            return round($value, max($decimals ?? self::ABBREVIATED_DECIMALS, 0));
        }

        // Significant digits count from the first one, so how many decimals they leave depends on
        // how many integer digits there are: 999.6 to one significant digit is 1000, not 999.6.
        $integerDigits = (int) floor(log10(abs($value))) + 1;

        return round($value, max($significantDigits - $integerDigits, 0));
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
     * Parses an amount written with the symbol of the currency it is being read as.
     *
     * Strictly, that is the notation this configuration writes — the symbol or the ISO code,
     * according to `intl_currency_symbol`, where the locale puts it. Leniently, either notation and
     * either member of the space class a keyboard might have produced between the two.
     *
     * ICU reads any currency's symbol, so the code it read has to be the one asked for: €10 read as
     * USD is a refusal rather than ten dollars.
     */
    #[Param(minorUnit: 'int<0, max>')]
    private static function parseCurrencyAmount(
        string $value,
        MoneyCurrency $currency,
        string $locale,
        int $minorUnit,
        bool $anyNotation,
    ): float|false {
        $formatters = [self::getNumberFormatter($locale, NumberFormatter::CURRENCY, $minorUnit)];
        $candidates = [$value];

        if ($anyNotation) {
            $formatters[] = self::currencyFormatter($locale, $currency->getCode());
            $candidates[] = strtr($value, array_fill_keys(self::GROUPING_SEPARATOR_CLASSES[0], "\u{00a0}"));
        }

        foreach ($formatters as $formatter) {
            foreach (array_unique($candidates) as $candidate) {
                $code     = null;
                $position = 0;
                $parsed   = $formatter->parseCurrency($candidate, $code, $position);

                if ($parsed === false || ! is_finite($parsed) || $position !== mb_strlen($candidate)) {
                    continue;
                }

                if ($code === $currency->getCode()) {
                    return $parsed;
                }
            }
        }

        return false;
    }

    /**
     * The string without the currency beside it, wherever it was written.
     *
     * Only the currency being read, so "12 EUR" as USD stays a refusal rather than becoming twelve
     * dollars, and only one occurrence of it, so "12 USD USD" is still not a number.
     */
    private static function withoutCurrency(string $value, string $locale, MoneyCurrency $currency): string
    {
        foreach (self::currencyNotations($locale, $currency) as $notation) {
            if ($notation === '') {
                continue;
            }

            foreach ([self::removePrefix($value, $notation), self::removeSuffix($value, $notation)] as $stripped) {
                if ($stripped !== $value) {
                    // Whatever stood between the two is the space of the locale, or the space of a
                    // keyboard, and the separator rules below read either.
                    return trim(str_replace(self::GROUPING_SEPARATOR_CLASSES[0], ' ', $stripped));
                }
            }
        }

        return $value;
    }

    /**
     * The ways the currency can be written beside an amount: its ISO code, and its symbol here.
     *
     * @return list<string>
     */
    private static function currencyNotations(string $locale, MoneyCurrency $currency): array
    {
        $code            = $currency->getCode();
        $numberFormatter = self::currencyFormatter($locale, $code);

        // ICU falls back to the currency of the locale's region for a code it does not know, whose
        // symbol belongs to a different currency altogether.
        $symbol = $numberFormatter->getSymbol(NumberFormatter::INTL_CURRENCY_SYMBOL) === $code
            ? $numberFormatter->getSymbol(NumberFormatter::CURRENCY_SYMBOL)
            : '';

        return [$code, $symbol];
    }

    private static function removePrefix(string $value, string $prefix): string
    {
        return str_starts_with($value, $prefix) ? substr($value, strlen($prefix)) : $value;
    }

    private static function removeSuffix(string $value, string $suffix): string
    {
        return str_ends_with($value, $suffix) ? substr($value, 0, -strlen($suffix)) : $value;
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
        ?int $decimals = null,
        ?int $significantDigits = null,
        string $numberSuffix = '',
    ): NumberFormatter {
        $locale             = self::resolveLocale($locale);
        $intlCurrencySymbol = (bool) config('larapara.intl_currency_symbol');

        // Building one costs more than everything else a format call does put together, and the same
        // few combinations come back on every row of a listing. The key carries everything the object
        // is built from, the config among it, since a formatter that is right for one setting writes
        // the wrong symbol under the other. Nothing mutates one after it is stored.
        $key = implode('|', [
            $locale,
            $style,
            $decimals          ?? 'default',
            $significantDigits ?? 'default',
            $numberSuffix,
            (int) $intlCurrencySymbol,
        ]);

        return self::$numberFormatters[$key] ??= self::buildNumberFormatter(
            $locale,
            $style,
            $decimals,
            $significantDigits,
            $numberSuffix,
            $intlCurrencySymbol,
        );
    }

    private static function buildNumberFormatter(
        string $locale,
        int $style,
        ?int $decimals,
        ?int $significantDigits,
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

        // Neither given leaves the digits of the locale in place, which for a plain number is as many
        // decimals as the value has.
        if ($significantDigits !== null) {
            $numberFormatter->setAttribute(NumberFormatter::MAX_SIGNIFICANT_DIGITS, $significantDigits);
        } elseif ($decimals !== null) {
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
     * The locale a formatter is built for, with the empty one resolved to intl's default.
     *
     * An empty locale stands for the default one to every other intl call, and two things here need
     * it spelled out: appending a `@currency=` keyword to an empty locale makes an identifier ICU
     * refuses outright, and a formatter is kept under the locale it was built for — so leaving the
     * resolution to intl would key one under the empty string and outlive the default it was built
     * from, which a long-running process can change between calls.
     */
    private static function resolveLocale(string $locale): string
    {
        return $locale === '' ? Locale::getDefault() : $locale;
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
