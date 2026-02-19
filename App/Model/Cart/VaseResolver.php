<?php declare(strict_types=1);

namespace App\Model\Cart;

use App\Model\Product\Product;
use App\Model\Product\ProductRepository;

/**
 * Resolves matching vase product for a flower bouquet
 * based on stem length and flower count.
 */
class VaseResolver
{
    public function __construct(
        private ProductRepository $productRepository,
    ) {}

    /**
     * Find matching vase for a product.
     * Returns Product or null if no suitable vase found/in stock.
     */
    public function resolve(Product $mainProduct, int $shopId): ?Product
    {
        // TODO: načíst z parametrů produktu
        $stemLength = 0;
        $flowerCount = 0;

        $vaseId = $this->findVaseId($mainProduct->id, $mainProduct->categoryId, $stemLength, $flowerCount);

        if ($vaseId === null) {
            return null;
        }

        $vase = $this->productRepository->findById($vaseId, $shopId);

        if (!$vase || $vase->stock <= 0) {
            return null;
        }

        return $vase;
    }

    /**
     * Core logic — find vase product ID based on parameters.
     * TODO: Michal doplní reálnou logiku z legacy getVase().
     */
    private function findVaseId(int $productId, int $categoryId, int $stemLength, int $flowerCount): ?int
    {
        if ($stemLength === 0 || $flowerCount === 0) {
            return null;
        }

        // Placeholder — doplnit lookup tabulku + logiku z legacy
        return null;
    }
}