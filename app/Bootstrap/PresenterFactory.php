<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Nette\Application\IPresenterFactory;
use Nette\Application\IPresenter;
use Nette\Application\InvalidPresenterException;
use App\Shop\ShopContext;
use Nette\DI\Container;

class PresenterFactory implements IPresenterFactory
{
    public function __construct(
        private ShopContext $shopContext,
        private Container $container
    ) {}

    /**
     * Create presenter instance with hierarchical lookup
     *
     * Tries: UI\{Shop}\{Module}\{Name}Presenter
     * Falls back to: UI\Base\{Module}\{Name}Presenter
     *
     * @param string $name Presenter name (e.g., 'Product', 'Home', 'Account')
     * @throws InvalidPresenterException
     */
    public function createPresenter(string $name): IPresenter
    {
        $class = $this->getPresenterClass($name);

        if (!class_exists($class)) {
            throw new InvalidPresenterException(
                "Cannot load presenter '$name', class '$class' was not found."
            );
        }

        // Create via DI container (autowires constructor + inject methods)
        $presenter = $this->container->createInstance($class);
        $this->container->callInjects($presenter);

        if (!$presenter instanceof IPresenter) {
            throw new InvalidPresenterException(
                "Presenter '$class' must implement Nette\\Application\\IPresenter interface."
            );
        }

        return $presenter;
    }

    /**
     * Get presenter class name with hierarchical lookup
     *
     * @param string $name Presenter name (e.g., 'Product', 'Home')
     * @return string Full class name
     * @throws InvalidPresenterException
     */
    public function getPresenterClass(string &$name): string
    {
        $presenterName = $name;
        $module = null;

        // Handle module notation (Error:Error4xx → module=Error, presenter=Error4xx)
        if (str_contains($presenterName, ':')) {
            $parts = explode(':', $presenterName);
            $presenterName = array_pop($parts);
            $module = implode('\\', $parts);
        }

        $shopTextId = $this->getShopTextIdForClass();

        // Try shop-specific presenter first
        $shopClass = $this->buildClassNameWithModule($shopTextId, $module, $presenterName);
        if (class_exists($shopClass)) {
            return $shopClass;
        }

        // Fallback to Base presenter
        $baseClass = $this->buildClassNameWithModule('Base', $module, $presenterName);
        if (class_exists($baseClass)) {
            return $baseClass;
        }

        throw new InvalidPresenterException(
            "Cannot load presenter '$name'. " .
            "Tried:\n" .
            "  - Shop-specific: $shopClass\n" .
            "  - Base fallback: $baseClass\n" .
            "Please create at least the Base presenter."
        );
    }

    /**
     * Build class name with optional module support
     *
     * No module:  App\UI\Base\Home\HomePresenter
     * With module: App\UI\Base\Error\Error4xxPresenter
     */
    private function buildClassNameWithModule(string $directory, ?string $module, string $presenterName): string
    {
        if ($module !== null) {
            return sprintf(
                'App\\UI\\%s\\%s\\%sPresenter',
                $directory,
                $module,
                $presenterName
            );
        }

        return sprintf(
            'App\\UI\\%s\\%s\\%sPresenter',
            $directory,
            $presenterName,
            $presenterName
        );
    }

    /**
     * Get shop textId formatted for class name (ucfirst)
     *
     * 'florea' → 'Florea'
     * 'velke-vence' → 'VelkeVence'
     */
    private function getShopTextIdForClass(): string
    {
        $textId = $this->shopContext->getTextId();

        // Convert kebab-case to PascalCase
        // 'velke-vence' → 'VelkeVence'
        return str_replace('-', '', ucwords($textId, '-'));
    }
}