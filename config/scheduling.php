<?php
declare(strict_types=1);

/**
 * Schedule Monitor Configuration
 *
 * Configuration for the Schedule Monitor plugin.
 */
return [
    'Scheduling' => [
        /**
         * The schedule monitor will log each start, finish and failure of all scheduled jobs.
         * After a while the `monitored_scheduled_task_log_items` might become big.
         * Here you can specify the amount of days log items should be kept.
         */
        'delete_log_items_older_than_days' => 30,

        /**
         * The date format used for all dates displayed on the output of commands
         * provided by this package.
         */
        'date_format' => 'Y-m-d H:i:s',

        /**
         * The default grace time in minutes for tasks.
         * Tasks that don't finish within this time are considered overdue.
         */
        'grace_time_in_minutes' => 5,

        /**
         * Whether to store task output in the database.
         */
        'store_output_in_db' => false,
    ],
];
