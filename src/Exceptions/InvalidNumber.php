<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Exceptions;

use InvalidArgumentException;

class InvalidNumber extends InvalidArgumentException
{
    public static function notNumeric(mixed $value): self
    {
        return new self(
            'The given '.get_debug_type($value).' is not a number. '
            .'MoneyFormatter::formatNumber() takes an int, a float or a numeric string; '
            .'a string written the way a locale writes it is read by parseToMinor().'
        );
    }

    /**
     * The value is left out of the message: a number carrying sixteen digits is as often a card
     * number pasted into a field as it is an amount, and this message goes to the log.
     */
    public static function exceedsDoublePrecision(): self
    {
        return new self(
            'The given number carries more significant digits than a double holds, and formatting '
            .'renders it through one, so what came out would be a different number. Fifteen '
            .'significant digits are carried exactly: round the value to that, or keep it as text.'
        );
    }

    public static function conflictingDigits(): self
    {
        return new self('Pass either $decimals or $significantDigits, not both: they are two ways to say how precise the output is.');
    }

    public static function negativeDecimals(int $decimals): self
    {
        return new self(
            '$decimals is a number of decimals, so '.$decimals.' is not one. '
            .'Pass $significantDigits instead — a negative $decimals used to mean that.'
        );
    }

    public static function significantDigitsBelowOne(int $significantDigits): self
    {
        return new self('$significantDigits counts digits, so it starts at 1, not '.$significantDigits.'.');
    }
}
