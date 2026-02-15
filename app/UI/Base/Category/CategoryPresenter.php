<?php declare(strict_types=1);

namespace App\UI\Base\Category;

use App\UI\Base\BasePresenter;
use App\Model\Category\MenuCategoryRepository;

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

    public function injectCategoryDependencies(
        MenuCategoryRepository $menuCategoryRepository,
    ): void {
        $this->menuCategoryRepository = $menuCategoryRepository;
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
    }
}