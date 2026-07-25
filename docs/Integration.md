# Integration

## Table of Contents

- [Schedule Constraints](#schedule-constraints)
- [Basic Integration](#basic-integration)
- [Defining Scheduled Tasks](#defining-scheduled-tasks)
  - [Command Scheduling](#command-scheduling)
  - [Closure Scheduling](#closure-scheduling)
  - [Shell Command Scheduling](#shell-command-scheduling)
- [Frequency Options](#frequency-options)
  - [Second-Based Frequencies](#second-based-frequencies)
  - [Minute-Based Frequencies](#minute-based-frequencies)
  - [Hour-Based Frequencies](#hour-based-frequencies)
  - [Daily Frequencies](#daily-frequencies)
  - [Weekly and Monthly](#weekly-and-monthly)
  - [Custom Cron Expressions](#custom-cron-expressions)
- [Task Constraints](#task-constraints)
  - [Time-Based Constraints](#time-based-constraints)
  - [Day-Based Constraints](#day-based-constraints)
  - [Conditional Constraints](#conditional-constraints)
- [Preventing Task Overlaps](#preventing-task-overlaps)
- [Single-Server Execution](#single-server-execution)
- [Background Execution](#background-execution)
- [Task Hooks](#task-hooks)
  - [Before and After Hooks](#before-and-after-hooks)
  - [Success and Failure Hooks](#success-and-failure-hooks)
- [Sub-Minute Tasks](#sub-minute-tasks)
  - [Repeatable Tasks](#repeatable-tasks)
- [Event Integration](#event-integration)
- [Running the Scheduler](#running-the-scheduler)
  - [Production Setup](#production-setup)
  - [Development Mode](#development-mode)
  - [Testing Scheduled Tasks](#testing-scheduled-tasks)
- [Configuration Options](#configuration-options)
  - [Timezone Configuration](#timezone-configuration)
  - [Cache Configuration](#cache-configuration)
  - [Mutex Configuration](#mutex-configuration)
- [Task Output](#task-output)
- [Task Monitoring](#task-monitoring)
  - [Enabling Monitoring](#enabling-monitoring)
  - [Monitoring Configuration](#monitoring-configuration)
  - [Monitoring Methods](#monitoring-methods)
  - [Monitoring Commands](#monitoring-commands)
- [Events](#events)

<a name="schedule-constraints"></a>
## Schedule Constraints

The scheduler provides many constraint methods to fine-tune when your tasks should run.

## Basic Integration

The recommended way to define scheduled tasks is using the event manager during container building:

```php
use Cake\Event\EventManager;
use Crustum\Scheduling\Schedule;

$eventManager = EventManager::instance();
$eventManager->on('Application.buildContainer', function ($event): void {
    $container = $event->getData('container');
    $schedule = $container->get(Schedule::class);

    // Define your scheduled tasks here
    $schedule->command('cache clear')->everyMinute();
});
```

This approach ensures that your scheduled tasks are defined when the application container is built, making them available to all scheduling commands.

## Defining Scheduled Tasks

### Command Scheduling

Schedule CakePHP console commands using the `command` method:

```php
// Schedule a command every minute
$schedule->command('emails send')->everyMinute();

// Schedule with arguments
$schedule->command('reports generate --format=pdf')->daily();

// Schedule using command class
$schedule->command(\App\Command\BackupCommand::class)->weekly();
```

### Closure Scheduling

Schedule closures for simple tasks:

```php
$schedule->call(function () {
    // Clear temporary files
    $filesystem = new \Cake\Filesystem\Folder(TMP);
    $filesystem->delete();
})->daily();
```

### Shell Command Scheduling

Execute system commands:

```php
$schedule->exec('php /path/to/script.php')->hourly();

$schedule->exec('mysqldump -u user -p database > backup.sql')
    ->dailyAt('02:00');
```

## Frequency Options

The plugin provides a comprehensive set of frequency methods:

### Second-Based Frequencies

```php
$schedule->command('monitor check')->everySecond();
$schedule->command('health check')->everyFiveSeconds();
$schedule->command('queue process')->everyTenSeconds();
$schedule->command('metrics collect')->everyFifteenSeconds();
$schedule->command('status update')->everyThirtySeconds();
```

### Minute-Based Frequencies

```php
$schedule->command('cache warm')->everyMinute();
$schedule->command('data sync')->everyFiveMinutes();
$schedule->command('log rotate')->everyFifteenMinutes();
$schedule->command('cleanup temp')->everyThirtyMinutes();
```

### Hour-Based Frequencies

```php
$schedule->command('reports hourly')->hourly();
$schedule->command('backup incremental')->everyTwoHours();
$schedule->command('analytics process')->everyFourHours();
```

### Daily Frequencies

```php
$schedule->command('reports daily')->daily();
$schedule->command('backup full')->dailyAt('02:00');
$schedule->command('cleanup logs')->dailyAt('03:30');
```

### Weekly and Monthly

```php
$schedule->command('reports weekly')->weekly();
$schedule->command('reports monthly')->monthly();
$schedule->command('maintenance')->weeklyOn(1, '08:00'); // Monday at 8 AM
```

### Custom Cron Expressions

```php
$schedule->command('custom task')->cron('0 */6 * * *'); // Every 6 hours
```

## Task Constraints

### Time-Based Constraints

```php
// Run only between 9 AM and 5 PM
$schedule->command('business task')
    ->hourly()
    ->between('09:00', '17:00');

// Skip execution between midnight and 6 AM
$schedule->command('maintenance task')
    ->hourly()
    ->unlessBetween('00:00', '06:00');
```

### Day-Based Constraints

```php
// Run only on weekdays
$schedule->command('business report')
    ->daily()
    ->weekdays();

// Run only on weekends
$schedule->command('maintenance')
    ->hourly()
    ->weekends();

// Run on specific days
$schedule->command('weekly report')
    ->daily()
    ->days([1, 3, 5]); // Monday, Wednesday, Friday
```

### Conditional Constraints

```php
// Run only if condition is true
$schedule->command('conditional task')
    ->daily()
    ->when(function () {
        return file_exists('/tmp/run_task');
    });

// Skip if condition is true
$schedule->command('normal task')
    ->hourly()
    ->skip(function () {
        return app()->isDownForMaintenance();
    });
```

## Preventing Task Overlaps

Prevent tasks from running if the previous instance is still executing:

```php
$schedule->command('long running task')
    ->everyMinute()
    ->withoutOverlapping();

// Custom overlap timeout (in minutes)
$schedule->command('data import')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);
```

## Single-Server Execution

Ensure tasks run on only one server in multi-server environments:

```php
$schedule->command('backup database')
    ->daily()
    ->onOneServer();

// Named single-server tasks
$schedule->command('process queue high')
    ->everyMinute()
    ->onOneServer()
    ->name('process-high-priority');
```

## Background Execution

Run tasks in the background to prevent blocking:

```php
$schedule->command('heavy processing')
    ->everyFiveMinutes()
    ->runInBackground();
```

## Task Hooks

### Before and After Hooks

```php
$schedule->command('data processing')
    ->hourly()
    ->before(function () {
        // Log task start
        \Cake\Log\Log::info('Starting data processing');
    })
    ->after(function () {
        // Log task completion
        \Cake\Log\Log::info('Data processing completed');
    });
```

### Success and Failure Hooks

```php
$schedule->command('critical task')
    ->daily()
    ->onSuccess(function () {
        // Send success notification
        \Cake\Log\Log::info('Critical task completed successfully');
    })
    ->onFailure(function () {
        // Send failure alert
        \Cake\Log\Log::error('Critical task failed');
    });
```

## Sub-Minute Tasks

The plugin supports sub-minute scheduling for high-frequency tasks:

```php
// Process queue items every 5 seconds
$schedule->command('queue work')
    ->everyFiveSeconds()
    ->withoutOverlapping(1);

// Monitor system health every 10 seconds
$schedule->call(function () {
    // Check system metrics
})->everyTenSeconds();
```

### Repeatable Tasks

For tasks that need to run multiple times within a minute:

```php
$schedule->command('monitor check')
    ->everyMinute()
    ->repeatEvery(5); // Repeat every 5 seconds within the minute
```

## Event Integration

The plugin dispatches events during task execution that you can listen to:

```php
use Cake\Event\EventManager;
use Crustum\Scheduling\Event\ScheduledTaskStarting;
use Crustum\Scheduling\Event\ScheduledTaskFinished;

$eventManager = EventManager::instance();

$eventManager->on('Scheduling.taskStarting', function ($event) {
    $task = $event->getData('event');
    \Cake\Log\Log::info('Task starting: ' . $task->getSummaryForDisplay());
});

$eventManager->on('Scheduling.taskFinished', function ($event) {
    $task = $event->getData('event');
    $output = $event->getData('output');
    \Cake\Log\Log::info('Task finished: ' . $task->getSummaryForDisplay());
});
```

## Running the Scheduler

### Production Setup

Add a single cron entry to run the scheduler:

```bash
* * * * * cd /path/to/your/project && bin/cake schedule run >> /dev/null 2>&1
```

### Development Mode

For development, use the work command that runs continuously:

```bash
bin/cake schedule work
```

This command will:
- Run scheduled tasks as they become due
- Handle sub-minute tasks properly
- Provide verbose output for debugging
- Support graceful termination with Ctrl+C

### Testing Scheduled Tasks

Test your scheduled tasks without waiting:

```bash
# List all scheduled tasks
bin/cake schedule list

# Test a specific task
bin/cake schedule test "cache clear"

# Run all due tasks immediately
bin/cake schedule run
```

## Configuration Options

### Timezone Configuration

Set a default timezone for all scheduled tasks:

```php
$schedule->useTimezone('America/New_York');

// Or set per task
$schedule->command('reports generate')
    ->dailyAt('09:00')
    ->timezone('Europe/London');
```

### Cache Configuration

Configure cache store for task overlap prevention:

```php
$schedule->useCache('redis');
```

### Mutex Configuration

Custom mutex implementation for single-server execution:

```php
$schedule->useMutex(new \App\Scheduling\CustomMutex());
```

<a name="task-output"></a>
## Task Output

The scheduler provides several convenient methods for working with the output generated by scheduled tasks. First, using the `sendOutputTo` method, you may send the output to a file for later inspection:

```php
$schedule->command('emails send')
    ->daily()
    ->sendOutputTo($filePath);
```

If you would like to append the output to a given file, you may use the `appendOutputTo` method:

```php
$schedule->command('emails send')
    ->daily()
    ->appendOutputTo($filePath);
```

> **Note**
> The `sendOutputTo` and `appendOutputTo` methods are exclusive to the `command` and `exec` methods.

<a name="task-hooks"></a>
## Task Hooks

Using the `before` and `after` methods, you may specify code to be executed before and after the scheduled task is executed:

```php
$schedule->command('emails send')
    ->daily()
    ->before(function () {
        // The task is about to execute...
    })
    ->after(function () {
        // The task has executed...
    });
```

The `onSuccess` and `onFailure` methods allow you to specify code to be executed if the scheduled task succeeds or fails. A failure indicates that the scheduled console or system command terminated with a non-zero exit code:

```php
$schedule->command('emails send')
    ->daily()
    ->onSuccess(function () {
        // The task succeeded...
    })
    ->onFailure(function () {
        // The task failed...
    });
```

If output is available from your command, you may access it in your `after`, `onSuccess` or `onFailure` hooks by type-hinting a `string` parameter as the `$output` argument of your hook's closure definition:

```php
$schedule->command('emails send')
    ->daily()
    ->onSuccess(function (string $output) {
        // The task succeeded...
    })
    ->onFailure(function (string $output) {
        // The task failed...
    });
```


<a name="monitoring"></a>
## Task Monitoring

The Scheduling plugin includes comprehensive monitoring capabilities to track task execution, performance, and status.

### Enabling Monitoring

Enable monitoring for individual tasks using the `useMonitoring()` method:

```php
$schedule->command('emails send')
    ->daily()
    ->useMonitoring()
    ->monitorName('Email Sending Task')
    ->graceTimeInMinutes(10);
```

### Monitoring Configuration

Configure monitoring behavior for tasks:

```php
$schedule->command('data processing')
    ->hourly()
    ->useMonitoring()
    ->monitorName('Data Processing')
    ->graceTimeInMinutes(30)
    ->storeOutputInDb(true);
```

### Monitoring Methods

| Method | Description |
| --- | --- |
| `useMonitoring()` | Enable monitoring for this task |
| `disableMonitoring()` | Disable monitoring for this task |
| `monitorName(string $name)` | Set a custom monitor name |
| `graceTimeInMinutes(int $minutes)` | Set grace time for overdue detection |
| `storeOutputInDb(bool $store)` | Enable storing command output in database |

### Monitoring Commands

```bash
# Sync schedule with monitoring database
bin/cake schedule monitor sync

# List all monitored tasks
bin/cake schedule monitor list

# Filter by status
bin/cake schedule monitor list --status=running
bin/cake schedule monitor list --status=failed
bin/cake schedule monitor list --status=overdue

# Filter by type
bin/cake schedule monitor list --type=cake_command

# Show recent activity
bin/cake schedule monitor list --recent=24

# Output as JSON
bin/cake schedule monitor list --format=json

# Clean up old log data
bin/cake schedule monitor prune --days=7
```

### Monitoring Configuration

Publish `config/scheduling.php` via `bin/cake manifest install --plugin Crustum/Scheduling` (see [Installation](Installation.md)), then configure monitoring behavior:

```php
return [
    'Scheduling' => [
        'delete_log_items_older_than_days' => 30,
        'date_format' => 'Y-m-d H:i:s',
        'grace_time_in_minutes' => 5,
        'store_output_in_db' => false,
    ],
];
```

<a name="events"></a>
## Events

The scheduler dispatches a variety of events during the scheduling process. You may define listeners for any of the following events:

| Event Name |
| --- |
| `Crustum\Scheduling\Event\ScheduledTaskStarting` |
| `Crustum\Scheduling\Event\ScheduledTaskFinished` |
| `Crustum\Scheduling\Event\ScheduledTaskSkipped` |
| `Crustum\Scheduling\Event\ScheduledTaskFailed` |
| `Crustum\Scheduling\Event\SchedulePaused` |
| `Crustum\Scheduling\Event\ScheduleResumed` |

You may register event listeners for these events in your application's event manager:

```php
use Cake\Event\EventManager;
use Crustum\Scheduling\Event\ScheduledTaskStarting;
use Crustum\Scheduling\Event\ScheduledTaskFinished;

$eventManager = EventManager::instance();

$eventManager->on('Scheduling.taskStarting', function ($event) {
    $task = $event->getData('event');
    \Cake\Log\Log::info('Task starting: ' . $task->getSummaryForDisplay());
});

$eventManager->on('Scheduling.taskFinished', function ($event) {
    $task = $event->getData('event');
    $output = $event->getData('output');
    \Cake\Log\Log::info('Task finished: ' . $task->getSummaryForDisplay());
});
```

This integration guide covers all major features of the Scheduling plugin. For detailed API documentation, see the [API Reference](API-Reference.md).
