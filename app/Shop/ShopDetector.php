<?php

declare(strict_types=1);

namespace App\Shop;

use App\Shop\Exception\ShopNotFoundException;

class ShopDetector
{
    public function __construct(
        private ShopRepository $repository,
        private array $domainMapping
    ) {}

    /**
     * Detect shop from HTTP request
     *
     * @throws ShopNotFoundException
     */
    public function detectFromRequest(): ShopContext
    {
        $domain = $this->getCurrentDomain();

        // Find textId in domain mapping
        $textId = $this->domainMapping[$domain] ?? null;

        if ($textId === null) {
            throw ShopNotFoundException::forDomain($domain);
        }

        // Load shop + seller from database
        $data = $this->repository->findByTextIdWithSeller($textId);

        // Create contexts
        $seller = SellerContext::createFromData($data['seller']);
        $shop = ShopContext::createFromData($domain, $data['shop'], $seller);

        return $shop;
    }

    /**
     * Get current domain from $_SERVER
     */
    private function getCurrentDomain(): string
    {
        // Use HTTP_HOST (includes port if non-standard)
        $host = $_SERVER['HTTP_HOST'] ?? '';

        // Remove port for matching (e.g., localhost:8000 → localhost)
        // But keep it in ShopContext->domain for reference
        $domain = explode(':', $host)[0];

        return strtolower(trim($domain));
    }

    /**
     * Check if domain is registered in configuration
     */
    public function isDomainRegistered(string $domain): bool
    {
        return isset($this->domainMapping[strtolower($domain)]);
    }

    /**
     * Get textId for a specific domain (useful for testing/admin)
     */
    public function getTextIdForDomain(string $domain): ?string
    {
        return $this->domainMapping[strtolower($domain)] ?? null;
    }
}