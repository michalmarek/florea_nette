<?php declare(strict_types=1);

namespace App\Model\Cart;

use DateTimeImmutable;

class Cart
{
    /** @var CartItem[] Cart items indexed by product ID */
    private array $items = [];
    private ?string $discountCode = null;

    public function __construct(
        public readonly int $shopId,
        private DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
    ) {}

    // ========================================
    // Item Management
    // ========================================

    public function addItem(int $productId, int $quantity, float $price): void
    {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1');
        }

        if ($this->hasItem($productId)) {
            $existing = $this->items[$productId];
            $this->items[$productId] = $existing->withQuantity($existing->quantity + $quantity);
        } else {
            $this->items[$productId] = new CartItem($productId, $quantity, $price);
        }

        $this->touch();
    }

    public function removeItem(int $productId): void
    {
        unset($this->items[$productId]);
        $this->touch();
    }

    public function updateQuantity(int $productId, int $newQuantity): void
    {
        if (!$this->hasItem($productId)) {
            throw new \InvalidArgumentException("Product {$productId} not in cart");
        }

        if ($newQuantity < 1) {
            $this->removeItem($productId);
            return;
        }

        $this->items[$productId] = $this->items[$productId]->withQuantity($newQuantity);
        $this->touch();
    }

    public function updatePriceAtAddition(int $productId, float $newPrice): void
    {
        if (!$this->hasItem($productId)) {
            throw new \InvalidArgumentException("Product {$productId} not in cart");
        }

        $item = $this->items[$productId];
        $this->items[$productId] = new CartItem(
            $item->productId,
            $item->quantity,
            $newPrice,
            $item->hasProduct() ? $item->getProduct() : null,
        );

        $this->touch();
    }

    public function clear(): void
    {
        $this->items = [];
        $this->discountCode = null;
        $this->touch();
    }

    // ========================================
    // Item Queries
    // ========================================

    public function hasItem(int $productId): bool
    {
        return isset($this->items[$productId]);
    }

    public function getItem(int $productId): ?CartItem
    {
        return $this->items[$productId] ?? null;
    }

    /** @return CartItem[] */
    public function getItems(): array
    {
        return $this->items;
    }

    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    public function getItemCount(): int
    {
        $count = 0;
        foreach ($this->items as $item) {
            $count += $item->quantity;
        }
        return $count;
    }

    public function getUniqueItemCount(): int
    {
        return count($this->items);
    }

    // ========================================
    // Price Calculations
    // ========================================

    public function getTotalPrice(): float
    {
        $total = 0.0;
        foreach ($this->items as $item) {
            $total += $item->getSubtotal();
        }
        return $total;
    }

    // ========================================
    // Discount Code
    // ========================================

    public function applyDiscountCode(string $code): void
    {
        $this->discountCode = $code;
        $this->touch();
    }

    public function removeDiscountCode(): void
    {
        $this->discountCode = null;
        $this->touch();
    }

    public function getDiscountCode(): ?string
    {
        return $this->discountCode;
    }

    public function hasDiscountCode(): bool
    {
        return $this->discountCode !== null;
    }

    // ========================================
    // Product Loading
    // ========================================

    /** @param array $products Products indexed by ID [productId => Product] */
    public function attachProducts(array $products): void
    {
        foreach ($this->items as $productId => $item) {
            if (isset($products[$productId])) {
                $this->items[$productId] = $item->withProduct($products[$productId]);
            }
        }
    }

    /** @return int[] */
    public function getProductIds(): array
    {
        return array_keys($this->items);
    }

    // ========================================
    // Timestamps
    // ========================================

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function isExpired(int $expirationHours): bool
    {
        $expirationTime = $this->updatedAt->modify("+{$expirationHours} hours");
        return new DateTimeImmutable() > $expirationTime;
    }

    // ========================================
    // Serialization (for session)
    // ========================================

    public function toArray(): array
    {
        $items = [];
        foreach ($this->items as $productId => $item) {
            $items[$productId] = $item->toArray();
        }

        return [
            'items' => $items,
            'discountCode' => $this->discountCode,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
            'updatedAt' => $this->updatedAt->format('Y-m-d H:i:s'),
        ];
    }

    public static function fromArray(int $shopId, array $data): self
    {
        $cart = new self(
            shopId: $shopId,
            createdAt: new DateTimeImmutable($data['createdAt']),
            updatedAt: new DateTimeImmutable($data['updatedAt']),
        );

        foreach ($data['items'] as $productId => $itemData) {
            $cart->items[$productId] = CartItem::fromArray($itemData);
        }

        if (isset($data['discountCode'])) {
            $cart->discountCode = $data['discountCode'];
        }

        return $cart;
    }

    public static function create(int $shopId): self
    {
        return new self(
            shopId: $shopId,
            createdAt: new DateTimeImmutable(),
            updatedAt: new DateTimeImmutable(),
        );
    }
}