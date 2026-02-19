<?php declare(strict_types=1);

namespace App\Model\Cart;

use App\Model\Product\Product;
use App\Model\Product\ProductRepository;
use App\Model\Category\BaseCategoryRepository;

/**
 * Provides upsell offers for products based on their base category configuration.
 *
 * Returns array of upsell definitions, each with:
 * - key: unique identifier (e.g. 'card_message', 'product_123')
 * - type: 'simple' / 'text_input' / 'select'
 * - label: display label (null for simple products — use product name)
 * - product: Product entity
 * - inputLabel: label for input field (text_input/select only)
 * - options: array of options (select only)
 */
class UpsellService
{
    public function __construct(
        private BaseCategoryRepository $baseCategoryRepository,
        private ProductRepository $productRepository,
        private VaseResolver $vaseResolver,
    ) {}

    /**
     * Get available upsell offers for a product.
     *
     * @return array[] Array of upsell definitions
     */
    public function getUpsellsForProduct(Product $mainProduct, int $shopId): array
    {
        $category = $this->baseCategoryRepository->findById($mainProduct->categoryId);

        if (!$category || !$category->hasUpsells()) {
            return [];
        }

        $upsells = [];

        // Konkrétní produkty
        $productIds = $category->getUpsellProducts();
        if (!empty($productIds)) {
            $products = $this->productRepository->findByIds($productIds, $shopId);
            foreach ($products as $upsellProduct) {
                if ($upsellProduct->visible && $upsellProduct->stock > 0) {
                    $upsells[] = [
                        'key' => 'product_' . $upsellProduct->id,
                        'type' => 'simple',
                        'label' => null,
                        'product' => $upsellProduct,
                    ];
                }
            }
        }

        // Vzkaz na kartičce
        if ($category->upsellCardMessage) {
            $upsellProduct = $this->loadSpecialProduct(UpsellConfig::CARD_MESSAGE_PRODUCT_ID, $shopId);
            if ($upsellProduct) {
                $upsells[] = [
                    'key' => 'card_message',
                    'type' => 'text_input',
                    'label' => 'Vzkaz na kartičce',
                    'inputLabel' => 'Text vzkazu',
                    'product' => $upsellProduct,
                ];
            }
        }

        // Vzkaz na stuze
        if ($category->upsellRibbonMessage) {
            $upsellProduct = $this->loadSpecialProduct(UpsellConfig::RIBBON_MESSAGE_PRODUCT_ID, $shopId);
            if ($upsellProduct) {
                $upsells[] = [
                    'key' => 'ribbon_message',
                    'type' => 'text_input',
                    'label' => 'Vzkaz na stuze',
                    'inputLabel' => 'Text vzkazu',
                    'product' => $upsellProduct,
                ];
            }
        }

        // Prémiová stuha
        if ($category->upsellPremiumRibbon) {
            $upsellProduct = $this->loadSpecialProduct(UpsellConfig::PREMIUM_RIBBON_PRODUCT_ID, $shopId);
            if ($upsellProduct) {
                $upsells[] = [
                    'key' => 'premium_ribbon',
                    'type' => 'select',
                    'label' => 'Prémiová stuha',
                    'inputLabel' => 'Barva stuhy',
                    'options' => UpsellConfig::PREMIUM_RIBBON_COLORS,
                    'product' => $upsellProduct,
                ];
            }
        }

        // Foto před odesláním
        if ($category->upsellPhoto) {
            $upsellProduct = $this->loadSpecialProduct(UpsellConfig::PHOTO_PRODUCT_ID, $shopId);
            if ($upsellProduct) {
                $upsells[] = [
                    'key' => 'photo',
                    'type' => 'simple',
                    'label' => 'Foto před odesláním',
                    'product' => $upsellProduct,
                ];
            }
        }

        // Váza
        if ($category->upsellVase) {
            $vase = $this->vaseResolver->resolve($mainProduct, $shopId);
            if ($vase) {
                $upsells[] = [
                    'key' => 'vase',
                    'type' => 'simple',
                    'label' => 'Váza',
                    'product' => $vase,
                ];
            }
        }

        return $upsells;
    }

    /**
     * Load special upsell product by ID.
     * Returns null if ID is 0 (not configured), product not found, or out of stock.
     */
    private function loadSpecialProduct(int $productId, int $shopId): ?Product
    {
        if ($productId === 0) {
            return null;
        }

        $product = $this->productRepository->findById($productId, $shopId);

        if (!$product || !$product->visible || $product->stock <= 0) {
            return null;
        }

        return $product;
    }
}