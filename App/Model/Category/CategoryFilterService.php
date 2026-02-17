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
     * Get available filters with dynamic counts
     *
     * Counts for each filter are calculated from products filtered
     * by all OTHER active filters (not the current one).
     * This ensures users always see relevant options.
     *
     * @param int $baseCategoryId Base category of the current menu category
     * @param int[] $allProductIds All product IDs in listing (before any filtering)
     * @param array<string, string> $activeParams URL filter params
     * @return CategoryFilter[]
     */
    public function getAvailableFilters(
        int $baseCategoryId,
        array $allProductIds,
        array $activeParams,
    ): array {
        if (empty($allProductIds)) {
            return [];
        }

        // First pass: determine which filter groups exist and their config
        $filterConfigs = $this->getFilterConfigs($baseCategoryId);

        // Build per-filter product sets (filtered by everything EXCEPT that filter)
        $perFilterProductIds = $this->buildPerFilterProductIds(
            $allProductIds,
            $filterConfigs,
            $activeParams,
        );

        // Second pass: build filter objects with dynamic counts
        $filters = [];

        foreach ($filterConfigs as $config) {
            $key = $config['key'];
            $productIds = $perFilterProductIds[$key] ?? $allProductIds;
            $activeValue = $activeParams[$key] ?? null;

            $filter = match ($config['builder']) {
                'item' => $this->buildItemFilter($config['group'], $key, $config['sort'], $productIds, $activeValue),
                'numericCheckbox' => $this->buildNumericCheckboxFilter($config['group'], $key, $config['sort'], $productIds, $activeValue),
                'numeric' => $this->buildNumericFilter($config['group'], $key, $config['sort'], $productIds, $activeValue),
                'stock' => $this->buildStockFilter($productIds, $activeParams),
                'price' => $this->buildPriceFilter($productIds, $activeParams),
                default => null,
            };

            if ($filter !== null) {
                $filters[] = $filter;
            }
        }

        return $filters;
    }

    /**
     * Get filter configuration entries (what filters exist, their type and config)
     *
     * @return array[] Each entry: [key, builder, sort, group?, display?]
     */
    private function getFilterConfigs(int $baseCategoryId): array
    {
        $configs = [];

        // Parameter-based filters
        $category = $this->baseCategoryRepository->findById($baseCategoryId);
        if ($category && $category->hasParameterGroups()) {
            $paramConfig = $category->getParameterGroups();
            $filterableConfig = [];
            foreach ($paramConfig as $entry) {
                if (!empty($entry['filter'])) {
                    $filterableConfig[$entry['id']] = $entry;
                }
            }

            if (!empty($filterableConfig)) {
                $groups = $this->parameterGroupRepository->findByIds(array_keys($filterableConfig));

                foreach ($filterableConfig as $groupId => $conf) {
                    if (!isset($groups[$groupId])) {
                        continue;
                    }

                    $group = $groups[$groupId];
                    $display = $conf['display'] ?? null;
                    $sort = (int) ($conf['sort'] ?? 0);
                    $key = 'f' . $groupId;

                    if ($group->isItemBased()) {
                        $builder = 'item';
                    } elseif ($group->isNumeric() && $display === 'checkbox') {
                        $builder = 'numericCheckbox';
                    } elseif ($group->isNumeric()) {
                        $builder = 'numeric';
                    } else {
                        continue;
                    }

                    $configs[] = [
                        'key' => $key,
                        'builder' => $builder,
                        'sort' => $sort,
                        'group' => $group,
                    ];
                }

                usort($configs, fn($a, $b) => $a['sort'] <=> $b['sort']);
            }
        }

        // Stock filter
        $configs[] = [
            'key' => 'stock',
            'builder' => 'stock',
            'sort' => PHP_INT_MAX - 1,
            'group' => null,
        ];

        // Price filter
        $configs[] = [
            'key' => 'price',
            'builder' => 'price',
            'sort' => PHP_INT_MAX,
            'group' => null,
        ];

        return $configs;
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

    /**
     * Parse active URL params into structured filter data
     *
     * @return array<string, array> key => [type, values/range]
     */
    private function parseActiveParamsRaw(array $activeParams, array $filterConfigs): array
    {
        $active = [];

        // Index configs by key for lookup
        $configByKey = [];
        foreach ($filterConfigs as $config) {
            $configByKey[$config['key']] = $config;
        }

        foreach ($activeParams as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            if ($key === 'stock') {
                if ($value === '0') {
                    // stock=0 means show all, no filtering
                    continue;
                }
                $active[$key] = ['type' => 'stock'];
                continue;
            }

            if ($key === 'price') {
                [$min, $max] = $this->parseRangeValue($value);
                if ($min !== null || $max !== null) {
                    $active[$key] = ['type' => 'price', 'min' => $min, 'max' => $max];
                }
                continue;
            }

            if (!isset($configByKey[$key])) {
                continue;
            }

            $config = $configByKey[$key];

            if ($config['builder'] === 'item' || $config['builder'] === 'numericCheckbox') {
                $ids = array_map('intval', explode(',', $value));
                $groupId = $this->extractGroupId($key);
                $active[$key] = [
                    'type' => $config['builder'],
                    'groupId' => $groupId,
                    'values' => $ids,
                ];
            } elseif ($config['builder'] === 'numeric') {
                [$min, $max] = $this->parseRangeValue($value);
                $groupId = $this->extractGroupId($key);
                if ($min !== null || $max !== null) {
                    $active[$key] = [
                        'type' => 'numeric',
                        'groupId' => $groupId,
                        'min' => $min,
                        'max' => $max,
                    ];
                }
            }
        }

        // Default stock filter (no param = filter by stock)
        if (!isset($activeParams['stock']) || $activeParams['stock'] !== '0') {
            $active['stock'] = ['type' => 'stock'];
        }

        return $active;
    }

    // === Private builders ===

    /**
     * Build per-filter product ID sets for dynamic counts
     *
     * For each filter, applies all OTHER active filters to get
     * the product set used for counting that filter's values.
     *
     * @return array<string, int[]> key => filtered product IDs
     */
    private function buildPerFilterProductIds(
        array $allProductIds,
        array $filterConfigs,
        array $activeParams,
    ): array {

        // Parse all active filter values (includes default stock filter)
        $activeFilters = $this->parseActiveParamsRaw($activeParams, $filterConfigs);

        if (empty($activeFilters)) {
            return [];
        }

        $result = [];

        foreach ($filterConfigs as $config) {
            $key = $config['key'];

            // Build filter set excluding current filter
            $filtered = $allProductIds;

            foreach ($activeFilters as $filterKey => $filterData) {
                if ($filterKey === $key) {
                    continue; // Skip current filter
                }

                $filtered = $this->applyOneFilter($filtered, $filterKey, $filterData);

                if (empty($filtered)) {
                    break;
                }
            }

            $result[$key] = $filtered;
        }

        return $result;
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

    /**
     * Apply a single filter to product IDs
     *
     * @param int[] $productIds
     * @return int[]
     */
    private function applyOneFilter(array $productIds, string $key, array $filterData): array
    {
        if (empty($productIds)) {
            return [];
        }

        switch ($filterData['type']) {
            case 'item':
                $matching = $this->database->table('es_zboziParameters')
                    ->where('product_id', $productIds)
                    ->where('group_id', $filterData['groupId'])
                    ->where('item_id', $filterData['values'])
                    ->fetchPairs(null, 'product_id');
                return array_values(array_intersect($productIds, $matching));

            case 'numericCheckbox':
                $matching = $this->database->table('es_zboziParameters')
                    ->where('product_id', $productIds)
                    ->where('group_id', $filterData['groupId'])
                    ->where('freeInteger', $filterData['values'])
                    ->fetchPairs(null, 'product_id');
                return array_values(array_intersect($productIds, $matching));

            case 'numeric':
                $query = $this->database->table('es_zboziParameters')
                    ->where('product_id', $productIds)
                    ->where('group_id', $filterData['groupId'])
                    ->where('freeInteger IS NOT NULL');
                if ($filterData['min'] !== null) {
                    $query->where('freeInteger >= ?', (int) $filterData['min']);
                }
                if ($filterData['max'] !== null) {
                    $query->where('freeInteger <= ?', (int) $filterData['max']);
                }
                $matching = $query->fetchPairs(null, 'product_id');
                return array_values(array_intersect($productIds, $matching));

            case 'price':
                $query = $this->database->table('es_zbozi')
                    ->select('id')
                    ->where('id', $productIds);
                if ($filterData['min'] !== null) {
                    $query->where('cenaFlorea * 1.21 >= ?', $filterData['min']);
                }
                if ($filterData['max'] !== null) {
                    $query->where('cenaFlorea * 1.21 <= ?', $filterData['max']);
                }
                return array_values($query->fetchPairs(null, 'id'));

            case 'stock':
                return array_values(
                    $this->database->table('es_zbozi')
                        ->select('id')
                        ->where('id', $productIds)
                        ->where('sklad > ?', 0)
                        ->fetchPairs(null, 'id')
                );

            default:
                return $productIds;
        }
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