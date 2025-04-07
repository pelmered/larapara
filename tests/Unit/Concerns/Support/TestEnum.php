<?php

namespace Pelmered\LaraPara\Tests\Unit\Concerns\Support;

use Pelmered\LaraPara\Concerns\EnumHelpers;

enum TestEnum: string
{
    use EnumHelpers;

    case Red   = 'red';
    case Green = 'green';
    case Blue  = 'blue';
}
