<?php declare(strict_types=1);

namespace App\Model\Parameter;

/**
 * ParameterGroup Entity
 *
 * Defines parameter types: item selection, free text, or numeric.
 *
 * Database table: es_parameterGroups
 */
class ParameterGroup
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly bool $isFreeText,
        public readonly bool $isFreeInteger,
        public readonly ?string $units,
    ) {}

    public function isItemBased(): bool
    {
        return !$this->isFreeText && !$this->isFreeInteger;
    }

    public function isNumeric(): bool
    {
        return $this->isFreeInteger;
    }

    public function isText(): bool
    {
        return $this->isFreeText;
    }

    /**
     * Get formatted label with units
     */
    public function getLabel(mixed $value): string
    {
        if ($this->units) {
            return $value . ' ' . $this->units;
        }

        return (string) $value;
    }
}