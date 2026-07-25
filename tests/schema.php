<?php
declare(strict_types=1);

/**
 * Test database schema for Scheduling plugin tests.
 *
 * This format resembles the existing fixture schema
 * and is converted to SQL via the Schema generation
 * features of the Database package.
 */
return [
    [
        'table' => 'monitored_scheduled_tasks',
        'columns' => [
            'id' => [
                'type' => 'integer',
                'autoIncrement' => true,
            ],
            'name' => [
                'type' => 'string',
                'length' => 255,
                'null' => false,
            ],
            'type' => [
                'type' => 'string',
                'length' => 50,
                'null' => true,
            ],
            'cron_expression' => [
                'type' => 'string',
                'length' => 255,
                'null' => false,
            ],
            'timezone' => [
                'type' => 'string',
                'length' => 50,
                'null' => true,
            ],
            'grace_time_in_minutes' => [
                'type' => 'integer',
                'default' => 5,
                'null' => false,
            ],
            'last_started' => [
                'type' => 'datetime',
                'null' => true,
            ],
            'last_finished' => [
                'type' => 'datetime',
                'null' => true,
            ],
            'last_failed' => [
                'type' => 'datetime',
                'null' => true,
            ],
            'last_skipped' => [
                'type' => 'datetime',
                'null' => true,
            ],
            'created' => [
                'type' => 'datetime',
                'null' => false,
            ],
            'modified' => [
                'type' => 'datetime',
                'null' => false,
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
        ],
        'indexes' => [
            'idx_name' => [
                'type' => 'index',
                'columns' => [
                    'name',
                ],
            ],
            'idx_last_started' => [
                'type' => 'index',
                'columns' => [
                    'last_started',
                ],
            ],
            'idx_last_finished' => [
                'type' => 'index',
                'columns' => [
                    'last_finished',
                ],
            ],
        ],
    ],
    [
        'table' => 'monitored_scheduled_task_log_items',
        'columns' => [
            'id' => [
                'type' => 'integer',
                'autoIncrement' => true,
            ],
            'monitored_scheduled_task_id' => [
                'type' => 'biginteger',
                'null' => false,
                'unsigned' => true,
            ],
            'type' => [
                'type' => 'string',
                'length' => 20,
                'null' => false,
            ],
            'meta' => [
                'type' => 'json',
                'null' => true,
            ],
            'created' => [
                'type' => 'datetime',
                'null' => false,
            ],
        ],
        'constraints' => [
            'primary' => [
                'type' => 'primary',
                'columns' => [
                    'id',
                ],
            ],
            'monitored_scheduled_task_log_items_ibfk_1' => [
                'type' => 'foreign',
                'columns' => [
                    'monitored_scheduled_task_id',
                ],
                'references' => [
                    'monitored_scheduled_tasks',
                    'id',
                ],
                'update' => 'cascade',
                'delete' => 'cascade',
            ],
        ],
        'indexes' => [
            'idx_monitored_scheduled_task_id' => [
                'type' => 'index',
                'columns' => [
                    'monitored_scheduled_task_id',
                ],
            ],
            'idx_task_type' => [
                'type' => 'index',
                'columns' => [
                    'monitored_scheduled_task_id',
                    'type',
                ],
            ],
            'idx_created' => [
                'type' => 'index',
                'columns' => [
                    'created',
                ],
            ],
        ],
    ],
];
