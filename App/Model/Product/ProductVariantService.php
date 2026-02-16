<?php declare(strict_types=1);

namespace App\Model\Product;

use Nette\Database\Explorer;
use App\Model\Category\BaseCategoryRepository;
use App\Model\Parameter\ParameterGroup;
use App\Model\Parameter\ParameterGroupRepository;

/**
 * ProductVariantService
 *
 * Handles product variant logic based on groupCode and variant parameter groups.
 */
class ProductVariantService
{
    public function __construct(
        private Explorer $database,
        private ProductRepository $productRepository,
        private BaseCategoryRepository $baseCategoryRepository,
        private ParameterGroupRepository $parameterGroupRepository,
    ) {}

    /**
     * Get variant parameter group for product
     */
    public function getVariantParameterGroup(Product $product): ?ParameterGroup
    {
        $category = $this->baseCategoryRepository->findById($product->categoryId);

        if (!$category || !$category->variantParameterGroupId) {
            return null;
        }

        return $this->parameterGroupRepository->findById($category->variantParameterGroupId);
    }

    /**
     * Get all variants of a product
     *
     * @return array Array of ['product' => Product, 'value' => mixed, 'label' => string]
     */
    public function getVariants(Product $product, int $shopId): array
    {
        if (!$product->groupCode) {
            return [];
        }

        $variantGroup = $this->getVariantParameterGroup($product);
        if (!$variantGroup) {
            return [];
        }

        // Find all products with same groupCode
        $rows = $this->database->table('es_zbozi')
            ->where('groupCode', $product->groupCode)
            ->where('fl_zobrazovat', '1')
            ->where('sklad > ?', 0)
            ->fetchAll();

        $variants = [];
        foreach ($rows as $row) {
            $variantProduct = $this->productRepository->mapRowToEntity($row, $shopId);
            $value = $this->getParameterValue($variantProduct->id, $variantGroup->id);

            if ($value !== null) {
                $variants[] = [
                    'product' => $variantProduct,
                    'value' => $value,
                    'label' => $variantGroup->getLabel($value),
                ];
            }
        }

        usort($variants, fn($a, $b) => $a['value'] <=> $b['value']);

        return $variants;
    }

    /**
     * Get parameter value for product
     */
    private function getParameterValue(int $productId, int $groupId): mixed
    {
        $param = $this->database->table('es_zboziParameters')
            ->where('product_id', $productId)
            ->where('group_id', $groupId)
            ->fetch();

        if (!$param) {
            return null;
        }

        // Item-based (číselník)
        if ($param->item_id) {
            $item = $this->database->table('es_parameterItems')
                ->where('id', $param->item_id)
                ->fetch();
            return $item ? $item->value : null;
        }

        if ($param->freeText) {
            return $param->freeText;
        }

        if ($param->freeInteger !== null) {
            return (float) $param->freeInteger;
        }

        return null;
    }
}