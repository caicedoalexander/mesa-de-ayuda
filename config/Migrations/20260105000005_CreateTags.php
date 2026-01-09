<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateTags Migration
 *
 * Creates the tags table for categorization of tickets, PQRS, and other entities.
 * Tags can be manually assigned or automatically suggested via n8n AI integration.
 *
 * Features:
 * - Color-coded for visual identification
 * - Active/inactive status for archiving unused tags
 * - Shared across all modules (Tickets, PQRS, future expansions)
 *
 * Common tag examples:
 * - Hardware, Software, Network
 * - Urgent, High Priority
 * - Customer Request, Internal Issue
 * - Bug, Feature Request
 *
 * @version 1.0.0 - Initial version (2026-01-05)
 */
class CreateTags extends AbstractMigration
{
    /**
     * Create tags table
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('tags', ['signed' => false]);

        $table
            // Tag details
            ->addColumn('name', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Tag name (unique)',
            ])
            ->addColumn('color', 'string', [
                'limit' => 7,
                'default' => '#3498db',
                'null' => false,
                'comment' => 'Hex color code for UI display (e.g., #FF5733)',
            ])

            // Status
            ->addColumn('is_active', 'boolean', [
                'default' => true,
                'null' => false,
                'comment' => 'Active status (inactive tags are hidden from selection)',
            ])

            // Timestamps
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Tag creation timestamp',
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Last modification timestamp',
            ])

            // Indexes
            ->addIndex(['name'], [
                'unique' => true,
                'name' => 'idx_name_unique',
            ])
            ->addIndex(['is_active'], [
                'name' => 'idx_tags_is_active',
            ])

            ->create();
    }
}
