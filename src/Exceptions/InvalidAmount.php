<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Exceptions;

use InvalidArgumentException;

class InvalidAmount extends InvalidArgumentException
{
    public static function exceedsStoredScale(string $value, string $currency, int $minorUnit, int $scale): self
    {
        return new self(
            'The amount "'.$value.'" in '.$currency.' carries '.$minorUnit.' decimals, and decimal storage keeps '
            .$scale.'. Raise `larapara.store.decimal_scale` (and the column scale), pass a scale to the column '
            .'macro, or store amounts as integer minor units.'
        );
    }

    public static function exceedsIntegerRange(string $value, string $currency): self
    {
        return new self(
            'The stored amount "'.$value.'" in '.$currency.' is more minor units than an integer holds, so it '
            .'cannot be read back as the amount it is. Store amounts this large as a string in a column of their '
            .'own, or in a currency whose minor unit needs fewer digits.'
        );
    }

    public static function exceedsFormattingPrecision(string $value, string $currency): self
    {
        return new self(
            'The amount "'.$value.'" in '.$currency.' is more minor units than a double carries exactly, '
            .'and formatting renders it through one, so it would be written as a neighbouring amount. '
            .'Abbreviate it with formatShortFromMinor(), which is an approximation by intent.'
        );
    }

    public static function notMinorUnits(string $value): self
    {
        return new self(
            'The amount "'.$value.'" is not a valid amount in minor units. '
            .'Amounts are whole minor units, such as 123456 for 1234.56, given as an int or a numeric string.'
        );
    }
}
