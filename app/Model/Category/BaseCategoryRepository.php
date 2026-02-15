<?php declare(strict_types=1);

namespace App\Model\Category;

use Nette\Database\Explorer;
use Nette\Database\Table\Selection;

/**
 * BaseCategoryRepository
 *
 * Handles database access for BaseCategory entities.
 * Maps Czech database columns to English PHP properties.
 *
 * Database table: fl_kategorie
 */
class BaseCategoryRepository
{
    public function __construct(
        private Explorer $database,
    ) {}

    /**
     * Find category by ID
     */
    public function findById(int $id): ?BaseCategory
    {
        $row = $this->database->table('fl_kategorie')->get($id);

        return $row ? $this->mapToEntity($row) : null;
    }

    /**
     * Get Selection for all visible base categories
     */
    public function getAllCategoriesSelection(): Selection
    {
        return $this->database->table('fl_kategorie')
            ->where('zobrazovat', '1')
            ->order('poradi ASC');
    }

    /**
     * Get Selection for child categories of given parent
     */
    public function getChildrenSelection(int $parentId): Selection
    {
        return $this->database->table('fl_kategorie')
            ->where('nadrazena', $parentId)
            ->where('zobrazovat', '1')
            ->order('poradi ASC');
    }

    /**
     * Map database rows to BaseCategory entities
     *
     * @return BaseCategory[]
     */
    public function mapRowsToEntities(iterable $rows): array
    {
        $categories = [];
        foreach ($rows as $row) {
            $categories[] = $this->mapToEntity($row);
        }
        return $categories;
    }

    /**
     * Map database row to BaseCategory entity
     *
     * Czech → English mapping:
     * nadrazena → parentId, foto → photo,
     * zobrazovat → visible, poradi → position
     */
    private function mapToEntity(object $row): BaseCategory
    {
        return new BaseCategory(
            id: (int) $row->id,
            parentId: $row->nadrazena > 0 ? (int) $row->nadrazena : null,
            variantParameterGroupId: $row->variantParameterGroup_id ? (int) $row->variantParameterGroup_id : null,
            photo: $row->foto ?? '',
            heurekaFeed: $row->heurekaFeed ?? '',
            zboziFeed: $row->zboziFeed ?? '',
            googleFeed: $row->googleFeed ?? '',
            visible: $row->zobrazovat === '1',
            position: (int) $row->poradi,
            parameterGroups: $row->parameterGroups,
        );
    }
}