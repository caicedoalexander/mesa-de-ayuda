<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateSystemSettings extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/migrations/4/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('system_settings');
        $table->addColumn('setting_key', 'string', [
            'default' => null,
            'limit' => 100,
            'null' => false,
        ]);
        $table->addColumn('setting_value', 'text', [
            'default' => null,
            'null' => true,
        ]);
        $table->addColumn('setting_type', 'string', [
            'default' => 'string',
            'limit' => 50,
            'null' => false,
        ]);
        $table->addColumn('created', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'null' => false,
        ]);
        $table->addIndex(['setting_key'], ['unique' => true]);
        $table->create();
    }
}
