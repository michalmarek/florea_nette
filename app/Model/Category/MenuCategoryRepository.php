<?php declare(strict_types=1);

namespace App\Model\Category;

use Nette\Database\Explorer;
use Nette\Database\Table\Selection;
use Nette\Caching\Cache;
use Nette\Caching\Storage;

/**
 * MenuCategoryRepository
 *
 * Handles database access for MenuCategory entities.
 * Shop-specific menu structure with hierarchical organization.
 *
 * Database table: es_menu_categories
 */
class MenuCategoryRepository
{
    private Cache $cache;

    public function __construct(
        private Explorer $database,
        Storage $cacheStorage,
    ) {
        $this->cache = new Cache($cacheStorage, 'MenuCategory');
    }

    /**
     * Find menu category by ID
     */
    public function findById(int $id): ?MenuCategory
    {
        $row = $this->database->table('es_menu_categories')->get($id);

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Find menu category by URL slug for specific shop
     */
    public function findByUrl(int $shopId, string $url): ?MenuCategory
    {
        $row = $this->database->table('es_menu_categories')
            ->where('shop_id', $shopId)
            ->where('url', $url)
            ->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Find menu category by base category ID for specific shop
     */
    public function findByBaseCategoryId(int $baseCategoryId, int $shopId): ?MenuCategory
    {
        $row = $this->database->table('es_menu_categories')
            ->where('shop_id', $shopId)
            ->where('base_category_id', $baseCategoryId)
            ->where('visible', 1)
            ->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Get Selection for all visible menu categories in shop
     */
    public function getVisibleCategoriesSelection(int $shopId): Selection
    {
        return $this->database->table('es_menu_categories')
            ->where('shop_id', $shopId)
            ->where('visible', 1)
            ->order('position ASC');
    }

    /**
     * Get Selection for root menu categories (top-level menu items)
     */
    public function getRootCategoriesSelection(int $shopId): Selection
    {
        return $this->database->table('es_menu_categories')
            ->where('shop_id', $shopId)
            ->where('parent_id IS NULL OR parent_id = ?', 0)
            ->where('visible', 1)
            ->order('position ASC');
    }

    /**
     * Get Selection for child categories of given parent
     */
    public function getChildrenSelection(int $parentId): Selection
    {
        return $this->database->table('es_menu_categories')
            ->where('parent_id', $parentId)
            ->where('visible', 1)
            ->order('position ASC');
    }

    /**
     * Get all descendant category IDs (single query + in-memory traversal)
     *
     * Loads all visible categories for the shop once,
     * then walks the tree in PHP. Eliminates N+1 recursive queries.
     *
     * @return int[] Array of menu category IDs including the parent
     */
    public function getAllDescendantIds(int $menuCategoryId, int $shopId): array
    {
        $allCategories = $this->database->table('es_menu_categories')
            ->where('shop_id', $shopId)
            ->where('visible', 1)
            ->fetchPairs('id', 'parent_id');

        // Build children lookup: parentId => [childId, childId, ...]
        $childrenMap = [];
        foreach ($allCategories as $id => $parentId) {
            $childrenMap[$parentId][] = $id;
        }

        // Iterative tree walk (no recursion needed)
        $ids = [$menuCategoryId];
        $queue = [$menuCategoryId];

        while ($queue) {
            $current = array_shift($queue);
            if (isset($childrenMap[$current])) {
                foreach ($childrenMap[$current] as $childId) {
                    $ids[] = $childId;
                    $queue[] = $childId;
                }
            }
        }

        return $ids;
    }

    /**
     * Get complete category tree for shop as nested array
     *
     * Single query, in-memory tree build.
     *
     * @return array Nested array with 'category' (MenuCategory) and 'children' keys
     */
    public function getCategoryTreeForShop(int $shopId): array
    {
        $selection = $this->getVisibleCategoriesSelection($shopId);
        $categories = $this->mapRowsToEntities($selection);

        // Build lookup by ID
        $lookup = [];
        foreach ($categories as $category) {
            $lookup[$category->id] = [
                'category' => $category,
                'children' => [],
            ];
        }

        // Build tree
        $tree = [];
        foreach ($categories as $category) {
            if ($category->isRoot()) {
                $tree[] = &$lookup[$category->id];
            } elseif (isset($lookup[$category->parentId])) {
                $lookup[$category->parentId]['children'][] = &$lookup[$category->id];
            }
        }

        return $tree;
    }

    /**
     * Build breadcrumbs from category to root (single query)
     *
     * Loads all shop categories once, walks up the tree in PHP.
     *
     * @return MenuCategory[] Ordered from root to current
     */
    public function getBreadcrumbs(MenuCategory $category, int $shopId): array
    {
        // Load all categories for shop indexed by ID
        $selection = $this->getVisibleCategoriesSelection($shopId);
        $allCategories = [];
        foreach ($selection as $row) {
            $allCategories[(int) $row->id] = $this->mapToEntity($row);
        }

        // Walk up from current to root
        $breadcrumbs = [];
        $current = $category;

        while ($current !== null) {
            array_unshift($breadcrumbs, $current);

            if ($current->parentId !== null && isset($allCategories[$current->parentId])) {
                $current = $allCategories[$current->parentId];
            } else {
                $current = null;
            }
        }

        return $breadcrumbs;
    }

    /**
     * Get menu tree for shop (max 2 levels, cached)
     *
     * @return array [{category: MenuCategory, children: [{category: MenuCategory}]}]
     */
    public function getMenuTree(int $shopId): array
    {
        return $this->cache->load("menu_tree_$shopId", function () use ($shopId) {
            return $this->buildMenuTree($shopId);
        });
    }

    /**
     * Build 2-level menu tree from database
     */
    private function buildMenuTree(int $shopId): array
    {
        $selection = $this->getVisibleCategoriesSelection($shopId);
        $categories = $this->mapRowsToEntities($selection);

        $roots = [];
        $childrenByParent = [];

        foreach ($categories as $category) {
            if ($category->isRoot()) {
                $roots[] = $category;
            } elseif ($category->parentId !== null) {
                $childrenByParent[$category->parentId][] = $category;
            }
        }

        $tree = [];
        foreach ($roots as $root) {
            $tree[] = [
                'category' => $root,
                'children' => array_map(
                    fn($child) => ['category' => $child],
                    $childrenByParent[$root->id] ?? [],
                ),
            ];
        }

        return $tree;
    }

    /**
     * Invalidate menu cache for shop (call from admin after changes)
     */
    public function invalidateMenuCache(int $shopId): void
    {
        $this->cache->remove("menu_tree_$shopId");
    }

    /**
     * Map database rows to MenuCategory entities
     *
     * @return MenuCategory[]
     */
    public function mapRowsToEntities(iterable $rows): array
    {
        $categories = [];
        foreach ($rows as $row) {
            $categories[] = $this->mapToEntity($row);
        }
        return $categories;
    }

    /**
     * Map database row to MenuCategory entity
     */
    private function mapToEntity(object $row): MenuCategory
    {
        return new MenuCategory(
            id: (int) $row->id,
            shopId: (int) $row->shop_id,
            parentId: $row->parent_id > 0 ? (int) $row->parent_id : null,
            baseCategoryId: (int) $row->base_category_id,
            url: $row->url ?? '',
            name: $row->name ?? '',
            nameInflected: $row->name_inflected ?? '',
            description: $row->description ?? '',
            productsDescription: $row->products_description ?? '',
            metaTitle: $row->meta_title ?? '',
            metaDescription: $row->meta_description ?? '',
            image: $row->image ?? '',
            visible: (bool) $row->visible,
            position: (int) $row->position,
        );
    }
}