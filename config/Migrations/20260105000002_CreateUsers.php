<?php
declare(strict_types=1);

use Migrations\AbstractMigration;

/**
 * CreateUsers Migration
 *
 * Creates the users table for authentication and authorization.
 *
 * User Roles:
 * - admin: Full system access, configuration management
 * - agent: Handle tickets, internal support
 * - compras: Purchase request processing
 * - servicio_cliente: External PQRS handling
 * - requester: Basic user, can create tickets
 *
 * Features:
 * - Email-based login (NOT username)
 * - Auto-created users from Gmail integration (password = NULL initially)
 * - Organization association for multi-tenancy
 * - Profile image support
 *
 * @version 1.0.0 - Initial version (2026-01-05)
 */
class CreateUsers extends AbstractMigration
{
    /**
     * Create users table with complete schema
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('users', ['signed' => false]);

        $table
            // Authentication credentials
            ->addColumn('email', 'string', [
                'limit' => 255,
                'null' => false,
                'comment' => 'Email address (used for login)',
            ])
            ->addColumn('password', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Hashed password (NULL for auto-created users from Gmail)',
            ])

            // User details
            ->addColumn('first_name', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'First name',
            ])
            ->addColumn('last_name', 'string', [
                'limit' => 100,
                'null' => false,
                'comment' => 'Last name',
            ])

            // Authorization
            ->addColumn('role', 'string', [
                'limit' => 20,
                'default' => 'requester',
                'null' => false,
                'comment' => 'User role for authorization (admin|agent|compras|servicio_cliente|requester)',
            ])

            // Organization relationship (multi-tenancy)
            ->addColumn('organization_id', 'integer', [
                'null' => true,
                'signed' => false,
                'comment' => 'Associated organization (optional)',
            ])

            // Profile
            ->addColumn('profile_image', 'string', [
                'limit' => 255,
                'null' => true,
                'comment' => 'Path to profile photo',
            ])
            ->addColumn('is_active', 'boolean', [
                'default' => true,
                'null' => false,
                'comment' => 'Account status (active/inactive)',
            ])

            // Timestamps
            ->addColumn('created', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Account creation timestamp',
            ])
            ->addColumn('modified', 'datetime', [
                'default' => null,
                'null' => true,
                'comment' => 'Last modification timestamp',
            ])

            // Indexes for performance
            ->addIndex(['email'], [
                'unique' => true,
                'name' => 'idx_email_unique',
            ])
            ->addIndex(['organization_id'], [
                'name' => 'idx_users_organization_id',
            ])
            ->addIndex(['role'], [
                'name' => 'idx_users_role',
            ])
            ->addIndex(['is_active'], [
                'name' => 'idx_users_is_active',
            ])

            // Composite index for common queries
            ->addIndex(['role', 'is_active'], [
                'name' => 'idx_users_role_active',
            ])

            // Foreign key constraint
            ->addForeignKey('organization_id', 'organizations', 'id', [
                'delete' => 'SET_NULL',
                'update' => 'CASCADE',
                'constraint' => 'fk_users_organization',
            ])

            ->create();
    }
}
