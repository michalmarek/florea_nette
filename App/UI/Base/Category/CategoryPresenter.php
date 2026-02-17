<?php declare(strict_types=1);

namespace App\UI\Base\Category;

use App\UI\Base\BasePresenter;
use App\Model\Category\MenuCategoryRepository;
use App\Model\Category\CategoryFilterService;
use App\Model\Product\ProductRepository;

/**
 * CategoryPresenter
 *
 * Displays menu category with filterable product listing.
 *
 * Filter URL format (with JS):  /produkty/kytice?f5=10,20&f8=40-80&price=100-500&p=2
 * Filter URL format (no JS):    /produkty/kytice?f5[]=10&f5[]=20&f8_min=40&f8_max=80&price_min=100&price_max=500&p=2
 * Both formats are accepted and produce identical results.
 */
class CategoryPresenter extends BasePresenter
{
    private MenuCategoryRepository $menuCategoryRepository;
    private ProductRepository $productRepository;
    private CategoryFilterService $categoryFilterService;

    public function injectCategoryDependencies(
        MenuCategoryRepository $menuCategoryRepository,
        ProductRepository $productRepository,
        CategoryFilterService $categoryFilterService,
    ): void {
        $this->menuCategoryRepository = $menuCategoryRepository;
        $this->productRepository = $productRepository;
        $this->categoryFilterService = $categoryFilterService;
    }

    public function actionDefault(string $slug, int $p = 1): void
    {
        $shopId = $this->shopContext->getId();

        // Find category by URL slug
        $category = $this->menuCategoryRepository->findByUrl($shopId, $slug);

        if (!$category) {
            $this->error('Kategorie nebyla nalezena');
        }

        // Direct child categories
        $childrenSelection = $this->menuCategoryRepository->getChildrenSelection($category->id);
        $childCategories = $this->menuCategoryRepository->mapRowsToEntities($childrenSelection);

        // Products (funnel: all descendants + manual assignments)
        $menuCategoryIds = $this->menuCategoryRepository->getAllDescendantIds($category->id, $shopId);

        // All product IDs before filtering (for filter counts)
        $allProductIds = $this->productRepository->getProductIdsByMenuCategory($shopId, $menuCategoryIds);

        // Parse filter params from URL (handles both JS and no-JS format)
        $activeParams = $this->getFilterParams();

        // Build available filters with counts
        $filters = $this->categoryFilterService->getAvailableFilters(
            $category->baseCategoryId,
            $allProductIds,
            $activeParams,
        );

        // Apply active filters
        $filteredProductIds = $allProductIds;

        // Apply filters (stock filter runs always as default)
        $parsed = $this->categoryFilterService->parseActiveFilters($filters);

        $filteredProductIds = $this->productRepository->filterProductIds(
            $allProductIds,
            $parsed['itemFilters'],
            $parsed['numericFilters'],
            $parsed['priceFilter'],
            $parsed['stockFilter'],
        );

        // Paginate
        $perPage = $this->getParameter('productsPerPage', 20);
        $totalProducts = count($filteredProductIds);
        $lastPage = max(1, (int) ceil($totalProducts / $perPage));
        $p = min($p, $lastPage);

        $pageProductIds = array_slice($filteredProductIds, ($p - 1) * $perPage, $perPage);

        // Load entities and preserve order
        $productsById = !empty($pageProductIds)
            ? $this->productRepository->findByIds($pageProductIds, $shopId)
            : [];

        $products = [];
        foreach ($pageProductIds as $id) {
            if (isset($productsById[$id])) {
                $products[] = $productsById[$id];
            }
        }

        // Breadcrumbs
        $breadcrumbCategories = $this->menuCategoryRepository->getBreadcrumbs($category, $shopId);
        $breadcrumbs = [];
        foreach ($breadcrumbCategories as $crumb) {
            $breadcrumbs[] = [
                'name' => $crumb->name,
                'destination' => 'Category:default',
                'params' => ['slug' => $crumb->url],
            ];
        }

        // Assign to template
        $this->template->category = $category;
        $this->template->childCategories = $childCategories;
        $this->template->breadcrumbs = $breadcrumbs;
        $this->template->products = $products;
        $this->template->filters = $filters;
        $this->template->activeFilterParams = $activeParams;
        $this->template->totalProducts = $totalProducts;
        $this->template->pagination = [
            'page' => $p,
            'lastPage' => $lastPage,
        ];
    }

    /**
     * Extract filter parameters from URL
     *
     * Accepts both formats:
     * - JS:    f5=10,20    f8=40-80    price=100-500
     * - No-JS: f5[]=10     f8_min=40   price_min=100
     *
     * Normalizes everything to JS format internally.
     */
    private function getFilterParams(): array
    {
        $params = [];
        $query = $this->getHttpRequest()->getQuery();

        // First pass: direct params (JS format) and checkbox arrays (no-JS)
        foreach ($query as $key => $value) {
            if ($value === '' || $value === null) {
                continue;
            }

            // JS format: f5=10,20 or price=100-500
            if ((preg_match('/^f\d+$/', $key) || $key === 'price') && is_string($value)) {
                $params[$key] = $value;
                continue;
            }

            // No-JS checkbox arrays: f5[]=10&f5[]=20 → PHP sees f5 as array
            if (preg_match('/^f\d+$/', $key) && is_array($value)) {
                $params[$key] = implode(',', array_map('intval', $value));
            }

            // Stock filter
            if ($key === 'stock') {
                $params['stock'] = (string) $value;
            }
        }

        // Second pass: _min/_max pairs (no-JS range format)
        $rangePairs = [];
        foreach ($query as $key => $value) {
            if (!preg_match('/^(f\d+|price)_(min|max)$/', $key, $m)) {
                continue;
            }
            if ($value === '' || $value === null) {
                continue;
            }

            $filterKey = $m[1];
            $side = $m[2];

            // Skip if JS format already covered this
            if (isset($params[$filterKey])) {
                continue;
            }

            $rangePairs[$filterKey][$side] = $value;
        }

        foreach ($rangePairs as $filterKey => $sides) {
            $min = $sides['min'] ?? '';
            $max = $sides['max'] ?? '';
            if ($min !== '' || $max !== '') {
                $params[$filterKey] = $min . '-' . $max;
            }
        }

        return $params;
    }
}