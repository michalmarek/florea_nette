<?php

declare(strict_types=1);

namespace App;

use Nette\Bootstrap\Configurator;
use Nette\Database\Connection;
use Nette\Database\Explorer;
use Nette\Database\Structure;
use Nette\Caching\Storages\DevNullStorage;
use App\Shop\ShopDetector;
use App\Shop\ShopRepository;
use App\Shop\ShopContext;
use App\Bootstrap\RouterFactory;

class Bootstrap
{
    private Configurator $configurator;
    private ?string $shopTextId = null;
    private ?ShopContext $shopContext = null;

    public function __construct()
    {
        $this->configurator = new Configurator;
        $this->setupEnvironment();
    }

    /**
     * Setup basic environment (temp dir, debug mode)
     */
    private function setupEnvironment(): void
    {
        $rootDir = dirname(__DIR__);

        // Set temp directory for cache
        $this->configurator->setTempDirectory($rootDir . '/temp');

        // Enable debug mode for development
        $this->configurator->setDebugMode(true);

        // Enable Tracy debugger
        $this->configurator->enableTracy($rootDir . '/log');
    }

    /**
     * Main boot method - detects shop and loads configs
     */
    public function boot(): self
    {
        // 1. Load common config
        $this->configurator->addConfig(__DIR__ . '/../config/common.neon');

        // 2. Load local config
        $localConfig = __DIR__ . '/../config/local.neon';
        if (file_exists($localConfig)) {
            $this->configurator->addConfig($localConfig);
        }

        // 3. Detect shop (without creating DI container yet!)
        $this->detectShopBeforeContainer();

        // 4. Load shop-specific config
        if ($this->shopTextId !== null) {
            $this->loadShopConfig($this->shopTextId);
        }

        return $this;
    }

    /**
     * Detect shop BEFORE creating DI container
     * Uses config files directly instead of temp container
     */
    private function detectShopBeforeContainer(): void
    {
        // Get domain mapping from common.neon via file parsing
        $domainsNeon = \Nette\Neon\Neon::decodeFile(__DIR__ . '/../config/domains.neon');
        $domainMapping = $domainsNeon['parameters']['domainMapping'];

        // Get database config from local.neon
        $localNeon = \Nette\Neon\Neon::decodeFile(__DIR__ . '/../config/local.neon');
        $dbParams = $localNeon['database'];

        // Create temporary database connection (outside DI)
        $connection = new Connection(
            $dbParams['dsn'],
            $dbParams['user'],
            $dbParams['password']
        );

        $structure = new Structure($connection, new DevNullStorage());
        $explorer = new Explorer($connection, $structure);

        // Detect shop
        $repository = new ShopRepository($explorer);
        $detector = new ShopDetector($repository, $domainMapping);

        try {
            $this->shopContext = $detector->detectFromRequest();
            $this->shopTextId = $this->shopContext->getTextId();
        } catch (\Exception $e) {
            throw new \RuntimeException(
                "Shop detection failed: " . $e->getMessage(),
                0,
                $e
            );
        }

        // Close temporary connection - DI container will create its own
        $connection->disconnect();
    }

    /**
     * Load shop-specific config with custom parameter merge
     */
    private function loadShopConfig(string $shopTextId): void
    {
        $shopConfigFile = __DIR__ . "/../config/shops/{$shopTextId}.neon";

        if (!file_exists($shopConfigFile)) {
            // Shop config doesn't exist - that's OK, use common config only
            return;
        }

        // Load and merge shop-specific parameters
        $this->configurator->onCompile[] = function ($configurator, $compiler) use ($shopConfigFile) {
            $this->mergeShopParameters($compiler, $shopConfigFile);
        };
    }

    /**
     * Custom parameter merge - deep merges shop parameters over common
     */
    private function mergeShopParameters($compiler, string $shopConfigFile): void
    {
        // Parse shop config
        $shopConfig = \Nette\Neon\Neon::decodeFile($shopConfigFile);

        if (!isset($shopConfig['parameters'])) {
            return;
        }

        // Get current parameters from compiler
        $params = $compiler->getContainerBuilder()->parameters;

        // Deep merge shop parameters over common parameters
        $params = $this->arrayMergeRecursive($params, $shopConfig['parameters']);

        // Set merged parameters back
        $compiler->getContainerBuilder()->parameters = $params;
    }

    /**
     * Recursive array merge (keeps numeric keys)
     */
    private function arrayMergeRecursive(array $array1, array $array2): array
    {
        foreach ($array2 as $key => $value) {
            if (is_array($value) && isset($array1[$key]) && is_array($array1[$key])) {
                $array1[$key] = $this->arrayMergeRecursive($array1[$key], $value);
            } else {
                $array1[$key] = $value;
            }
        }
        return $array1;
    }

    /**
     * Create DI container ONCE and register shop-specific services
     */
    public function createContainer(): \Nette\DI\Container
    {
        $container = $this->configurator->createContainer();

        // 1. Register ShopContext
        if ($this->shopContext !== null) {
            $container->addService('shopContext', $this->shopContext);
        }

        // 2. Register Router
        if ($this->shopTextId !== null) {
            $router = RouterFactory::createRouter($this->shopTextId);
            $container->addService('router', $router);
        }

        // 3. Register custom PresenterFactory
        $shopContext = $container->getService('shopContext');
        $presenterFactory = new \App\Bootstrap\PresenterFactory($shopContext, $container);
        $container->removeService('application.presenterFactory');
        $container->addService('application.presenterFactory', $presenterFactory);

        return $container;
    }
}