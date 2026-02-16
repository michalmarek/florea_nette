<?php declare(strict_types=1);

namespace App\Model\Product;

/**
 * Product Entity
 *
 * Represents a product from es_zbozi + es_zbozi_text tables.
 *
 * Uses hybrid approach:
 * - Public readonly: Simple data passthrough
 * - Private readonly + getter: Data with business logic (prices)
 */
class Product
{
    public function __construct(
        // === Core data (es_zbozi) ===
        public readonly int $id,
        public readonly int $shopId,
        public readonly int $sellerId,
        public readonly int $categoryId,
        public readonly ?string $groupCode,
        public readonly string $name,
        public readonly float $stock,
        public readonly bool $visible,

        // === Text data (es_zbozi_text) ===
        public readonly string $url,
        public readonly string $description,
        public readonly string $metaKeywords,
        public readonly string $metaDescription,

        // === Prices (private, with logic) ===
        private readonly float $price,
        private readonly float $originalPrice,
        private readonly int $vatRate,
    ) {}

    // === Price getters ===

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getPriceWithVat(): float
    {
        return round($this->price * (1 + $this->vatRate / 100), 2);
    }

    public function getOriginalPrice(): float
    {
        return $this->originalPrice;
    }

    public function getOriginalPriceWithVat(): float
    {
        return round($this->originalPrice * (1 + $this->vatRate / 100), 2);
    }

    /**
     * Is this product on sale? (original price higher than current)
     */
    public function isOnSale(): bool
    {
        return $this->originalPrice > $this->price;
    }

    public function getVatRate(): int
    {
        return $this->vatRate;
    }

    // === Stock helpers ===

    public function isInStock(): bool
    {
        return $this->stock > 0;
    }

    public function isAvailable(): bool
    {
        return $this->visible && $this->isInStock();
    }
}