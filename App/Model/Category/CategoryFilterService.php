<?php declare(strict_types=1);

namespace App\Model\Category;

use Nette\Database\Explorer;
use App\Model\Parameter\ParameterGroup;
use App\Model\Parameter\ParameterGroupRepository;

/**
 * CategoryFilterService
 *
 * Builds available filters for category product listing.
 * Filter config comes from BaseCategory.parameterGroups JSON (filter: true entries).
 * Price filter is always available.
 */
class CategoryFilterService
{
    public function __construct(
        private Explorer $database,
        private BaseCategoryRepository $baseCategoryRepository,
        private ParameterGroupRepository $parameterGroupRepository,
    ) {}

    /**
     * Get available filters for category listing
     *
     * @param int $baseCategoryId Base category of the current menu category
     * @param int[] $productIds All product IDs in listing (before filtering)
     * @param array<string, string> $activeParams URL filter params (f5 => '10,20', price => '100-500')
     * @return CategoryFilter[]
     */
    public function getAvailableFilters(
        int $baseCategoryId,
        array $productIds,
        array $activeParams,
    ): array {
        if (empty($productIds)) {
            return [];
        }

        $filters = $this->buildParameterFilters($baseCategoryId, $productIds, $activeParams);

        // Stock filter (always available)
        $filters[] = $this->buildStockFilter($productIds, $activeParams);

        // Price filter (always available)
        $priceFilter = $this->buildPriceFilter($productIds, $activeParams);
        if ($priceFilter !== null) {
            $filters[] = $priceFilter;
        }

        return $filters;
    }

    /**
     * Parse active filters from available filter objects
     *
     * @return array{itemFilters: array<int, int[]>, numericFilters: array<int, array{min: ?float, max: ?float}>, priceFilter: ?array{min: ?float, max: ?float}}
     */
    public function parseActiveFilters(array $availableFilters): array
    {
        $itemFilters = [];
        $numericItemFilters = [];
        $numericFilters = [];
        $priceFilter = null;
        $stockFilter = false;

        foreach ($availableFilters as $filter) {
            if (!$filter->isActive()) {
                continue;
            }

            $groupId = $this->extractGroupId($filter->key);

            if ($filter->type === CategoryFilter::TYPE_ITEM && $groupId !== null) {
                if ($this->isNumericCheckboxFilter($groupId)) {
                    $numericItemFilters[$groupId] = $filter->activeItems;
                } else {
                    $itemFilters[$groupId] = $filter->activeItems;
                }
            } elseif ($filter->type === CategoryFilter::TYPE_NUMERIC && $groupId !== null) {
                $numericFilters[$groupId] = [
                    'min' => $filter->activeMin,
                    'max' => $filter->activeMax,
                ];
            } elseif ($filter->type === CategoryFilter::TYPE_PRICE) {
                $priceFilter = [
                    'min' => $filter->activeMin,
                    'max' => $filter->activeMax,
                ];
            } elseif ($filter->type === CategoryFilter::TYPE_STOCK) {
                // Active means "show all" (stock=0), default is stock-only filtering
                $stockFilter = !in_array(0, $filter->activeItems);
            }
        }

        return [
            'itemFilters' => $itemFilters,
            'numericItemFilters' => $numericItemFilters,
            'numericFilters' => $numericFilters,
            'priceFilter' => $priceFilter,
            'stockFilter' => $stockFilter ?? false,
        ];
    }

    // === Private builders ===

