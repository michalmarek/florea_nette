<?php declare(strict_types=1);

namespace App\Model\Parameter;

use Nette\Database\Explorer;

/**
 * ParameterGroupRepository
 *
 * Database table: es_parameterGroups
 */
class ParameterGroupRepository
{
    public function __construct(
        private Explorer $database,
    ) {}

    public function findById(int $id): ?ParameterGroup
    {
        $row = $this->database->table('es_parameterGroups')->get($id);

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Find multiple parameter groups by IDs
     *
     * @param int[] $ids
     * @return ParameterGroup[] Indexed by ID
     */
    public function findByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        $rows = $this->database->table('es_parameterGroups')
            ->where('id', $ids)
            ->fetchAll();

        $groups = [];
        foreach ($rows as $row) {
            $entity = $this->mapToEntity($row);
            $groups[$entity->id] = $entity;
        }

        return $groups;
    }

    private function mapToEntity(object $row): ParameterGroup
    {
        return new ParameterGroup(
            id: (int) $row->id,
            name: $row->name,
            isFreeText: (bool) $row->freeText,
            isFreeInteger: (bool) $row->freeInteger,
            units: $row->units,
        );
    }
}