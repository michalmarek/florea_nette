<?php

declare(strict_types=1);

namespace App\Shop;

class ShopContext
{
    public readonly int $id;
    public readonly string $textId;
    public readonly string $domain;

    private readonly array $data;
    private readonly SellerContext $seller;

    private function __construct(
        int $id,
        string $textId,
        string $domain,
        array $data,
        SellerContext $seller
    ) {
        $this->id = $id;
        $this->textId = $textId;
        $this->domain = $domain;
        $this->data = $data;
        $this->seller = $seller;
    }

    // === Core Identity getters ===

    public function getId(): int
    {
        return $this->id;
    }

    public function getTextId(): string
    {
        return $this->textId;
    }

    public function getDomain(): string
    {
        return $this->domain;
    }

    // === Contact info ===

    public function getEmail(): string
    {
        return $this->data['email'];
    }

    public function getNotificationEmail(): string
    {
        return $this->data['notificationEmail'];
    }

    public function getPhoneNumber(): string
    {
        return $this->data['phoneNumber'];
    }

    public function getUrl(): string
    {
        return $this->data['url'];
    }

    // === Branding ===

    public function getWebsiteName(): string
    {
        return $this->data['websiteName'];
    }

    public function getTitle(): string
    {
        return $this->data['title'];
    }

    public function getDescription(): string
    {
        return $this->data['description'];
    }

    public function getMotto(): string
    {
        return $this->data['motto'];
    }

    // === Shipping & pricing ===

    public function getFreeShippingThreshold(): int
    {
        return $this->data['freeShippingThreshold'];
    }

    public function getShippingPrice(): float
    {
        return $this->data['shippingPrice'];
    }

    public function getPriceLimit(): int
    {
        return $this->data['priceLimit'];
    }

    public function getPriceRatio(): float
    {
        return $this->data['priceRatio'];
    }

    public function getPriceFixed(): int
    {
        return $this->data['priceFixed'];
    }

    // === Localization ===

    public function getLocales(): string
    {
        return $this->data['locales'];
    }

    // === Seller access ===

    public function getSeller(): SellerContext
    {
        return $this->seller;
    }

    public function getSellerId(): int
    {
        return $this->data['sellerId'];
    }

    // === Currency ===

    /**
     * Get currency configuration
     *
     * TODO: Load from parameters (shop-specific or common)
     * For now returns hardcoded CZK
     *
     * @return array{code: string, symbol: string, decimals: int}
     */
    public function getCurrency(): array
    {
        // TODO: Load from DI parameters via shop-specific config
        // For now hardcoded
        return [
            'code' => 'CZK',
            'symbol' => 'Kč',
            'decimals' => 0,
        ];
    }

    public function getCurrencySymbol(): string
    {
        return $this->getCurrency()['symbol'];
    }

    public function getCurrencyCode(): string
    {
        return $this->getCurrency()['code'];
    }

    // === Magic getter for rarely used fields ===
    // (social networks, tracking IDs, opening hours, etc.)

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    // === Factory ===

    public static function createFromData(
        string $domain,
        array $shopData,
        SellerContext $seller
    ): self {
        return new self(
            $shopData['id'],
            $shopData['textId'],
            $domain,
            $shopData,
            $seller
        );
    }
}