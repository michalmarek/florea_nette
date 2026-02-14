<?php

declare(strict_types=1);

namespace App\Shop;

use Nette\Database\Explorer;
use App\Shop\Exception\ShopNotFoundException;

class ShopRepository
{
    public function __construct(
        private Explorer $database
    ) {}

    /**
     * Find shop with seller by textId (eager loading via JOIN)
     *
     * @return array{shop: array, seller: array}
     * @throws ShopNotFoundException
     */
    public function findByTextIdWithSeller(string $textId): array
    {
        $query = "
            SELECT 
                s.id as shop_id,
                s.seller_id as shop_seller_id,
                s.textId,
                s.url,
                s.locales,
                s.nazevWebu,
                s.telefon,
                s.email,
                s.emailOznameni,
                s.messenger,
                s.facebook,
                s.instagram,
                s.youtube,
                s.pinterest,
                s.tiktok,
                s.zakaznickaOtevreno,
                s.linkSledovani,
                s.dopravaZdarma,
                s.dopravaCena,
                s.priceLimit,
                s.priceRatio,
                s.priceFixed,
                s.title,
                s.description,
                s.motto,
                s.enzuzoId,
                s.facebookPixelId,
                
                sel.id as seller_id,
                sel.name as seller_name,
                sel.telefon as seller_telefon,
                sel.email as seller_email,
                sel.emailOznameni as seller_emailOznameni,
                sel.nazevFirmy,
                sel.ulice,
                sel.mesto,
                sel.psc,
                sel.ic,
                sel.dic,
                sel.vatPayer,
                sel.zapisMesto,
                sel.zapisZnacka,
                sel.ucet,
                sel.ucetIban,
                sel.ucetBic,
                sel.dodavatel,
                sel.externalSale,
                sel.gopay
                
            FROM fl_shops s
            LEFT JOIN fl_sellers sel ON s.seller_id = sel.id
            WHERE s.textId = ?
        ";

        $row = $this->database->query($query, $textId)->fetch();

        if (!$row) {
            throw ShopNotFoundException::forTextId($textId);
        }

        // Map shop data (DB columns → PHP properties)
        $shopData = [
            'id' => (int) $row->shop_id,
            'textId' => $row->textId,
            'sellerId' => (int) $row->shop_seller_id,
            'url' => $row->url,
            'locales' => $row->locales,

            // Contact info (translated from Czech)
            'websiteName' => $row->nazevWebu,
            'phoneNumber' => $row->telefon,
            'email' => $row->email,
            'notificationEmail' => $row->emailOznameni,

            // Social networks
            'messenger' => $row->messenger,
            'facebook' => $row->facebook,
            'instagram' => $row->instagram,
            'youtube' => $row->youtube,
            'pinterest' => $row->pinterest,
            'tiktok' => $row->tiktok,

            // Operations
            'openingHours' => $row->zakaznickaOtevreno,
            'trackingLink' => $row->linkSledovani,

            // Shipping & pricing
            'freeShippingThreshold' => (int) $row->dopravaZdarma,
            'shippingPrice' => (float) $row->dopravaCena,
            'priceLimit' => (int) $row->priceLimit,
            'priceRatio' => (float) $row->priceRatio,
            'priceFixed' => (int) $row->priceFixed,

            // SEO & tracking
            'title' => $row->title,
            'description' => $row->description,
            'motto' => $row->motto,
            'enzuzoId' => $row->enzuzoId,
            'facebookPixelId' => $row->facebookPixelId,
        ];

        // Map seller data (DB columns → PHP properties)
        $sellerData = [
            'id' => (int) $row->seller_id,
            'name' => $row->seller_name,
            'phoneNumber' => $row->seller_telefon,
            'email' => $row->seller_email,
            'notificationEmail' => $row->seller_emailOznameni,

            // Company details
            'companyName' => $row->nazevFirmy,
            'street' => $row->ulice,
            'city' => $row->mesto,
            'postalCode' => $row->psc,

            // Tax info
            'registrationNumber' => $row->ic,
            'vatNumber' => $row->dic,
            'vatPayer' => (bool) $row->vatPayer,

            // Registry
            'registryCity' => $row->zapisMesto,
            'registryNumber' => $row->zapisZnacka,

            // Banking
            'bankAccount' => $row->ucet,
            'bankAccountIban' => $row->ucetIban,
            'bankAccountBic' => $row->ucetBic,

            // Business
            'supplier' => $row->dodavatel,
            'externalSale' => (bool) $row->externalSale,
            'gopayConfig' => $row->gopay, // Keep as JSON string
        ];

        return [
            'shop' => $shopData,
            'seller' => $sellerData,
        ];
    }
}