<?php

namespace Pelmered\LaraPara\Enum;

use Pelmered\LaraPara\Concerns\EnumHelpers;

enum CurrencySymbolPlacement: string
{
    use EnumHelpers;

    case Before = 'before';
    case After  = 'after';
    case Hidden = 'hidden';
}
