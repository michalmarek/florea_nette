<?php declare(strict_types=1);

namespace App\Model\Cart;

use App\Model\Product\Product;

/**
 * Immutable value object representing a single cart item.
 * To change quantity, create new instance via withQuantity().
 */
class CartItem
{
    public function __construct(
        public readonly int $productId,
        public readonly int $quantity,
        public readonly float $priceAtAddition,
        private readonly ?Product $product = null,
    ) {
        if ($quantity < 1) {
            throw new \InvalidArgumentException('Quantity must be at least 1');
        }
    }

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function hasProduct(): bool
    {
        return $this->product !== null;
    }

    public function getPriceAtAddition(): float
    {
        return $this->priceAtAddition;
    }

    public function getSubtotal(): float
    {
        if (!$this->hasProduct()) {
            return 0.0;
        }

        return $this->quantity * $this->product->getPrice();
    }

    public function withQuantity(int $newQuantity): self
    {
        return new self(
            productId: $this->productId,
            quantity: $newQuantity,
            priceAtAddition: $this->priceAtAddition,
            product: $this->product,
        );
    }

    public function withProduct(Product $product): self
    {
        return new self(
            productId: $this->productId,
            quantity: $this->quantity,
            priceAtAddition: $this->priceAtAddition,
            product: $product,
        );
    }

    public function toArray(): array
    {
        return [
            'productId' => $this->productId,
            'quantity' => $this->quantity,
            'priceAtAddition' => $this->priceAtAddition,
        ];
    }

    public static function fromArray(array $data): self
    {
        return new self(
            productId: (int) $data['productId'],
            quantity: (int) $data['quantity'],
            priceAtAddition: (float) $data['priceAtAddition'],
        );
    }
}