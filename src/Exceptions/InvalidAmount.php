<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Exceptions;

use InvalidArgumentException;

class InvalidAmount extends InvalidArgumentException
{
    public static function notMinorUnits(string $value): self
    {
        return new self(
            'The amount "'.$value.'" is not a valid amount in minor units. '
            .'Amounts are whole minor units, such as 123456 for 1234.56, given as an int or a numeric string.'
        );
    }
}
