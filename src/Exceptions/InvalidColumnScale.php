<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Exceptions;

use InvalidArgumentException;
use Pelmered\LaraPara\LaraParaServiceProvider;

class InvalidColumnScale extends InvalidArgumentException
{
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
