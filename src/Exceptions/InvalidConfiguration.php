<?php

declare(strict_types=1);

namespace Pelmered\LaraPara\Exceptions;

use RuntimeException;

/**
 * A currency this configuration asks for and no provider has.
 *
 * Deliberately not an UnsupportedCurrency: that one means "the code you looked up is not one of the
 * configured currencies", and every caller that asks whether a code is supported catches it to
 * answer no. A configured code the provider does not have is the configuration itself being wrong,
 * and answering "no" to every currency in the registry is how one typo came to look like nothing
 * being supported at all.
 */
class InvalidConfiguration extends RuntimeException
{
    public static function unknownCurrency(string $currencyCode): self
    {
        return new self(
            '`larapara.available_currencies` lists "'.$currencyCode.'", which the configured currency '
            .'provider does not have. Remove it, correct it, or — for a crypto currency — set '
            .'`larapara.load_crypto_currencies` to true.'
        );
    }
}
