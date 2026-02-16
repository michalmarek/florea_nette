<?php declare(strict_types=1);

namespace App\UI\Base\Product;

use App\UI\Base\BasePresenter;
use App\Model\Product\ProductRepository;
use App\Model\Category\MenuCategoryRepository;

/**
 * ProductPresenter
 *
 * Displays product detail.
 */
class ProductPresenter extends BasePresenter
{
    private ProductRepository $productRepository;
    private MenuCategoryRepository $menuCategoryRepository;

    public function injectProductDependencies(
        ProductRepository $productRepository,
        MenuCategoryRepository $menuCategoryRepository,
    ): void {
        $this->productRepository = $productRepository;
        $this->menuCategoryRepository = $menuCategoryRepository;
    }

    public function actionDetail(int $id): void
    {
        $shopId = $this->shopContext->getId();

        $product = $this->productRepository->findById($id, $shopId);

        if (!$product) {
            $this->error('Produkt nebyl nalezen');
        }

        $this->template->product = $product;
        $this->template->breadcrumbs = $this->buildBreadcrumbs($product, $shopId);
    }

    /**
     * Build breadcrumbs: Category hierarchy → Product name
     */
    private function buildBreadcrumbs($product, int $shopId): array
    {
        $breadcrumbs = [];

        // Find menu category for product's base category
        $menuCategory = $this->menuCategoryRepository->findByBaseCategoryId(
            $product->categoryId,
            $shopId,
        );

        if ($menuCategory) {
            $categoryBreadcrumbs = $this->menuCategoryRepository->getBreadcrumbs($menuCategory, $shopId);

            foreach ($categoryBreadcrumbs as $crumb) {
                $breadcrumbs[] = [
                    'name' => $crumb->name,
                    'destination' => 'Category:default',
                    'params' => ['slug' => $crumb->url],
                ];
            }
        }

        // Current product (last item)
        $breadcrumbs[] = [
            'name' => $product->name,
            'destination' => null,
            'params' => [],
        ];

        return $breadcrumbs;
    }
}