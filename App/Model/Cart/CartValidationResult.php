<?php declare(strict_types=1);

namespace App\Model\Cart;

/**
 * Validation result DTO.
 * Contains messages to display after cart validation (removed products, quantity/price changes).
 */
class CartValidationResult
{
    /** @var array[] Messages ['type' => 'danger|warning', 'message' => 'text'] */
    private array $messages = [];

    public function addRemovedProduct(string $productName, string $reason): void
    {
        $this->messages[] = [
            'type' => 'danger',
            'message' => "Produkt '{$productName}' byl odebrán z košíku ({$reason})",
        ];
    }

    public function addQuantityChange(string $productName, int $oldQuantity, int $newQuantity): void
    {
        $this->messages[] = [
            'type' => 'warning',
            'message' => "Množství produktu '{$productName}' bylo sníženo z {$oldQuantity} na {$newQuantity} ks (omezený sklad)",
        ];
    }

    public function addPriceChange(string $productName, float $oldPrice, float $newPrice): void
    {
        $this->messages[] = [
            'type' => 'warning',
            'message' => "Cena produktu '{$productName}' se změnila z {$oldPrice} Kč na {$newPrice} Kč",
        ];
    }

    /** @return array[] */
    public function getMessages(): array
    {
        return $this->messages;
    }

    public function hasMessages(): bool
    {
        return !empty($this->messages);
    }

    public function getMessageCount(): int
    {
        return count($this->messages);
    }
}