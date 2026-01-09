<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateOrganizations Migration
 *
 * Creates the organizations table for multi-tenancy support.
 * Organizations represent different companies/departments using the system.
 *
 * Features:
 * - Email domain-based auto-assignment of users
 * - Isolation of data between organizations (future)
 *
 * @version 1.0.0 - Initial version (2026-01-05)
 */
class CreateOrganizations extends AbstractMigration
{
    /**
     * Create organizations table
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('organizations', ['signed' => false]);

        $table
            // Organization details
            ->addColumn('name', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Organization name',
            ])
            ->addColumn('domain', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Email domain for auto-assignment (e.g., company.com)',
            ])

            // Timestamps
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Creation timestamp',
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Last modification timestamp',
            ])

            // Indexes
            ->addIndex(['name'], [
                'name' => 'idx_organizations_name',
            ])
            ->addIndex(['domain'], [
                'name' => 'idx_organizations_domain',
            ])

            ->create();
    }
}
