<?php
declare(strict_types=1);

use Migrations\BaseMigration;

class CreateMonitoredScheduledTaskLogItemsTable extends BaseMigration
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
        $table = $this->table('monitored_scheduled_task_log_items', [
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

        $table->addColumn('monitored_scheduled_task_id', 'biginteger', [
            'default' => null,
            'limit' => null,
            'null' => false,
            'signed' => false,
        ]);

        $table->addColumn('type', 'string', [
            'default' => null,
            'limit' => 20,
            'null' => false,
        ]);

        $table->addColumn('meta', 'json', [
            'default' => null,
            'null' => true,
        ]);

        $table->addColumn('created', 'datetime', [
            'default' => null,
            'limit' => null,
            'null' => false,
        ]);

        $table->addIndex([
            'monitored_scheduled_task_id',
        ], [
            'name' => 'idx_monitored_scheduled_task_id',
            'unique' => false,
        ]);

        $table->addIndex([
            'monitored_scheduled_task_id',
            'type',
        ], [
            'name' => 'idx_task_type',
            'unique' => false,
        ]);

        $table->addIndex([
            'created',
        ], [
            'name' => 'idx_created',
            'unique' => false,
        ]);

        $table->addForeignKey('monitored_scheduled_task_id', 'monitored_scheduled_tasks', 'id', [
            'update' => 'CASCADE',
            'delete' => 'CASCADE',
        ]);

        $table->create();
    }
}
