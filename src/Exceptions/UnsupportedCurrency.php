<?php

namespace Pelmered\LaraPara\Exceptions;

use RuntimeException;

class UnsupportedCurrency extends RuntimeException
{
    public function __construct(string $currencyCode)
    {
        parent::__construct('Currency not supported: '.$currencyCode.'. You might need to configure this currency in your `larapara.php` config file.');

    }
}
