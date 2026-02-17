<?php declare(strict_types=1);

namespace App\Model\Product;

use Nette\Database\Explorer;
use Nette\Database\Table\Selection;

/**
 * ProductRepository
 *
 * Handles database access for Product entities.
 * Joins es_zbozi with es_zbozi_text (with shop fallback) and es_zbozi_visibility.
 *
 * Database tables: es_zbozi, es_zbozi_text, es_zbozi_visibility
 */
class ProductRepository
{
    public function __construct(
        private Explorer $database,
    ) {}

    /**
     * Find product by ID for specific shop
     */
    public function findById(int $id, int $shopId): ?Product
    {
        $row = $this->database->table('es_zbozi')
            ->where('id', $id)
            ->where('fl_zobrazovat', '1')
            ->fetch();

        if (!$row) {
            return null;
        }

        // Check shop-specific visibility
        if (!$this->isVisibleInShop($id, $shopId)) {
            return null;
        }

        $text = $this->getTextForShop($id, $shopId);

        return $this->mapToEntity($row, $text, $shopId);
    }

    /**
     * Find product by URL slug for specific shop
     */
    public function findByUrl(string $url, int $shopId): ?Product
    {
        // Find text row with this URL
        $textRow = $this->findTextByUrl($url, $shopId);

        if (!$textRow) {
            return null;
        }

        $row = $this->database->table('es_zbozi')
            ->where('id', $textRow->zbozi)
            ->where('fl_zobrazovat', '1')
            ->fetch();

        if (!$row) {
            return null;
        }

        if (!$this->isVisibleInShop($row->id, $shopId)) {
            return null;
        }

        return $this->mapToEntity($row, $textRow, $shopId);
    }

    /**
     * Find multiple products by IDs
     *
     * @return Product[] Indexed by ID
     */
    public function findByIds(array $ids, int $shopId): array
    {
        if (empty($ids)) {
            return [];
        }

        // Get visible product IDs for this shop
        $visibleIds = $this->filterVisibleIds($ids, $shopId);

        if (empty($visibleIds)) {
            return [];
        }

        $rows = $this->database->table('es_zbozi')
            ->where('id', $visibleIds)
            ->where('fl_zobrazovat', '1')
            ->fetchAll();

        $products = [];
        foreach ($rows as $row) {
            $text = $this->getTextForShop($row->id, $shopId);
            $product = $this->mapToEntity($row, $text, $shopId);
            $products[$product->id] = $product;
        }

        return $products;
    }

    /**
     * Get products for menu category (base category + manual assignment)
     *
     * @param int[] $menuCategoryIds Parent + all descendant menu category IDs
     */
    public function getProductsByMenuCategorySelection(
        int $shopId,
        array $menuCategoryIds,
    ): Selection {
        // Collect base_category_ids from menu categories
        $baseCategoryIds = $this->database->table('es_menu_categories')
            ->select('base_category_id')
            ->where('id', $menuCategoryIds)
            ->fetchPairs(null, 'base_category_id');

        // Collect manually assigned product IDs
        $manualProductIds = $this->database->table('es_menu_category_products')
            ->select('product_id')
            ->where('menu_category_id', $menuCategoryIds)
            ->fetchPairs(null, 'product_id');

        $productIds = [];

        // Products from base categories
        if (!empty($baseCategoryIds)) {
            $categoryProducts = $this->database->table('es_zbozi')
                ->select('id')
                ->where('shop', $shopId)
                ->where('fl_kategorie', $baseCategoryIds)
                ->where('fl_zobrazovat', '1')
                ->fetchPairs(null, 'id');

            $productIds = array_merge($productIds, $categoryProducts);
        }

        // Add manually assigned products
        if (!empty($manualProductIds)) {
            $productIds = array_merge($productIds, $manualProductIds);
        }

        $productIds = array_unique($productIds);

        // Filter by shop visibility
        $productIds = $this->filterVisibleIds($productIds, $shopId);

        if (empty($productIds)) {
            return $this->database->table('es_zbozi')->where('id', null);
        }

        return $this->database->table('es_zbozi')
            ->where('id', $productIds)
            ->where('fl_zobrazovat', '1')
            ->order('nazev ASC');
    }

