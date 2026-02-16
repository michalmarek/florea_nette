<?php declare(strict_types=1);

namespace App\UI\Base\Product;

use App\UI\Base\BasePresenter;
use App\Model\Product\ProductRepository;
use App\Model\Product\ProductVariantService;
use App\Model\Category\MenuCategoryRepository;

/**
 * ProductPresenter
 *
 * Displays product detail.
 */
class ProductPresenter extends BasePresenter
{
    private ProductRepository $productRepository;
    private ProductVariantService $variantService;
    private MenuCategoryRepository $menuCategoryRepository;

    public function injectProductDependencies(
        ProductRepository $productRepository,
        ProductVariantService $variantService,
        MenuCategoryRepository $menuCategoryRepository,
    ): void {
        $this->productRepository = $productRepository;
        $this->variantService = $variantService;
        $this->menuCategoryRepository = $menuCategoryRepository;
    }

    /**
     * Product detail by URL slug (primary)
     */
    public function actionDetail(string $slug): void
    {
        $shopId = $this->shopContext->getId();

        $product = $this->productRepository->findByUrl($slug, $shopId);

        if (!$product) {
            $this->error('Produkt nebyl nalezen');
        }

        $variants = $this->variantService->getVariants($product, $shopId);

        $this->template->product = $product;
        $this->template->variants = $variants;
        $this->template->breadcrumbs = $this->buildBreadcrumbs($product, $shopId);
    }

    /**
     * Product detail by ID (fallback, redirects to slug URL)
     */
    public function actionDetailById(int $id): void
    {
        $shopId = $this->shopContext->getId();

        $product = $this->productRepository->findById($id, $shopId);

        if (!$product) {
            $this->error('Produkt nebyl nalezen');
        }

        $this->redirectPermanent('detail', ['slug' => $product->url]);
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