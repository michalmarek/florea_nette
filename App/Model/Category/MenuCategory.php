<?php declare(strict_types=1);

namespace App\Model\Category;

/**
 * MenuCategory Entity
 *
 * Represents shop-specific menu category from es_menu_categories table.
 * Each shop can have its own menu structure mapped to base categories.
 */
class MenuCategory
{
    public function __construct(
        public readonly int $id,
        public readonly int $shopId,
        public readonly ?int $parentId,
        public readonly int $baseCategoryId,
        public readonly string $url,
        public readonly string $name,
        public readonly string $nameInflected,
        public readonly string $description,
        public readonly string $productsDescription,
        public readonly string $metaTitle,
        public readonly string $metaDescription,
        public readonly string $image,
        public readonly bool $visible,
        public readonly int $position,
    ) {}

    /**
     * Check if category is root (has no parent)
     */
    public function isRoot(): bool
    {
        return $this->parentId === null;
    }
}