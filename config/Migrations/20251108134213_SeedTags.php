<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class SeedTags extends BaseMigration
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
        $data = [
            [
                'name' => 'Urgente',
                'color' => '#f44336',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Bug',
                'color' => '#e91e63',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Feature',
                'color' => '#9c27b0',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Soporte',
                'color' => '#3f51b5',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Consulta',
                'color' => '#2196f3',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Documentación',
                'color' => '#03a9f4',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Mejora',
                'color' => '#00bcd4',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Hardware',
                'color' => '#009688',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Software',
                'color' => '#4caf50',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Red',
                'color' => '#8bc34a',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Seguridad',
                'color' => '#ff5722',
                'created' => date('Y-m-d H:i:s'),
            ],
            [
                'name' => 'Acceso',
                'color' => '#795548',
                'created' => date('Y-m-d H:i:s'),
            ],
        ];

        $table = $this->table('tags');
        $table->insert($data)->save();
    }

    public function down(): void
    {
        $this->execute('DELETE FROM tags');
    }
}
