<?php declare(strict_types=1);

namespace App\UI\Base\Cart;

use App\Model\Cart\CartRepository;
use App\Model\Product\ProductRepository;
use App\UI\Base\BasePresenter;
use Nette\Application\UI\Form;
use Nette\Application\UI\Multiplier;

class CartPresenter extends BasePresenter
{
    private CartRepository $cartRepository;
    private ProductRepository $productRepository;

    public function injectCart(
        CartRepository $cartRepository,
        ProductRepository $productRepository,
    ): void {
        $this->cartRepository = $cartRepository;
        $this->productRepository = $productRepository;
    }

    // ========================================
    // Actions
    // ========================================

    public function actionDefault(): void
    {
        $cart = $this->cartRepository->get();

        // Validate cart (stock, prices, availability)
        $validationResult = $this->cartRepository->validate($cart);

        foreach ($validationResult->getMessages() as $msg) {
            $this->flashMessage($msg['message'], $msg['type']);
        }

        // Add default values to quantity forms
        foreach ($cart->getItems() as $item) {
            /** @var Form $qtyForm */
            $qtyForm = $this['quantityForm-' . $item->productId];
            $qtyForm->setDefaults(['quantity' => $item->quantity]);
        }

        $this->template->cart = $cart;
    }

    // ========================================
    // Signals
    // ========================================

    public function handleAdd(int $productId, int $quantity = 1): void
    {
        if ($quantity < 1) {
            $this->flashMessage('Neplatné množství', 'danger');
            $this->redirect('default');
        }

        $product = $this->productRepository->findById($productId, $this->shopContext->getId());

        if (!$product || !$product->visible) {
            $this->flashMessage('Produkt není dostupný', 'danger');
            $this->redirect('default');
        }

        $availableQuantity = min($quantity, (int) $product->stock);

        if ($availableQuantity === 0) {
            $this->flashMessage("Produkt '{$product->name}' je vyprodán", 'danger');
            $this->redirect('default');
        }

        $cart = $this->cartRepository->get();
        $cart->addItem($productId, $availableQuantity, $product->getPrice());
        $this->cartRepository->save($cart);

        if ($availableQuantity < $quantity) {
            $this->flashMessage(
                "Do košíku přidáno pouze {$availableQuantity} ks produktu '{$product->name}' (omezený sklad)",
                'warning',
            );
        } else {
            $this->flashMessage("Produkt '{$product->name}' byl přidán do košíku", 'success');
        }

        $this->redirect('default');
    }

    public function handleRemove(int $productId): void
    {
        $cart = $this->cartRepository->get();

        if (!$cart->hasItem($productId)) {
            $this->flashMessage('Produkt není v košíku', 'danger');
            $this->redirect('this');
        }

        $cart->removeItem($productId);
        $this->cartRepository->save($cart);

        $this->flashMessage('Produkt byl odebrán z košíku', 'success');
        $this->redirect('this');
    }

    public function handleClear(): void
    {
        $cart = $this->cartRepository->get();
        $cart->clear();
        $this->cartRepository->save($cart);

        $this->flashMessage('Košík byl vyprázdněn', 'success');
        $this->redirect('this');
    }

    public function handleRemoveDiscount(): void
    {
        $cart = $this->cartRepository->get();
        $cart->removeDiscountCode();
        $this->cartRepository->save($cart);

        $this->flashMessage('Slevový kód byl odebrán', 'success');
        $this->redirect('this');
    }

    // ========================================
    // Components
    // ========================================

    protected function createComponentQuantityForm(): Multiplier
    {
        return new Multiplier(function (string $productId): Form {
            $form = $this->formFactory->create();

            $form->addInteger('quantity', 'Množství')
                ->setRequired('Zadejte množství')
                ->setHtmlAttribute('class', 'form-control form-control-sm text-center')
                ->setHtmlAttribute('style', 'max-width: 100px;')
                ->setHtmlAttribute('min', 0)
                ->setHtmlAttribute('onchange', 'this.form.submit()');

            $form->addSubmit('submit', 'Aktualizovat')
                ->setHtmlAttribute('class', 'btn btn-sm btn-outline-secondary d-none');

            $form->onSuccess[] = function (Form $form, \stdClass $values) use ($productId): void {
                $this->processQuantityUpdate((int) $productId, $values->quantity);
            };

            return $form;
        });
    }

    protected function createComponentDiscountForm(): Form
    {
        $form = $this->formFactory->create();

        $form->addText('code', 'Slevový kód')
            ->setRequired('Zadejte slevový kód')
            ->setHtmlAttribute('placeholder', 'např. LETO2025');

        $form->addSubmit('submit', 'Použít')
            ->setHtmlAttribute('class', 'btn btn-primary w-100');

        $form->onSuccess[] = $this->discountFormSucceeded(...);

        return $form;
    }

    // ========================================
    // Form Handlers
    // ========================================

    private function discountFormSucceeded(Form $form, \stdClass $values): void
    {
        // TODO: Validate discount code (later with DiscountService)

        $cart = $this->cartRepository->get();
        $cart->applyDiscountCode($values->code);
        $this->cartRepository->save($cart);

        $this->flashMessage('Slevový kód byl použit', 'success');
        $this->redirect('this');
    }

    private function processQuantityUpdate(int $productId, int $quantity): void
    {
        $cart = $this->cartRepository->get();

        if (!$cart->hasItem($productId)) {
            $this->flashMessage('Produkt není v košíku', 'danger');
            $this->redirect('this');
        }

        if ($quantity < 1) {
            $cart->removeItem($productId);
            $this->cartRepository->save($cart);
            $this->flashMessage('Produkt byl odebrán z košíku', 'success');
            $this->redirect('this');
        }

        $product = $this->productRepository->findById($productId, $this->shopContext->getId());

        if (!$product) {
            $cart->removeItem($productId);
            $this->cartRepository->save($cart);
            $this->flashMessage('Produkt není dostupný', 'danger');
            $this->redirect('this');
        }

        $availableQuantity = min($quantity, (int) $product->stock);
        $cart->updateQuantity($productId, $availableQuantity);
        $this->cartRepository->save($cart);

        if ($availableQuantity < $quantity) {
            $this->flashMessage(
                "Množství upraveno na {$availableQuantity} ks (omezený sklad)",
                'warning',
            );
        }

        $this->redirect('this');
    }
}