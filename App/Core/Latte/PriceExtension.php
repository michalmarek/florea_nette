<?php declare(strict_types=1);

namespace App\Core\Latte;

use Latte\Extension;

class PriceExtension extends Extension
{
    public function getFilters(): array
    {
        return [
            'price' => $this->formatPrice(...),
        ];
    }

    private function formatPrice(float $price): string
    {
        return number_format($price, 0, '.', ' ') . ' Kč';
    }
}