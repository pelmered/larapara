<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Exceptions;

use InvalidArgumentException;
use Pelmered\LaraPara\LaraParaServiceProvider;

class InvalidColumnScale extends InvalidArgumentException
{
    public static function negative(int $scale): self
    {
        return new self(
            'A decimal scale is a count of decimals, so '.$scale.' is not one. Set '
            .'`larapara.store.decimal_scale`, the `scale:` argument of a column macro and the '
            .'parameter of MoneyCast to zero or more decimals.'
        );
    }

    public static function exceedsColumnDigits(string $column, int $scale, int $digits): self
    {
        return new self(
            'The column "'.$column.'" would keep '.$scale.' decimals of the '.$digits.' digits it holds, which '
            .'leaves no room for the amount itself. Lower `larapara.store.decimal_scale` or the `scale:` argument, '
            .'or write the column with a wider macro: money() holds '.LaraParaServiceProvider::DECIMAL_DIGITS
            .' digits where smallMoney() holds '.LaraParaServiceProvider::SMALL_DECIMAL_DIGITS.'.'
        );
    }
}
