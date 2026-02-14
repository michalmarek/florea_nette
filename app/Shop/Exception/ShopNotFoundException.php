<?php

declare(strict_types=1);

namespace App\Shop\Exception;

class ShopNotFoundException extends \RuntimeException
{
    public static function forDomain(string $domain): self
    {
        return new self(
            "Shop not found for domain: {$domain}. " .
            "Please check config/domains.neon domain mapping."
        );
    }

    public static function forTextId(string $textId): self
    {
        return new self(
            "Shop not found in database for textId: {$textId}. " .
            "Please check fl_shops table."
        );
    }
}
