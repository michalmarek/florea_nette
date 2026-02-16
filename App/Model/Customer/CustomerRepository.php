<?php declare(strict_types=1);

namespace App\Model\Customer;

use DateTimeImmutable;
use Nette\Database\Explorer;

/**
 * CustomerRepository
 *
 * Handles database access for Customer entities.
 * Maps Czech database columns to English PHP properties.
 *
 * Database table: es_uzivatele
 *
 * Key mappings:
 * - login → login (unchanged)
 * - fak_email → email
 * - fak_jmeno → firstName
 * - fak_prijmeni → lastName
 * - fak_telefon → phone
 */
class CustomerRepository
{
    public function __construct(
        private Explorer $database,
    ) {}

    /**
     * Find customer by ID
     */
    public function findById(int $id): ?Customer
    {
        $row = $this->database->table('es_uzivatele')->get($id);

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Find customer by login
     */
    public function findByLogin(string $login): ?Customer
    {
        $row = $this->database->table('es_uzivatele')
            ->where('login', $login)
            ->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Find customer by email (fak_email OR login)
     *
     * Searches both fields to handle cases where:
     * - Customer uses original login email
     * - Customer changed fak_email but remembers it
     *
     * Safe because emailExistsForAnotherCustomer() prevents duplicates.
     */
    public function findByEmail(string $email): ?Customer
    {
        $row = $this->database->table('es_uzivatele')
            ->whereOr([
                'login' => $email,
                'fak_email' => $email,
            ])
            ->fetch();

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Check if email exists for another customer
     *
     * Checks login AND fak_email of all other customers.
     * Used for profile email change and registration validation.
     */
    public function emailExistsForAnotherCustomer(string $email, int $customerId): bool
    {
        return $this->database->table('es_uzivatele')
                ->whereOr([
                    'login' => $email,
                    'fak_email' => $email,
                ])
                ->where('id != ?', $customerId)
                ->count() > 0;
    }

    /**
     * Check if login already exists
     */
    public function loginExists(string $login, ?int $excludeId = null): bool
    {
        $query = $this->database->table('es_uzivatele')
            ->where('login', $login);

        if ($excludeId !== null) {
            $query->where('id != ?', $excludeId);
        }

        return $query->count() > 0;
    }

    /**
     * Create new customer
     */
    public function create(array $data): Customer
    {
        $passwordHash = password_hash($data['password'], PASSWORD_DEFAULT);

        $row = $this->database->table('es_uzivatele')->insert([
            'login' => $data['login'],
            'password' => $passwordHash,
            'shop' => $data['shopId'],
            'lang' => $data['language'] ?? 'cs',
            'fak_email' => $data['email'],
            'fak_jmeno' => $data['firstName'],
            'fak_prijmeni' => $data['lastName'],
            'fak_osloveni' => $data['salutation'] ?? '',
            'fak_telefon' => $data['phone'] ?? '',
            'fak_firma' => $data['companyName'] ?? '',
            'fak_ulice' => $data['street'] ?? '',
            'fak_mesto' => $data['city'] ?? '',
            'fak_psc' => $data['postalCode'] ?? '',
            'fak_country' => $data['country'] ?? 'cz',
            'fak_ic' => $data['companyId'] ?? '',
            'fak_dic' => $data['vatId'] ?? '',
            'aktivni' => $data['active'] ?? '1',
            'schvaleny' => '0',
            'newsletter' => $data['newsletter'] ?? '0',
            'datum' => date('Y-m-d H:i:s'),
        ]);

        return $this->mapToEntity($row);
    }

    /**
     * Update customer data (only provided fields)
     */
    public function update(int $id, array $data): Customer
    {
        // EN key → CZ column mapping
        $fieldMap = [
            'email' => 'fak_email',
            'firstName' => 'fak_jmeno',
            'lastName' => 'fak_prijmeni',
            'phone' => 'fak_telefon',
            'companyName' => 'fak_firma',
            'salutation' => 'fak_osloveni',
            'billingStreet' => 'fak_ulice',
            'billingCity' => 'fak_mesto',
            'billingPostalCode' => 'fak_psc',
            'billingCountry' => 'fak_country',
            'companyId' => 'fak_ic',
            'vatId' => 'fak_dic',
            'newsletterConsent' => 'newsletter',
        ];

        $updateData = [];
        foreach ($fieldMap as $english => $czech) {
            if (array_key_exists($english, $data)) {
                $updateData[$czech] = $data[$english];
            }
        }

        if (!empty($updateData)) {
            $this->database->table('es_uzivatele')
                ->where('id', $id)
                ->update($updateData);
        }

        return $this->findById($id);
    }

    /**
     * Update customer password
     */
    public function updatePassword(int $id, string $newPassword): void
    {
        $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);

        $this->database->table('es_uzivatele')
            ->where('id', $id)
            ->update(['password' => $passwordHash]);
    }

    /**
     * Update last login timestamp
     */
    public function updateLastLogin(int $id): void
    {
        $this->database->table('es_uzivatele')
            ->where('id', $id)
            ->update(['prihlasen' => date('Y-m-d H:i:s')]);
    }

    /**
     * Activate pre-registered customer account
     *
     * Used when customer registers after placing order without account.
     */
    public function activate(int $id, string $password): void
    {
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $this->database->table('es_uzivatele')
            ->where('id', $id)
            ->update([
                'password' => $passwordHash,
                'aktivni' => '1',
            ]);
    }

    /**
     * Map database row to Customer entity
     *
     * Converts Czech column names to English properties.
     */
    private function mapToEntity(object $row): Customer
    {
        return new Customer(
            id: (int) $row->id,
            login: $row->login,
            email: $row->fak_email,
            firstName: $row->fak_jmeno,
            lastName: $row->fak_prijmeni,
            salutation: $row->fak_osloveni ?: null,
            phone: $row->fak_telefon ?: null,

            // Billing address
            companyName: $row->fak_firma ?: null,
            billingStreet: $row->fak_ulice,
            billingCity: $row->fak_mesto,
            billingPostalCode: $row->fak_psc,
            billingCountry: $row->fak_country,
            companyId: $row->fak_ic ?: null,
            vatId: $row->fak_dic ?: null,

            // Metadata
            shopId: (int) $row->shop,
            language: $row->lang,
            active: $row->aktivni === '1',
            approved: $row->schvaleny === '1',
            newsletterConsent: $row->newsletter === '1',
            registeredAt: $this->parseDateTime($row->datum),
            lastLoginAt: $this->parseDateTime($row->prihlasen),

            // Password (private)
            passwordHash: $row->password,
        );
    }

    /**
     * Parse database datetime to DateTimeImmutable
     *
     * Handles null, zero dates ('0000-00-00 00:00:00'),
     * and Nette Database DateTime objects.
     */
    private function parseDateTime(mixed $value): ?DateTimeImmutable
    {
        if (!$value || $value === '0000-00-00 00:00:00') {
            return null;
        }

        if ($value instanceof \DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value);
        }

        return new DateTimeImmutable($value);
    }
}