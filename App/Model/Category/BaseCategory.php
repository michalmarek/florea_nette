<?php declare(strict_types=1);

namespace App\Model\Category;

/**
 * BaseCategory Entity
 *
 * Represents internal product categorization from fl_kategorie table.
 * Used for internal product classification, feeds, and parameter groups.
 *
 * Uses hybrid approach:
 * - Public readonly: Simple data passthrough
 * - Private readonly + getter: Data with business logic
 */
class BaseCategory
{
    public function __construct(
        public readonly int $id,
        public readonly ?int $parentId,
        public readonly ?int $variantParameterGroupId,
        public readonly string $photo,
        public readonly string $heurekaFeed,
        public readonly string $zboziFeed,
        public readonly string $googleFeed,
        public readonly bool $visible,
        public readonly int $position,

        private readonly ?string $parameterGroups,
        private readonly ?string $upsellProducts,
        public readonly bool $upsellCardMessage,
        public readonly bool $upsellRibbonMessage,
        public readonly bool $upsellPremiumRibbon,
        public readonly bool $upsellPhoto,
        public readonly bool $upsellVase,
    ) {}

    /**
     * Get parameter groups as decoded array
     */
    public function getParameterGroups(): array
    {
        if ($this->parameterGroups === null || $this->parameterGroups === '') {
            return [];
        }

        $decoded = json_decode($this->parameterGroups, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Check if category is root (has no parent)
     */
    public function isRoot(): bool
    {
        return $this->parentId === null;
    }

    /**
     * Check if category has parameter groups configured
     */
    public function hasParameterGroups(): bool
    {
        return !empty($this->getParameterGroups());
    }

    /**
     * Get upsell product IDs as array
     */
    public function getUpsellProducts(): array
    {
        if ($this->upsellProducts === null || $this->upsellProducts === '') {
            return [];
        }

        $decoded = json_decode($this->upsellProducts, true);

        return is_array($decoded) ? array_map('intval', $decoded) : [];
    }

    /**
     * Check if category has any upsell configuration
     */
    public function hasUpsells(): bool
    {
        return !empty($this->getUpsellProducts())
            || $this->upsellCardMessage
            || $this->upsellRibbonMessage
            || $this->upsellPremiumRibbon
            || $this->upsellPhoto
            || $this->upsellVase;
    }
}