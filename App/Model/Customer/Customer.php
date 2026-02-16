<?php declare(strict_types=1);

namespace App\Model\Customer;

use DateTimeImmutable;

/**
 * Customer Entity
 *
 * Represents a customer from es_uzivatele table.
 *
 * Uses hybrid approach:
 * - Public readonly: Simple data passthrough
 * - Private readonly + getter: Password hash (internal only)
 *
 * Key properties:
 * - login: Immutable identifier (cannot be changed)
 * - email: Current contact email from fak_email (can be changed)
 * - active: false = pre-registration (inactive account after order without registration)
 */
class Customer
{
    public function __construct(
        // === Public readonly (simple passthrough) ===
        public readonly int $id,
        public readonly string $login,          // Immutable identifier
        public readonly string $email,          // Current contact email (fak_email)
        public readonly string $firstName,      // fak_jmeno
        public readonly string $lastName,       // fak_prijmeni
        public readonly ?string $salutation,    // fak_osloveni
        public readonly ?string $phone,         // fak_telefon (normalized E.164)

        // Billing address
        public readonly ?string $companyName,   // fak_firma
        public readonly string $billingStreet,  // fak_ulice
        public readonly string $billingCity,    // fak_mesto
        public readonly string $billingPostalCode, // fak_psc
        public readonly string $billingCountry, // fak_country
        public readonly ?string $companyId,     // fak_ic
        public readonly ?string $vatId,         // fak_dic

        // Metadata
        public readonly int $shopId,            // shop
        public readonly string $language,       // lang
        public readonly bool $active,           // aktivni
        public readonly bool $approved,         // schvaleny
        public readonly bool $newsletterConsent, // newsletter
        public readonly ?DateTimeImmutable $registeredAt, // datum
        public readonly ?DateTimeImmutable $lastLoginAt,  // prihlasen

        // === Private (internal only) ===
        private readonly string $passwordHash,  // password - never exposed
    ) {}

    /**
     * Get full customer name
     */
    public function getFullName(): string
    {
        return trim("{$this->firstName} {$this->lastName}");
    }

    /**
     * Verify password against stored hash
     *
     * Internal method - used only by Authenticator.
     */
    public function verifyPassword(string $plainPassword): bool
    {
        return password_verify($plainPassword, $this->passwordHash);
    }
}