    private function buildParameterFilters(
        int $baseCategoryId,
        array $productIds,
        array $activeParams,
    ): array {
        $category = $this->baseCategoryRepository->findById($baseCategoryId);
        if (!$category || !$category->hasParameterGroups()) {
            return [];
        }

        $config = $category->getParameterGroups();
        $filterableConfig = [];
        foreach ($config as $entry) {
            if (!empty($entry['filter'])) {
                $filterableConfig[$entry['id']] = $entry;
            }
        }

        if (empty($filterableConfig)) {
            return [];
        }

        $groups = $this->parameterGroupRepository->findByIds(array_keys($filterableConfig));

        $filters = [];
        foreach ($filterableConfig as $groupId => $conf) {
            if (!isset($groups[$groupId])) {
                continue;
            }

            $group = $groups[$groupId];
            $sort = (int) ($conf['sort'] ?? 0);
            $key = 'f' . $groupId;
            $activeValue = $activeParams[$key] ?? null;

            $display = $conf['display'] ?? null;

            if ($group->isItemBased()) {
                $filter = $this->buildItemFilter($group, $key, $sort, $productIds, $activeValue);
            } elseif ($group->isNumeric() && $display === 'checkbox') {
                $filter = $this->buildNumericCheckboxFilter($group, $key, $sort, $productIds, $activeValue);
            } elseif ($group->isNumeric()) {
                $filter = $this->buildNumericFilter($group, $key, $sort, $productIds, $activeValue);
            } else {
                continue;
            }

            if ($filter !== null) {
                $filters[] = $filter;
            }
        }

        usort($filters, fn(CategoryFilter $a, CategoryFilter $b) => $a->sort <=> $b->sort);

        return $filters;
    }

    private function buildItemFilter(
        ParameterGroup $group,
        string $key,
        int $sort,
        array $productIds,
        ?string $activeValue,
    ): ?CategoryFilter {
        $rows = $this->database->table('es_zboziParameters')
            ->select('item_id, COUNT(DISTINCT product_id) AS cnt')
            ->where('product_id', $productIds)
            ->where('group_id', $group->id)
            ->where('item_id IS NOT NULL')
            ->group('item_id')
            ->fetchAll();

        if (empty($rows)) {
            return null;
        }

        $itemIds = array_map(fn($r) => (int) $r->item_id, $rows);
        $itemNames = $this->database->table('es_parameterItems')
            ->where('id', $itemIds)
            ->fetchPairs('id', 'value');

        $countByItem = [];
        foreach ($rows as $row) {
            $countByItem[(int) $row->item_id] = (int) $row->cnt;
        }

        $items = [];
        foreach ($itemIds as $itemId) {
            if (!isset($itemNames[$itemId])) {
                continue;
            }
            $items[] = [
                'id' => $itemId,
                'value' => $itemNames[$itemId],
                'count' => $countByItem[$itemId] ?? 0,
            ];
        }

        usort($items, fn($a, $b) => strcmp($a['value'], $b['value']));

        $activeItems = [];
        if ($activeValue !== null && $activeValue !== '') {
            $activeItems = array_map('intval', explode(',', $activeValue));
        }

        return new CategoryFilter(
            key: $key,
            name: $group->name,
            type: CategoryFilter::TYPE_ITEM,
            units: null,
            sort: $sort,
            items: $items,
            activeItems: $activeItems,
        );
    }

    /**
     * Build checkbox filter from freeInteger values
     *
     * Displays distinct numeric values as checkboxes instead of range.
     */
    private function buildNumericCheckboxFilter(
        ParameterGroup $group,
        string $key,
        int $sort,
        array $productIds,
        ?string $activeValue,
    ): ?CategoryFilter {
        $rows = $this->database->table('es_zboziParameters')
            ->select('freeInteger, COUNT(DISTINCT product_id) AS cnt')
            ->where('product_id', $productIds)
            ->where('group_id', $group->id)
            ->where('freeInteger IS NOT NULL')
            ->group('freeInteger')
            ->order('freeInteger ASC')
            ->fetchAll();

        if (empty($rows)) {
            return null;
        }

        $items = [];
        foreach ($rows as $row) {
            $items[] = [
                'id' => (int) $row->freeInteger,
                'value' => $group->getLabel($row->freeInteger),
                'count' => (int) $row->cnt,
            ];
        }

        $activeItems = [];
        if ($activeValue !== null && $activeValue !== '') {
            $activeItems = array_map('intval', explode(',', $activeValue));
        }

        return new CategoryFilter(
            key: $key,
            name: $group->name,
            type: CategoryFilter::TYPE_ITEM,
            units: null,
            sort: $sort,
            items: $items,
            activeItems: $activeItems,
        );
    }

