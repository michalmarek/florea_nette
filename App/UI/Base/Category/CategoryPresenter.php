<?php declare(strict_types=1);

namespace App\UI\Base\Category;

use App\UI\Base\BasePresenter;
use App\Model\Category\MenuCategoryRepository;
use App\Model\Product\ProductRepository;

/**
 * CategoryPresenter
 *
 * Displays menu category detail with products.
 * Products come from two sources:
 * - Base category assignment (via funnel/descendant categories)
 * - Manual assignment (via es_menu_category_products pivot)
 */
class CategoryPresenter extends BasePresenter
{
    private MenuCategoryRepository $menuCategoryRepository;
    private ProductRepository $productRepository;

    public function injectCategoryDependencies(
        MenuCategoryRepository $menuCategoryRepository,
        ProductRepository $productRepository,
    ): void {
        $this->menuCategoryRepository = $menuCategoryRepository;
        $this->productRepository = $productRepository;
    }

    public function actionDefault(string $slug, int $p = 1): void
    {
        $shopId = $this->shopContext->getId();

        // Find category by URL slug
        $category = $this->menuCategoryRepository->findByUrl($shopId, $slug);

        if (!$category) {
            $this->error('Kategorie nebyla nalezena');
        }

        // Direct child categories (subcategories)
        $childrenSelection = $this->menuCategoryRepository->getChildrenSelection($category->id);
        $childCategories = $this->menuCategoryRepository->mapRowsToEntities($childrenSelection);

        // Products (funnel: all descendants + manual assignments)
        $menuCategoryIds = $this->menuCategoryRepository->getAllDescendantIds($category->id, $shopId);
        $selection = $this->productRepository->getProductsByMenuCategorySelection($shopId, $menuCategoryIds);

        // Pagination
        $perPage = $this->getParameter('productsPerPage', 20);
        $selection->page($p, $perPage, $lastPage);

        // Map to entities
        $products = $this->productRepository->mapRowsToEntities($selection, $shopId);

        // Breadcrumbs (root → current)
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
        $this->template->pagination = [
            'page' => $p,
            'lastPage' => $lastPage ?? 1,
        ];
    }
}