<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Exceptions;

use InvalidArgumentException;

class InvalidNumber extends InvalidArgumentException
{
    public static function notNumeric(mixed $value): self
    {
        return new self(
            'The value '.var_export($value, true).' is not a number. '
            .'MoneyFormatter::formatNumber() takes an int, a float or a numeric string; '
            .'a string written the way a locale writes it is read by parseToMinor().'
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
