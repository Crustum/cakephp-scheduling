# API Reference

This document provides a comprehensive reference for all classes, methods, and interfaces provided by the Scheduling plugin.

## Table of Contents

- [Schedule Class](#schedule-class)
  - [Schedule Class Methods](#schedule-class-methods)
  - [Dynamic Methods](#dynamic-methods)
- [Event Class](#event-class)
  - [Frequency Methods](#frequency-methods)
  - [Constraint Methods](#constraint-methods)
  - [Configuration Methods](#configuration-methods)
  - [Hook Methods](#hook-methods)
  - [Output Methods](#output-methods)
  - [Execution Methods](#execution-methods)
  - [Information Methods](#information-methods)
  - [Monitoring Methods](#monitoring-methods)
- [Console Commands](#console-commands)
  - [ScheduleRunCommand](#scheduleruncommand)
  - [ScheduleWorkCommand](#scheduleworkcommand)
  - [ScheduleListCommand](#schedulelistcommand)
  - [SchedulePauseCommand](#schedulepausecommand)
  - [ScheduleResumeCommand](#scheduleresumecommand)
  - [ScheduleInterruptCommand](#scheduleinterruptcommand)
  - [ScheduleTestCommand](#scheduletestcommand)
  - [ScheduleClearCacheCommand](#scheduleclearcachecommand)
  - [ScheduleFinishCommand](#schedulefinishcommand)
- [Monitoring Commands](#monitoring-commands)
  - [ScheduleMonitorSyncCommand](#schedulemonitorsynccommand)
  - [ScheduleMonitorListCommand](#schedulemonitorlistcommand)
  - [ScheduleMonitorPruneCommand](#schedulemonitorprunecommand)
- [Events](#events)
  - [Event Data](#event-data)
- [Interfaces](#interfaces)
  - [EventMutexInterface](#eventmutexinterface)
  - [SchedulingMutexInterface](#schedulingmutexinterface)
- [Constants](#constants)
  - [Usage Example](#usage-example)
- [Configuration](#configuration)
  - [Scheduling Configuration](#scheduling-configuration)

## Schedule Class

The main scheduling class that manages all scheduled tasks.

### Schedule Class Methods

| Method | Description |
| --- | --- |
| `call($callback, array $parameters = [])` | Schedule a closure or callable to be executed |
| `command(string $command, array $parameters = [])` | Schedule a CakePHP console command |
| `exec(string $command, array $parameters = [])` | Schedule a shell command |
| `group(Closure $events)` | Create a group of scheduled tasks with shared configuration |
| `dueEvents()` | Get all events that are due to run |
| `events()` | Get all events on the schedule |
| `serverShouldRun(Event $event, DateTimeInterface $time)` | Determine if the server should run the given event |
| `useCache(string $store)` | Specify the cache store for task coordination |
| `pause()` | Pause scheduled task processing (cache flag) |
| `resume()` | Resume scheduled task processing |
| `interrupt()` | Broadcast an interrupt for the current schedule run |
| `isPaused()` | Determine if the scheduler is paused |
| `withoutInterruptionPolling()` | Disable pause/interrupt cache checks for this process |

### Dynamic Methods

The Schedule class supports dynamic method calls that are forwarded to `PendingEventAttributes`. This enables method chaining for frequency and constraint methods:

```php
$schedule->daily()->onOneServer()->group(function () {
    $schedule->command('emails send');
    $schedule->command('reports generate');
});
```

## Event Class

Represents a scheduled task event.

### Frequency Methods

| Method | Description |
| --- | --- |
| `cron(string $expression)` | Set a custom cron expression |
| `everySecond()` | Run the task every second |
| `everyTwoSeconds()` | Run the task every two seconds |
| `everyFiveSeconds()` | Run the task every five seconds |
| `everyTenSeconds()` | Run the task every ten seconds |
| `everyFifteenSeconds()` | Run the task every fifteen seconds |
| `everyTwentySeconds()` | Run the task every twenty seconds |
| `everyThirtySeconds()` | Run the task every thirty seconds |
| `everyMinute()` | Run the task every minute |
| `everyTwoMinutes()` | Run the task every two minutes |
| `everyThreeMinutes()` | Run the task every three minutes |
| `everyFourMinutes()` | Run the task every four minutes |
| `everyFiveMinutes()` | Run the task every five minutes |
| `everyTenMinutes()` | Run the task every ten minutes |
| `everyFifteenMinutes()` | Run the task every fifteen minutes |
| `everyThirtyMinutes()` | Run the task every thirty minutes |
| `hourly()` | Run the task every hour |
| `hourlyAt($offset)` | Run the task every hour at the given offset |
| `everyOddHour($offset = 0)` | Run the task every odd hour |
| `everyTwoHours($offset = 0)` | Run the task every two hours |
| `everyThreeHours($offset = 0)` | Run the task every three hours |
| `everyFourHours($offset = 0)` | Run the task every four hours |
| `everySixHours($offset = 0)` | Run the task every six hours |
| `daily()` | Run the task every day at midnight |
| `at(string $time)` | Schedule the command at a given time |
| `dailyAt(string $time)` | Run the task daily at a given time |
| `twiceDaily(int $first = 1, int $second = 13)` | Run the task twice daily |
| `twiceDailyAt(int $first, int $second, int $offset)` | Run the task twice daily at given offset |
| `weekly()` | Run the task every week |
| `weeklyOn($dayOfWeek, string $time = '0:0')` | Run the task weekly on given day and time |
| `monthly()` | Run the task every month |
| `monthlyOn(int $dayOfMonth = 1, string $time = '0:0')` | Run the task monthly on given day |
| `twiceMonthly(int $first = 1, int $second = 16, string $time = '0:0')` | Run the task twice monthly |
| `daysOfMonth(array|int ...$days)` | Run the task on specific days of the month (for example `daysOfMonth([1, 10, 20])` or `daysOfMonth(1, 10, 20)`) |
| `lastDayOfMonth(string $time = '0:0')` | Run the task on the last day of the month |
| `quarterly()` | Run the task every quarter |
| `quarterlyOn(int $dayOfQuarter = 1, string $time = '0:0')` | Run the task quarterly on given day |
| `yearly()` | Run the task every year |
| `yearlyOn(int $month = 1, $dayOfMonth = 1, string $time = '0:0')` | Run the task yearly on given date |

### Constraint Methods

| Method | Description |
| --- | --- |
| `between(string $startTime, string $endTime)` | Constrain the task to run between start and end times |
| `unlessBetween(string $startTime, string $endTime)` | Constrain the task to not run between start and end times |
| `weekdays()` | Constrain the task to run only on weekdays |
| `weekends()` | Constrain the task to run only on weekends |
| `mondays()` | Constrain the task to run only on Mondays |
| `tuesdays()` | Constrain the task to run only on Tuesdays |
| `wednesdays()` | Constrain the task to run only on Wednesdays |
| `thursdays()` | Constrain the task to run only on Thursdays |
| `fridays()` | Constrain the task to run only on Fridays |
| `saturdays()` | Constrain the task to run only on Saturdays |
| `sundays()` | Constrain the task to run only on Sundays |
| `days($days)` | Constrain the task to run only on specific days |
| `timezone($timezone)` | Set the timezone for this task |

### Configuration Methods

| Method | Description |
| --- | --- |
| `name(string $name)` | Assign a name to the scheduled task |
| `withoutOverlapping(int $expiresAt = 1440, bool $releaseOnTerminationSignals = true)` | Prevent the task from overlapping |
| `evenWhenPaused()` | Allow the task to run while the scheduler is paused |
| `onOneServer()` | Ensure the task runs on only one server |
| `runInBackground()` | Run the task in the background |
| `when(callable $callback)` | Constrain the task based on a truth test |
| `skip(callable $callback)` | Skip the task based on a truth test |

### Hook Methods

| Method | Description |
| --- | --- |
| `before(callable $callback)` | Register a callback to run before the task |
| `after(callable $callback)` | Register a callback to run after the task |
| `onSuccess(callable $callback)` | Register a callback to run when the task succeeds |
| `onFailure(callable $callback)` | Register a callback to run when the task fails |

### Output Methods

| Method | Description |
| --- | --- |
| `sendOutputTo(string $location, bool $append = false)` | Send the output to a file |
| `appendOutputTo(string $location)` | Append the output to a file |


### Execution Methods

| Method | Description |
| --- | --- |
| `run()` | Execute the scheduled task |
| `isDue()` | Determine if the task is due to run |
| `filtersPass()` | Determine if all filters pass for the task |
| `shouldSkipDueToOverlapping()` | Determine if the task should be skipped due to overlapping |

### Information Methods

| Method | Description |
| --- | --- |
| `getSummaryForDisplay()` | Get a human-readable summary of the task |
| `getExpression()` | Get the cron expression for the task |
| `mutexName()` | Get the mutex name for the task |

### Monitoring Methods

| Method | Description |
| --- | --- |
| `useMonitoring()` | Enable monitoring for this task |
| `disableMonitoring()` | Disable monitoring for this task |
| `monitorName(string $name)` | Set a custom monitor name for this task |
| `graceTimeInMinutes(int $minutes)` | Set grace time in minutes for overdue detection |
| `storeOutputInDb(bool $store = true)` | Enable storing command output in database |
| `shouldMonitor()` | Check if this task should be monitored |
| `shouldStoreOutputInDb()` | Check if output should be stored in database |
| `getMonitorName()` | Get the custom monitor name |
| `getGraceTimeInMinutes()` | Get the grace time in minutes |

## Console Commands

### ScheduleRunCommand

Run scheduled tasks that are due (typically called by cron).

```bash
bin/cake schedule run
```

### ScheduleWorkCommand

Run the scheduler continuously for development.

```bash
bin/cake schedule work [options]
```

**Options:**
- `--interval, -i` - Interval between runs in seconds (default: 60)
- `--max-runs, -m` - Maximum runs before stopping (0 = unlimited)
- `--verbose, -v` - Enable verbose output

### ScheduleListCommand

List all scheduled tasks and their next run times.

```bash
bin/cake schedule list [--timezone=TIMEZONE]
```

**Options:**
- `--timezone` - Display cron expressions and next-run times in this timezone

### SchedulePauseCommand

Pause scheduled task processing without changing deployed schedule definitions.

```bash
bin/cake schedule pause
```

### ScheduleResumeCommand

Resume scheduled task processing after a pause.

```bash
bin/cake schedule resume
```

### ScheduleInterruptCommand

Interrupt the current schedule run so sub-minute (repeatable) tasks stop for the remainder of the minute.

```bash
bin/cake schedule interrupt
```

### ScheduleTestCommand

Test execution of scheduled tasks.

```bash
bin/cake schedule test [task_name]
```

### ScheduleClearCacheCommand

Clear the scheduling cache.

```bash
bin/cake schedule clear
```

### ScheduleFinishCommand

Finish a scheduled task execution.

```bash
bin/cake schedule finish [task_id] [exit_code]
```

## Monitoring Commands

### ScheduleMonitorSyncCommand

Syncs the schedule with the monitoring database.

**Usage:**
```bash
bin/cake schedule monitor sync [--keep-old] [--dry-run]
```

**Options:**
- `--keep-old` - Keep existing monitored tasks not in current schedule
- `--dry-run` - Show what would be synced without making changes

### ScheduleMonitorListCommand

Lists all monitored scheduled tasks with their status.

**Usage:**
```bash
bin/cake schedule monitor list [--status=STATUS] [--type=TYPE] [--recent=HOURS] [--format=FORMAT]
```

**Options:**
- `--status` - Filter by status (running, failed, completed, overdue)
- `--type` - Filter by task type
- `--recent` - Show only tasks with recent activity (hours)
- `--format` - Output format (table, json)

### ScheduleMonitorPruneCommand

Prunes old monitoring data.

**Usage:**
```bash
bin/cake schedule monitor prune [--days=DAYS]
```

**Options:**
- `--days` - Days to keep (default: 7)

## Events

The plugin dispatches the following events during task execution:

| Event Name | Description |
| --- | --- |
| `Crustum\Scheduling\Event\ScheduledTaskStarting` | Dispatched when a scheduled task is about to start |
| `Crustum\Scheduling\Event\ScheduledTaskFinished` | Dispatched when a scheduled task has finished |
| `Crustum\Scheduling\Event\ScheduledTaskSkipped` | Dispatched when a scheduled task is skipped |
| `Crustum\Scheduling\Event\ScheduledTaskFailed` | Dispatched when a scheduled task fails |
| `Crustum\Scheduling\Event\SchedulePaused` | Dispatched when scheduled task processing is paused |
| `Crustum\Scheduling\Event\ScheduleResumed` | Dispatched when scheduled task processing is resumed |

### Event Data

Each event includes the following data:

- `event` - The `Event` instance
- `output` - The task output (for finished/failed events)
- `runtime` - The execution time in milliseconds (for finished events)
- `exception` - The exception that caused the failure (for failed events)
- `reason` - The reason for skipping (for skipped events)

## Interfaces

### EventMutexInterface

Interface for implementing custom mutex implementations for overlap prevention.

**Methods:**
- `create(Event $event): bool` - Create a mutex for the given event
- `exists(Event $event): bool` - Check if a mutex exists for the given event
- `forget(Event $event): bool` - Remove the mutex for the given event

### SchedulingMutexInterface

Interface for implementing scheduling mutex functionality for single-server execution.

**Methods:**
- `create(Event $event, DateTimeInterface $time): bool` - Create a scheduling mutex
- `exists(Event $event, DateTimeInterface $time): bool` - Check if a scheduling mutex exists
- `forget(Event $event, DateTimeInterface $time): bool` - Remove a scheduling mutex

## Constants

The Schedule class provides day constants for use with scheduling methods:

| Constant | Value | Description |
| --- | --- | --- |
| `Schedule::SUNDAY` | 0 | Sunday |
| `Schedule::MONDAY` | 1 | Monday |
| `Schedule::TUESDAY` | 2 | Tuesday |
| `Schedule::WEDNESDAY` | 3 | Wednesday |
| `Schedule::THURSDAY` | 4 | Thursday |
| `Schedule::FRIDAY` | 5 | Friday |
| `Schedule::SATURDAY` | 6 | Saturday |

### Usage Example

```php
$schedule->command('weekly report')
    ->weeklyOn(Schedule::MONDAY, '09:00');
```

## Configuration

### Scheduling Configuration

Publish `config/scheduling.php` with `bin/cake manifest install --plugin Crustum/Scheduling`, then configure monitoring behavior:

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

**Configuration Options:**
- `delete_log_items_older_than_days` - Number of days to keep log items (default: 30)
- `date_format` - Date format for display (default: 'Y-m-d H:i:s')
- `grace_time_in_minutes` - Default grace time for overdue detection (default: 5)
- `store_output_in_db` - Whether to store command output in database (default: false)

This API reference reflects the actual implementation of the Scheduling plugin. For usage examples and integration guides, see the [Home](Home.md) and [Integration](Integration.md) documentation.
