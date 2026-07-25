<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateMonitoredScheduledTasksTable extends BaseMigration
{
    /**
     * Change Method.
     *
     * More information on this method is available here:
     * https://book.cakephp.org/5/en/migrations.html#the-change-method
     *
     * @return void
     */
    public function change(): void
    {
        $table = $this->table('monitored_scheduled_tasks', [
            'id' => false,
            'primary_key' => ['id']
        ]);

        $table->addColumn('id', 'biginteger', [
            'autoIncrement' => true,
            'default' => null,
            'limit' => null,
            'null' => false,
            'signed' => false,
        ]);

        $table->addColumn('name', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);

        $table->addColumn('type', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => true,
        ]);

        $table->addColumn('cron_expression', 'string', [
            'default' => null,
            'limit' => 255,
            'null' => false,
        ]);

        $table->addColumn('timezone', 'string', [
            'default' => null,
            'limit' => 50,
            'null' => true,
        ]);

        $table->addColumn('grace_time_in_minutes', 'integer', [
            'default' => 5,
            'limit' => null,
            'null' => false,
        ]);

        $table->addColumn('last_started', 'datetime', [
            'default' => null,
            'limit' => null,
            'null' => true,
        ]);

        $table->addColumn('last_finished', 'datetime', [
            'default' => null,
            'limit' => null,
            'null' => true,
        ]);

        $table->addColumn('last_failed', 'datetime', [
            'default' => null,
            'limit' => null,
            'null' => true,
        ]);

        $table->addColumn('last_skipped', 'datetime', [
            'default' => null,
            'limit' => null,
            'null' => true,
        ]);

        $table->addColumn('created', 'datetime', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);

        $table->addColumn('modified', 'datetime', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);

        $table->addIndex([
            'name',
        ], [
            'name' => 'idx_name',
            'unique' => false,
        ]);

        $table->addIndex([
            'last_started',
        ], [
            'name' => 'idx_last_started',
            'unique' => false,
        ]);

        $table->addIndex([
            'last_finished',
        ], [
            'name' => 'idx_last_finished',
            'unique' => false,
        ]);

        $table->create();
    }
}
