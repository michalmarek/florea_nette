<?php declare(strict_types=1);

namespace App\Model\Customer;

/**
 * DeliveryAddress Entity
 *
 * Represents a delivery address from es_dodaci table.
 * Each customer can have multiple delivery addresses, one marked as default.
 */
class DeliveryAddress
{
    public function __construct(
        public readonly int $id,
        public readonly int $customerId,
        public readonly string $name,
        public readonly bool $isDefault,
        public readonly ?string $companyName,
        public readonly string $firstName,
        public readonly string $street,
        public readonly string $city,
        public readonly string $postalCode,
        public readonly string $country,
        public readonly ?string $phone,
        public readonly ?float $gpsLat,
        public readonly ?float $gpsLon,
        public readonly ?string $courierNote,
        public readonly ?string $openingHours,
    ) {}
}