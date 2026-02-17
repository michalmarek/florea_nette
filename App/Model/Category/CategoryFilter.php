<?php declare(strict_types=1);

namespace App\Model\Category;

/**
 * CategoryFilter DTO
 *
 * Represents a single filter available in category listing.
 *
 * Three types:
 * - Item-based (checkboxes): user picks from predefined values
 * - Numeric (range input): user sets min/max for freeInteger params
 * - Price (range input): special case, always available
 */
class CategoryFilter
{
    public const TYPE_ITEM = 'item';
    public const TYPE_NUMERIC = 'numeric';
    public const TYPE_PRICE = 'price';
    public const TYPE_STOCK = 'stock';

    public function __construct(
        public readonly string $key,
        public readonly string $name,
        public readonly string $type,
        public readonly ?string $units,
        public readonly int $sort,

        /** @var array{id: int, value: string, count: int}[] */
        public readonly array $items = [],

        public readonly ?float $min = null,
        public readonly ?float $max = null,

        /** @var int[] */
        public readonly array $activeItems = [],
        public readonly ?float $activeMin = null,
        public readonly ?float $activeMax = null,
    ) {}

    public function isActive(): bool
    {
        return match ($this->type) {
            self::TYPE_ITEM => !empty($this->activeItems),
            self::TYPE_NUMERIC, self::TYPE_PRICE => $this->activeMin !== null || $this->activeMax !== null,
            self::TYPE_STOCK => !empty($this->activeItems),
        };
    }
    public function isItemType(): bool
    {
        return $this->type === self::TYPE_ITEM;
    }

    public function isRangeType(): bool
    {
        return $this->type === self::TYPE_NUMERIC || $this->type === self::TYPE_PRICE;
    }

    public function isToggleType(): bool
    {
        return $this->type === self::TYPE_STOCK;
    }
}