    private function buildNumericFilter(
        ParameterGroup $group,
        string $key,
        int $sort,
        array $productIds,
        ?string $activeValue,
    ): ?CategoryFilter {
        $row = $this->database->table('es_zboziParameters')
            ->select('MIN(freeInteger) AS min_val, MAX(freeInteger) AS max_val')
            ->where('product_id', $productIds)
            ->where('group_id', $group->id)
            ->where('freeInteger IS NOT NULL')
            ->fetch();

        if (!$row || $row->min_val === null) {
            return null;
        }

        $min = (float) $row->min_val;
        $max = (float) $row->max_val;

        if ($min === $max) {
            return null;
        }

        [$activeMin, $activeMax] = $this->parseRangeValue($activeValue);

        return new CategoryFilter(
            key: $key,
            name: $group->name,
            type: CategoryFilter::TYPE_NUMERIC,
            units: $group->units,
            sort: $sort,
            min: $min,
            max: $max,
            activeMin: $activeMin,
            activeMax: $activeMax,
        );
    }

    private function buildPriceFilter(array $productIds, array $activeParams): ?CategoryFilter
    {
        $row = $this->database->table('es_zbozi')
            ->select('MIN(cenaFlorea * 1.21) AS min_price, MAX(cenaFlorea * 1.21) AS max_price')
            ->where('id', $productIds)
            ->fetch();

        if (!$row || $row->min_price === null) {
            return null;
        }

        $min = floor((float) $row->min_price);
        $max = ceil((float) $row->max_price);

        [$activeMin, $activeMax] = $this->parseRangeValue($activeParams['price'] ?? null);

        return new CategoryFilter(
            key: 'price',
            name: 'Cena',
            type: CategoryFilter::TYPE_PRICE,
            units: 'Kč',
            sort: PHP_INT_MAX,
            min: $min,
            max: $max,
            activeMin: $activeMin,
            activeMax: $activeMax,
        );
    }

    private function buildStockFilter(array $productIds, array $activeParams): CategoryFilter
    {
        $inStockCount = $this->database->table('es_zbozi')
            ->where('id', $productIds)
            ->where('sklad > ?', 0)
            ->count('*');

        $allCount = count($productIds);

        $activeValue = $activeParams['stock'] ?? null;
        // Default (no param) = stock only; stock=0 = show all
        $showAll = $activeValue === '0';

        return new CategoryFilter(
            key: 'stock',
            name: 'Dostupnost',
            type: CategoryFilter::TYPE_STOCK,
            units: null,
            sort: PHP_INT_MAX - 1, // Before price, after parameter filters
            items: [
                ['id' => 1, 'value' => 'Pouze skladem', 'count' => $inStockCount],
                ['id' => 0, 'value' => 'Vše', 'count' => $allCount],
            ],
            activeItems: $showAll ? [0] : [1],
        );
    }

    // === Helpers ===

    /**
     * Parse range value: '40-80' => [40.0, 80.0], '40-' => [40.0, null]
     *
     * @return array{?float, ?float}
     */
    private function parseRangeValue(?string $value): array
    {
        if ($value === null || $value === '') {
            return [null, null];
        }

        $parts = explode('-', $value, 2);

        if (count($parts) !== 2) {
            return [null, null];
        }

        $min = $parts[0] !== '' ? (float) $parts[0] : null;
        $max = $parts[1] !== '' ? (float) $parts[1] : null;

        return [$min, $max];
    }

    /**
     * Extract group_id from filter key: 'f5' => 5, 'price' => null
     */
    private function extractGroupId(string $key): ?int
    {
        if (preg_match('/^f(\d+)$/', $key, $m)) {
            return (int) $m[1];
        }
        return null;
    }

    private function isNumericCheckboxFilter(int $groupId): bool
    {
        $group = $this->parameterGroupRepository->findById($groupId);
        return $group !== null && $group->isNumeric();
    }
}