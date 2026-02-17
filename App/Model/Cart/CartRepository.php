<?php declare(strict_types=1);

namespace App\Model\Cart;

use App\Shop\ShopContext;
use App\Model\Product\ProductRepository;
use Nette\Http\Session;
use Nette\Http\SessionSection;

class CartRepository
{
    /** Cart expiration in hours */
    private const EXPIRATION_HOURS = 24;

    /** Session section name */
    private const SESSION_SECTION = 'cart';

    private SessionSection $section;

    public function __construct(
        private ShopContext $shopContext,
        private ProductRepository $productRepository,
        Session $session,
    ) {
        $this->section = $session->getSection(self::SESSION_SECTION);
    }

    // ========================================
    // Public API
    // ========================================

    /**
     * Get cart for current shop.
     * Loads from session, checks expiration, touches timestamp.
     * Does NOT validate items — call validate() separately when needed.
     */
    public function get(): Cart
    {
        $shopId = $this->shopContext->getId();
        $key = $this->getKey();

        $data = $this->section->get($key);

        if ($data === null) {
            return Cart::create($shopId);
        }

        $cart = Cart::fromArray($shopId, $data);

        if ($cart->isExpired(self::EXPIRATION_HOURS)) {
            $this->clear();
            return Cart::create($shopId);
        }

        // Touch — update timestamp for expiration tracking
        $cart->touch();
        $this->save($cart);

        return $cart;
    }

    /**
     * Validate cart items against current DB state.
     *
     * Checks product availability, stock, price changes.
     * Automatically adjusts cart (removes unavailable, reduces quantity).
     * Returns result with messages for user notification.
     *
     * Call when: displaying cart page, before checkout, before order submission.
     */
    public function validate(Cart $cart): CartValidationResult
    {
        $result = new CartValidationResult();

        if ($cart->isEmpty()) {
            return $result;
        }

        // Load current products from DB
        $products = $this->productRepository->findByIds($cart->getProductIds(), $this->shopContext->getId());

        // Check availability and stock
        foreach ($cart->getItems() as $item) {
            $product = $products[$item->productId] ?? null;

            if (!$product) {
                $cart->removeItem($item->productId);
                $result->addRemovedProduct('Neznámý produkt', 'produkt nenalezen');
                continue;
            }

            if (!$product->visible) {
                $cart->removeItem($item->productId);
                $result->addRemovedProduct($product->name, 'není v nabídce');
                continue;
            }

            if ($product->stock < $item->quantity) {
                if ($product->stock > 0) {
                    $oldQuantity = $item->quantity;
                    $newQuantity = (int) $product->stock;
                    $cart->updateQuantity($item->productId, $newQuantity);
                    $result->addQuantityChange($product->name, $oldQuantity, $newQuantity);
                } else {
                    $cart->removeItem($item->productId);
                    $result->addRemovedProduct($product->name, 'vyprodáno');
                }
            }
        }

        // Attach products to items (for price calculation, template display)
        $cart->attachProducts($products);

        // Check price changes
        foreach ($cart->getItems() as $item) {
            $product = $products[$item->productId] ?? null;

            if ($product) {
                $priceAtAddition = $item->getPriceAtAddition();
                $currentPrice = $product->getPrice();

                if ($priceAtAddition !== $currentPrice) {
                    $result->addPriceChange($product->name, $priceAtAddition, $currentPrice);
                    $cart->updatePriceAtAddition($item->productId, $currentPrice);
                }
            }
        }

        $this->save($cart);

        return $result;
    }

    public function save(Cart $cart): void
    {
        $this->section->set($this->getKey(), $cart->toArray());
    }

    public function clear(): void
    {
        $this->section->remove($this->getKey());
    }

    // ========================================
    // Private Helpers
    // ========================================

    /**
     * Session key per shop: "shop_1", "shop_2", etc.
     */
    private function getKey(): string
    {
        return 'shop_' . $this->shopContext->getId();
    }
}