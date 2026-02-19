<?php declare(strict_types=1);

namespace App\Model\Cart;

/**
 * Upsell product IDs and configuration constants.
 */
class UpsellConfig
{
    // Special product IDs
    public const CARD_MESSAGE_PRODUCT_ID = 3;      // TODO: doplnit reálné ID
    public const RIBBON_MESSAGE_PRODUCT_ID = 6;     // TODO: doplnit reálné ID
    public const PREMIUM_RIBBON_PRODUCT_ID = 9;     // TODO: doplnit reálné ID
    public const PHOTO_PRODUCT_ID = 44;              // TODO: doplnit reálné ID

    // Premium ribbon color options
    public const PREMIUM_RIBBON_COLORS = [
        'red' => 'Červená',
        'gold' => 'Zlatá',
        'silver' => 'Stříbrná',
        'white' => 'Bílá',
        'black' => 'Černá',
    ];
}