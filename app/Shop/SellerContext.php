<?php

declare(strict_types=1);

namespace App\Shop;

class SellerContext
{
    public readonly int $id;
    public readonly string $name;

    private readonly array $data;

    private function __construct(int $id, string $name, array $data)
    {
        $this->id = $id;
        $this->name = $name;
        $this->data = $data;
    }

    // === Core Identity ===

    public function getId(): int
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    // === Contact info ===

    public function getPhoneNumber(): string
    {
        return $this->data['phoneNumber'];
    }

    public function getEmail(): string
    {
        return $this->data['email'];
    }

    public function getNotificationEmail(): string
    {
        return $this->data['notificationEmail'];
    }

    // === Company details ===

    public function getCompanyName(): string
    {
        return $this->data['companyName'];
    }

    public function getStreet(): string
    {
        return $this->data['street'];
    }

    public function getCity(): string
    {
        return $this->data['city'];
    }

    public function getPostalCode(): string
    {
        return $this->data['postalCode'];
    }

    /**
     * Get full address as string
     */
    public function getFullAddress(): string
    {
        return sprintf(
            '%s, %s %s',
            $this->getStreet(),
            $this->getPostalCode(),
            $this->getCity()
        );
    }

    // === Tax info ===

    public function getRegistrationNumber(): string
    {
        return $this->data['registrationNumber'];
    }

    public function getVatNumber(): string
    {
        return $this->data['vatNumber'];
    }

    public function isVatPayer(): bool
    {
        return $this->data['vatPayer'];
    }

    // === Registry ===

    public function getRegistryCity(): string
    {
        return $this->data['registryCity'];
    }

    public function getRegistryNumber(): string
    {
        return $this->data['registryNumber'];
    }

    // === Banking ===

    public function getBankAccount(): string
    {
        return $this->data['bankAccount'];
    }

    public function getBankAccountIban(): string
    {
        return $this->data['bankAccountIban'];
    }

    public function getBankAccountBic(): string
    {
        return $this->data['bankAccountBic'];
    }

    // === Business ===

    public function getSupplier(): string
    {
        return $this->data['supplier'];
    }

    public function isExternalSale(): bool
    {
        return $this->data['externalSale'];
    }

    /**
     * Get GoPay configuration (JSON decoded)
     */
    public function getGopayConfig(): ?array
    {
        if (empty($this->data['gopayConfig'])) {
            return null;
        }

        return json_decode($this->data['gopayConfig'], true);
    }

    // === Magic getter ===

    public function __get(string $name): mixed
    {
        return $this->data[$name] ?? null;
    }

    // === Factory ===

    public static function createFromData(array $data): self
    {
        return new self(
            $data['id'],
            $data['name'],
            $data
        );
    }
}