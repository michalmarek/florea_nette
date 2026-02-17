<?php declare(strict_types=1);

namespace App\UI\Base\Product;

use Nette\Application\UI\Form;
use App\UI\Base\BasePresenter;
use App\Model\Product\ProductRepository;
use App\Model\Product\ProductVariantService;
use App\Model\Category\MenuCategoryRepository;
use App\Model\Cart\CartRepository;

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

    private CartRepository $cartRepository;

    public function injectCart(CartRepository $cartRepository): void
    {
        $this->cartRepository = $cartRepository;
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

        $this['addToCartForm']->setDefaults([
            'productId' => $product->id,
        ]);
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

    protected function createComponentAddToCartForm(): Form
    {
        $form = $this->formFactory->create();

        $form->addInteger('quantity', 'Množství')
            ->setDefaultValue(1)
            ->setRequired('Zadejte množství')
            ->addRule($form::Min, 'Minimální množství je 1 ks', 1)
            ->setHtmlAttribute('min', 1)
            ->setHtmlAttribute('class', 'form-control text-center')
            ->setHtmlAttribute('style', 'width: 80px;');

        $form->addHidden('productId');

        $form->addSubmit('submit', 'Přidat do košíku')
            ->setHtmlAttribute('class', 'btn btn-primary btn-lg px-5');

        $form->onSuccess[] = $this->addToCartFormSucceeded(...);

        return $form;
    }

    private function addToCartFormSucceeded(Form $form, \stdClass $values): void
    {
        $product = $this->productRepository->findById((int) $values->productId, $this->shopContext->getId());

        if (!$product || !$product->visible) {
            $this->flashMessage('Produkt není dostupný', 'danger');
            $this->redirect('this');
        }

        $quantity = $values->quantity;
        $availableQuantity = min($quantity, (int) $product->stock);

        if ($availableQuantity === 0) {
            $this->flashMessage("Produkt '{$product->name}' je vyprodán", 'danger');
            $this->redirect('this');
        }

        $cart = $this->cartRepository->get();
        $cart->addItem($product->id, $availableQuantity, $product->getPrice());
        $this->cartRepository->save($cart);

        if ($availableQuantity < $quantity) {
            $this->flashMessage(
                "Do košíku přidáno pouze {$availableQuantity} ks (omezený sklad)",
                'warning',
            );
        } else {
            $this->flashMessage("Produkt '{$product->name}' byl přidán do košíku", 'success');
        }

        $this->redirect('Cart:default');
    }
}