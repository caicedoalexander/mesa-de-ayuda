<?php
declare(strict_types=1);

namespace App\Model\Table\Traits;

/**
 * Number Generator Trait
 *
 * Provides generic sequential number generation for entities.
 * Eliminates ~60 lines of duplicated code across 3 tables.
 *
 * Resolves: MODEL-002 (generateXXXNumber() duplication)
 *
 * Usage:
 * 1. Use this trait in your Table class
 * 2. Define getNumberPrefix() to return the prefix (e.g., 'TKT', 'PQRS', 'CPR')
 * 3. Define getNumberField() to return the field name (e.g., 'ticket_number')
 * 4. Call generateNumber() to get the next sequential number
 */
trait NumberGeneratorTrait
{
    /**
     * Get the prefix for the number
     *
     * Must be implemented by each Table class
     *
     * @return string Prefix (e.g., 'TKT', 'PQRS', 'CPR')
     */
    abstract protected function getNumberPrefix(): string;

    /**
     * Get the field name that stores the number
     *
     * Must be implemented by each Table class
     *
     * @return string Field name (e.g., 'ticket_number', 'pqrs_number')
     */
    abstract protected function getNumberField(): string;

    /**
     * Generate the next sequential number
     *
     * Format: {PREFIX}-{YEAR}-{SEQUENCE}
     * Example: TKT-2025-00001, PQRS-2025-00001, CPR-2025-00001
     *
     * @return string Generated number
     */
    public function generateNumber(): string
    {
        $prefix = $this->getNumberPrefix();
        $field = $this->getNumberField();
        $year = date('Y');
        $fullPrefix = "{$prefix}-{$year}-";

        // Get last number for this year
        $lastEntity = $this->find()
            ->select([$field])
            ->where(["{$field} LIKE" => "{$fullPrefix}%"])
            ->orderBy([$field => 'DESC'])
            ->first();

        if ($lastEntity) {
            // Extract sequence number from last number and increment
            $lastNumber = (int) substr($lastEntity->get($field), -5);
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $fullPrefix . str_pad((string) $newNumber, 5, '0', STR_PAD_LEFT);
    }
}