    /**
     * Filter product IDs by parameter values and price range
     *
     * AND logic between groups, OR logic within item-based group.
     *
     * @param int[] $productIds Starting set of product IDs
     * @param array<int, int[]> $itemFilters group_id => [item_id, ...]
     * @param array<int, array{min: ?float, max: ?float}> $numericFilters group_id => [min, max]
     * @param array{min: ?float, max: ?float}|null $priceFilter
     * @return int[] Filtered product IDs
     */
    public function filterProductIds(
        array $productIds,
        array $itemFilters,
        array $numericFilters,
        ?array $priceFilter,
        bool $stockFilter = false,
    ): array {
        if (empty($productIds)) {
            return [];
        }

        $filtered = $productIds;

        // Item-based filters
        foreach ($itemFilters as $groupId => $itemIds) {
            if (empty($itemIds)) {
                continue;
            }

            $matching = $this->database->table('es_zboziParameters')
                ->where('product_id', $filtered)
                ->where('group_id', $groupId)
                ->where('item_id', $itemIds)
                ->fetchPairs(null, 'product_id');

            $filtered = array_values(array_intersect($filtered, $matching));

            if (empty($filtered)) {
                return [];
            }
        }

        // Numeric filters (freeInteger range)
        foreach ($numericFilters as $groupId => $range) {
            $query = $this->database->table('es_zboziParameters')
                ->where('product_id', $filtered)
                ->where('group_id', $groupId)
                ->where('freeInteger IS NOT NULL');

            if ($range['min'] !== null) {
                $query->where('freeInteger >= ?', (int) $range['min']);
            }
            if ($range['max'] !== null) {
                $query->where('freeInteger <= ?', (int) $range['max']);
            }

            $matching = $query->fetchPairs(null, 'product_id');
            $filtered = array_values(array_intersect($filtered, $matching));

            if (empty($filtered)) {
                return [];
            }
        }

        // Price filter (cenaFlorea * 1.21 approximation)
        if ($priceFilter !== null) {
            $query = $this->database->table('es_zbozi')
                ->select('id')
                ->where('id', $filtered);

            if ($priceFilter['min'] !== null) {
                $query->where('cenaFlorea * 1.21 >= ?', $priceFilter['min']);
            }
            if ($priceFilter['max'] !== null) {
                $query->where('cenaFlorea * 1.21 <= ?', $priceFilter['max']);
            }

            $filtered = array_values($query->fetchPairs(null, 'id'));
        }

        // Stock filter
        if ($stockFilter) {
            $inStock = $this->database->table('es_zbozi')
                ->select('id')
                ->where('id', $filtered)
                ->where('sklad > ?', 0)
                ->fetchPairs(null, 'id');

            $filtered = array_values($inStock);
        }

        return $filtered;
    }

    /**
     * Get Selection for visible products in shop
     */
    public function getVisibleProductsSelection(int $shopId): Selection
    {
        return $this->database->table('es_zbozi')
            ->where('shop', $shopId)
            ->where('fl_zobrazovat', '1')
            ->order('nazev ASC');
    }

    /**
     * Map Selection rows to Product entities
     *
     * @return Product[]
     */
    public function mapRowsToEntities(iterable $rows, int $shopId): array
    {
        $products = [];
        foreach ($rows as $row) {
            $text = $this->getTextForShop($row->id, $shopId);
            $products[] = $this->mapToEntity($row, $text, $shopId);
        }
        return $products;
    }

    // === Private helpers ===

    /**
     * Get text row for product with shop fallback
     *
     * Priority: shop-specific → florea (shop=1) → any available
     */
    private function getTextForShop(int $productId, int $shopId): ?object
    {
        $texts = $this->database->table('es_zbozi_text')
            ->where('zbozi', $productId)
            ->fetchAll();

        if (empty($texts)) {
            return null;
        }

        // Index by shop
        $byShop = [];
        foreach ($texts as $text) {
            $byShop[(int) $text->shop] = $text;
        }

        // Priority: current shop → florea (1) → first available
        return $byShop[$shopId] ?? $byShop[1] ?? reset($texts);
    }

    /**
     * Find text row by URL slug for shop (with fallback)
     */
    private function findTextByUrl(string $url, int $shopId): ?object
    {
        // Try shop-specific first
        $text = $this->database->table('es_zbozi_text')
            ->where('url', $url)
            ->where('shop', $shopId)
            ->fetch();

        if ($text) {
            return $text;
        }

        // Fallback to florea
        return $this->database->table('es_zbozi_text')
            ->where('url', $url)
            ->where('shop', 1)
            ->fetch();
    }

    /**
     * Check if product is visible in specific shop
     */
    private function isVisibleInShop(int $productId, int $shopId): bool
    {
        $visibility = $this->database->table('es_zbozi_visibility')
            ->where('product_id', $productId)
            ->where('shop_id', $shopId)
            ->fetch();

        // No row = visible (default)
        if (!$visibility) {
            return true;
        }

        return (bool) $visibility->visible;
    }

    /**
     * Filter product IDs by shop visibility
     *
     * @param int[] $productIds
     * @return int[]
     */
    private function filterVisibleIds(array $productIds, int $shopId): array
    {
        if (empty($productIds)) {
            return [];
        }

        // Get hidden product IDs for this shop
        $hiddenIds = $this->database->table('es_zbozi_visibility')
            ->where('product_id', $productIds)
            ->where('shop_id', $shopId)
            ->where('visible', 0)
            ->fetchPairs(null, 'product_id');

        return array_values(array_diff($productIds, $hiddenIds));
    }

    /**
     * Map single database row to Product entity (public, for use by services)
     */
    public function mapRowToEntity(object $row, int $shopId): Product
    {
        $text = $this->getTextForShop((int) $row->id, $shopId);
        return $this->mapToEntity($row, $text, $shopId);
    }

    /**
     * Map database row + text to Product entity
     */
    private function mapToEntity(object $row, ?object $text, int $shopId): Product
    {
        return new Product(
            id: (int) $row->id,
            shopId: (int) $row->shop,
            sellerId: (int) $row->seller_id,
            categoryId: (int) $row->fl_kategorie,
            groupCode: $row->groupCode,
            name: $text->nazev ?? $row->nazev,
            stock: (float) $row->sklad,
            visible: $row->fl_zobrazovat === '1',
            url: $text->url ?? '',
            description: $text->popis ?? '',
            metaKeywords: $text->keywords ?? '',
            metaDescription: $text->description ?? '',
            price: (float) $row->cenaFlorea,
            originalPrice: (float) $row->cenaPuvodni,
            vatRate: $text ? (int) $text->fl_dph : 21,
        );
    }
}