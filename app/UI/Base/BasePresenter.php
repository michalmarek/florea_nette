<?php

declare(strict_types=1);

namespace App\UI\Base;

use Nette\Application\UI\Presenter;
use App\Shop\ShopContext;

/**
 * Base presenter for all application presenters
 *
 * Features:
 * - Hierarchical template resolution (shop-specific → Base)
 * - ShopContext injection and availability in templates
 * - Common setup for all presenters
 */
abstract class BasePresenter extends Presenter
{
    // ShopContext injected via DI (available in all child presenters)
    protected ?ShopContext $shopContext = null;

    public function injectShopContext(ShopContext $shopContext): void
    {
        $this->shopContext = $shopContext;
    }

    protected function startup(): void
    {
        parent::startup();

        $this->template->shopContext = $this->shopContext;
        $this->template->basePath = $this->getHttpRequest()->getUrl()->getBasePath();
    }

    /**
     * Hierarchical layout file resolution
     *
     * Tries (in order):
     * 1. UI/{Shop}/{Presenter}/@layout.latte
     * 2. UI/{Shop}/@layout.latte
     * 3. UI/Base/{Presenter}/@layout.latte
     * 4. UI/Base/@layout.latte
     *
     * @return array List of layout file paths to try
     */
    public function formatLayoutTemplateFiles(): array
    {
        $name = $this->getName();
        $presenter = $this->formatPresenterName($name);

        $shopDir = $this->getShopDirectory();
        $baseDir = $this->getBaseDirectory();

        return [
            // Shop-specific layouts
            "$shopDir/$presenter/@layout.latte",
            "$shopDir/@layout.latte",

            // Base fallback layouts
            "$baseDir/$presenter/@layout.latte",
            "$baseDir/@layout.latte",
        ];
    }

    /**
     * Hierarchical template file resolution
     *
     * Tries (in order):
     * 1. UI/{Shop}/{Presenter}/{action}.latte
     * 2. UI/Base/{Presenter}/{action}.latte
     *
     * Example for florea.cz, HomePresenter, action 'default':
     * 1. UI/Florea/Home/default.latte
     * 2. UI/Base/Home/default.latte
     *
     * @return array List of template file paths to try
     */
    public function formatTemplateFiles(): array
    {
        $name = $this->getName();
        $presenter = $this->formatPresenterName($name);
        $action = $this->getAction();

        $shopDir = $this->getShopDirectory();
        $baseDir = $this->getBaseDirectory();

        return [
            // Shop-specific templates
            "$shopDir/$presenter/$action.latte",
            "$shopDir/$presenter.$action.latte",

            // Base fallback templates
            "$baseDir/$presenter/$action.latte",
            "$baseDir/$presenter.$action.latte",
        ];
    }

    /**
     * Get shop-specific template directory
     */
    private function getShopDirectory(): string
    {
        $shopClass = $this->getShopTextIdForDirectory();
        return __DIR__ . "/../{$shopClass}";
    }

    /**
     * Get base template directory
     */
    private function getBaseDirectory(): string
    {
        return __DIR__;
    }

    /**
     * Format presenter name from full name
     *
     * 'Home' → 'Home'
     * 'Product' → 'Product'
     * 'Admin:Dashboard' → 'Admin/Dashboard'
     */
    private function formatPresenterName(string $name): string
    {
        // Extract presenter name (remove module prefix)
        // 'Product' → 'Product'
        // 'Admin:Dashboard' → 'Dashboard'
        $parts = explode(':', $name);
        $presenterName = end($parts);

        return $presenterName;
    }

    /**
     * Get shop textId formatted for directory name
     *
     * 'florea' → 'Florea'
     * 'velke-vence' → 'VelkeVence'
     */
    private function getShopTextIdForDirectory(): string
    {
        $textId = $this->shopContext->getTextId();

        // Convert kebab-case to PascalCase
        return str_replace('-', '', ucwords($textId, '-'));
    }

    /**
     * Before render - common for all actions
     */
    protected function beforeRender(): void
    {
        parent::beforeRender();

        // Add presenter and action to template (useful for CSS classes, debugging)
        $this->template->presenterName = $this->getName();
        $this->template->actionName = $this->getAction();
    }
}