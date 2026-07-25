# Installation

## Composer

```
composer require crustum/cakephp-scheduling
```

## Load the Plugin

Ensure the Scheduling Plugin is loaded in your `src/Application.php` file (or via `bin/cake plugin load Crustum/Scheduling`):

```php
$this->addPlugin(\Crustum\Scheduling\SchedulingPlugin::class);
```

## Load SignalHandler Plugin (Required)

The Scheduling plugin requires the SignalHandler plugin for graceful termination support:

```php
$this->addPlugin(\SignalHandler\SignalHandlerPlugin::class);
```

The plugin automatically registers the scheduling services and integrates with CakePHP's console system and event manager. No additional configuration is required for basic scheduling functionality.

## Install Configuration (Plugin Manifest)

After the plugin is registered, install published assets with the Plugin Manifest system:

```bash
bin/cake manifest install --plugin Crustum/Scheduling
```

This will:

- Copy monitoring migrations into the application's `config/Migrations` directory
- Create `config/scheduling.php` (copy-safe; will not overwrite an existing file)
- Append loading of `config/scheduling.php` to `config/bootstrap.php` when the file exists

You can then run migrations for monitoring features:

```bash
bin/cake migrations migrate
```

Alternatively, migrate from the plugin path without publishing:

```bash
bin/cake migrations migrate -p Crustum/Scheduling
```

Published migrations and `config/scheduling.php` support:

- `monitored_scheduled_tasks` — task metadata and status
- `monitored_scheduled_task_log_items` — execution logs with metadata
- Monitoring options (`delete_log_items_older_than_days`, `grace_time_in_minutes`, etc.)
## Platform Support

The plugin works on all platforms supported by CakePHP:

* **Linux**: Full support with all features
* **Windows**: Full support with all features
* **macOS**: Full support with all features

The plugin uses the SignalHandler plugin for cross-platform signal handling, ensuring consistent behavior across all operating systems.

## Requirements Verification

The plugin automatically checks for required dependencies:

* CakePHP 5.0+ framework
* PHP 8.4+ runtime
* SignalHandler plugin for graceful termination
* Access to system commands for shell execution tasks

If any requirements are missing, the plugin will provide clear error messages during initialization.

## Automatic Integration

The plugin automatically integrates with CakePHP's systems:

* Registers scheduling services in the dependency injection container
* Integrates with CakePHP's console command system
* Connects to the event manager for task lifecycle events
* Provides commands for running and managing scheduled tasks
* Sets up signal handling for graceful termination

No manual configuration is required for basic scheduling functionality.

## Configuration Options

The plugin supports optional configuration for advanced use cases:

* Cache configuration for task overlap prevention
* Timezone settings for scheduled tasks
* Event listener registration for custom monitoring
* Mutex implementations for single-server execution

For detailed configuration options, see the Integration documentation.

## Verification

To verify the plugin is installed correctly, run:

```
bin/cake --help
```

You should see the following scheduling commands available:

* `schedule run` - Run scheduled tasks (typically called by cron)
* `schedule work` - Run the scheduler continuously for development
* `schedule list` - List all scheduled tasks
* `schedule pause` - Pause scheduled task processing
* `schedule resume` - Resume scheduled task processing after a pause
* `schedule interrupt` - Interrupt the current schedule run (sub-minute loop)
* `schedule test` - Test scheduled task execution
* `schedule finish` - Finish a scheduled task execution
* `schedule clear` - Clear scheduling cache
* `schedule monitor list` - List all scheduled tasks
* `schedule monitor prune` - Prune scheduled tasks
* `schedule monitor sync` - Sync scheduled tasks

## Next Steps

After installation, proceed to the [Integration](Integration.md) guide to learn how to define and configure your scheduled tasks.
