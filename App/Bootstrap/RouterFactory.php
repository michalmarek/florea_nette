<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Nette\Application\Routers\RouteList;
use Nette\Application\Routers\Route;
use Nette\Neon\Neon;

class RouterFactory
{
    /**
     * Create application router for specific shop
     *
     * @param string $shopTextId Shop identifier (e.g., 'florea', 'velke-vence')
     * @throws \RuntimeException If route file not found
     */
    public static function createRouter(string $shopTextId): RouteList
    {
        $router = new RouteList;

        // Load shop-specific routes from neon file
        $routeFile = __DIR__ . "/../../config/routes/{$shopTextId}.neon";

        if (!file_exists($routeFile)) {
            throw new \RuntimeException(
                "Route file not found for shop '{$shopTextId}'. " .
                "Expected: {$routeFile}"
            );
        }

        $config = Neon::decodeFile($routeFile);

        if (!isset($config['routes']) || !is_array($config['routes'])) {
            throw new \RuntimeException(
                "Invalid route file for shop '{$shopTextId}'. " .
                "Expected 'routes' key with array of routes."
            );
        }

        // Add routes from config
        foreach ($config['routes'] as $route) {
            self::addRoute($router, $route);
        }

        return $router;
    }

    /**
     * Add single route (supports both 'mask' and 'patterns' for multi-language)
     */
    private static function addRoute(RouteList $router, array $route): void
    {
        // Check for simple mask (single language)
        if (isset($route['mask'])) {
            $metadata = array_diff_key($route, ['mask' => true]);
            $router->addRoute($route['mask'], $metadata);
            return;
        }

        // Check for patterns (multi-language support)
        if (isset($route['patterns'])) {
            self::addMultiLanguageRoute($router, $route);
            return;
        }

        throw new \RuntimeException(
            "Route must have either 'mask' (single language) or 'patterns' (multi-language)"
        );
    }

    /**
     * Add multi-language route with language parameter
     */
    private static function addMultiLanguageRoute(RouteList $router, array $route): void
    {
        if (!is_array($route['patterns'])) {
            throw new \RuntimeException("Route 'patterns' must be an array");
        }

        $metadata = array_diff_key($route, ['patterns' => true]);

        foreach ($route['patterns'] as $lang => $mask) {
            // Add language parameter to metadata
            $langMetadata = array_merge($metadata, ['lang' => $lang]);

            $router->addRoute($mask, $langMetadata);
        }
    }
}