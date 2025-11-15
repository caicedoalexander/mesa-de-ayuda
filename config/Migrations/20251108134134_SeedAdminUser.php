<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class SeedAdminUser extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function up(): void
    {
        // Crear organización de ejemplo
        $orgTable = $this->table('organizations');
        $orgTable->insert([
            [
                'name' => 'Soporte Interno',
                'domain' => 'localhost',
                'created' => date('Y-m-d H:i:s'),
            ]
        ])->save();

        // Obtener el ID de la organización creada
        $org = $this->fetchRow('SELECT id FROM organizations WHERE name = "Soporte Interno"');
        $orgId = $org['id'];

        // Hash de la contraseña 'admin123' usando password_hash
        $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);

        // Crear usuario administrador
        $usersTable = $this->table('users');
        $usersTable->insert([
            [
                'email' => 'admin@localhost',
                'password' => $passwordHash,
                'name' => 'Administrador',
                'phone' => null,
                'role' => 'admin',
                'organization_id' => $orgId,
                'is_active' => true,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ],
            [
                'email' => 'agente@localhost',
                'password' => $passwordHash,
                'name' => 'Agente de Soporte',
                'phone' => null,
                'role' => 'agent',
                'organization_id' => $orgId,
                'is_active' => true,
                'created' => date('Y-m-d H:i:s'),
                'modified' => date('Y-m-d H:i:s'),
            ]
        ])->save();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM users WHERE email IN ("admin@localhost", "agente@localhost")');
        $this->execute('DELETE FROM organizations WHERE name = "Soporte Interno"');
    }
}
