<?php

declare(strict_types=1);

namespace App\UI\Base\Home;

use App\UI\Base\BasePresenter;

/**
 * Homepage presenter
 *
 * Displays main page with featured products, categories, banners, etc.
 */
class HomePresenter extends BasePresenter
{
    /**
     * Default action - homepage
     */
    public function renderDefault(): void
    {
        // For now just render template
        // Later we'll add:
        // - Featured products
        // - Popular categories
        // - Banners
        // - Special offers

        $this->template->pageTitle = 'Úvodní stránka';
    }
}