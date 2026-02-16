<?php declare(strict_types=1);

namespace App\Model\Customer;

use Nette\Database\Explorer;

/**
 * DeliveryAddressRepository
 *
 * Handles database access for DeliveryAddress entities.
 * Database table: es_dodaci
 *
 * Key mappings:
 * - uzivatel → customerId
 * - firma → companyName
 * - jmeno → firstName
 * - ulice → street
 * - mesto → city
 * - psc → postalCode
 * - poznamka_kuryr → courierNote
 * - oteviraci_doba → openingHours
 */
class DeliveryAddressRepository
{
    public function __construct(
        private Explorer $database,
    ) {}

    /**
     * Find all addresses for customer
     *
     * @return DeliveryAddress[]
     */
    public function findByCustomerId(int $customerId): array
    {
        $rows = $this->database->table('es_dodaci')
            ->where('uzivatel', $customerId)
            ->order('is_default DESC, name ASC');

        $entities = [];
        foreach ($rows as $row) {
            $entities[] = $this->mapToEntity($row);
        }
        return $entities;
    }

    /**
     * Find address by ID
     */
    public function findById(int $id): ?DeliveryAddress
    {
        $row = $this->database->table('es_dodaci')->get($id);

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Create new address
     */
    public function create(array $data): DeliveryAddress
    {
        if ($data['isDefault'] ?? false) {
            $this->unsetDefaultForCustomer($data['customerId']);
        }

        $row = $this->database->table('es_dodaci')->insert([
            'uzivatel' => $data['customerId'],
            'name' => $data['name'],
            'is_default' => $data['isDefault'] ? 1 : 0,
            'contact' => 0,
            'firma' => $data['companyName'] ?? '',
            'jmeno' => $data['firstName'],
            'ulice' => $data['street'],
            'mesto' => $data['city'],
            'psc' => $data['postalCode'],
            'country' => $data['country'] ?? 'cz',
            'predvolba' => $data['phonePrefix'] ?? '',
            'telefon' => $data['phone'] ?? '',
            'gps_lat' => $data['gpsLat'] ?? 0,
            'gps_lon' => $data['gpsLon'] ?? 0,
            'poznamka_kuryr' => $data['courierNote'] ?? '',
            'oteviraci_doba' => $data['openingHours'] ?? '',
            'morava' => '0',
            'prefilled' => 0,
        ]);

        return $this->mapToEntity($row);
    }

    /**
     * Update address (only provided fields)
     */
    public function update(int $id, array $data): DeliveryAddress
    {
        // Handle default flag — unset others first
        if (isset($data['isDefault']) && $data['isDefault']) {
            $address = $this->findById($id);
            if ($address) {
                $this->unsetDefaultForCustomer($address->customerId);
            }
        }

        // EN key → CZ column mapping
        $fieldMap = [
            'name' => 'name',
            'isDefault' => 'is_default',
            'companyName' => 'firma',
            'firstName' => 'jmeno',
            'street' => 'ulice',
            'city' => 'mesto',
            'postalCode' => 'psc',
            'country' => 'country',
            'phonePrefix' => 'predvolba',
            'phone' => 'telefon',
            'courierNote' => 'poznamka_kuryr',
            'openingHours' => 'oteviraci_doba',
        ];

        $updateData = [];
        foreach ($fieldMap as $english => $czech) {
            if (array_key_exists($english, $data)) {
                $value = $data[$english];
                // Boolean → int for isDefault
                $updateData[$czech] = is_bool($value) ? (int) $value : $value;
            }
        }

        if (!empty($updateData)) {
            $this->database->table('es_dodaci')
                ->where('id', $id)
                ->update($updateData);
        }

        return $this->findById($id);
    }

    /**
     * Delete address
     */
    public function delete(int $id): void
    {
        $this->database->table('es_dodaci')
            ->where('id', $id)
            ->delete();
    }

    /**
     * Set address as default (unsets all others for that customer)
     */
    public function setAsDefault(int $id): void
    {
        $address = $this->findById($id);
        if ($address) {
            $this->unsetDefaultForCustomer($address->customerId);

            $this->database->table('es_dodaci')
                ->where('id', $id)
                ->update(['is_default' => 1]);
        }
    }

    /**
     * Unset default flag for all customer addresses
     */
    private function unsetDefaultForCustomer(int $customerId): void
    {
        $this->database->table('es_dodaci')
            ->where('uzivatel', $customerId)
            ->update(['is_default' => 0]);
    }

    /**
     * Map database row to DeliveryAddress entity
     */
    private function mapToEntity(object $row): DeliveryAddress
    {
        return new DeliveryAddress(
            id: (int) $row->id,
            customerId: (int) $row->uzivatel,
            name: $row->name,
            isDefault: (bool) $row->is_default,
            companyName: $row->firma ?: null,
            firstName: $row->jmeno,
            street: $row->ulice,
            city: $row->mesto,
            postalCode: $row->psc,
            country: $row->country,
            phone: $row->telefon ?: null,
            gpsLat: $row->gps_lat ? (float) $row->gps_lat : null,
            gpsLon: $row->gps_lon ? (float) $row->gps_lon : null,
            courierNote: $row->poznamka_kuryr ?: null,
            openingHours: $row->oteviraci_doba ?: null,
        );
    }
}