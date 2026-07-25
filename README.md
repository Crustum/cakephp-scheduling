# CakePHP Scheduling Plugin

The **Scheduling** plugin provides comprehensive task scheduling for CakePHP applications with the following features:

The scheduler allows you to fluently and expressively define your command schedule within your CakePHP application itself. When using the scheduler, only a single cron entry is needed on your server. Your task schedule is defined during your application's container building phase using CakePHP's event system.

The plugin provides task scheduling capabilities with sub-minute precision, allowing you to schedule tasks as frequently as every second. It features a fluent API for defining task frequencies from seconds to yearly intervals, with human-readable syntax that makes complex schedules easy to understand and maintain.

Task overlap prevention ensures that long-running tasks don't interfere with subsequent executions, while single-server execution prevents duplicate task runs in multi-server environments. Background task execution allows multiple tasks to run simultaneously, improving overall system performance.

The plugin includes comprehensive task hooks for before, after, success, and failure callbacks, enabling you to implement custom logic around task execution. Repeatable tasks with configurable intervals provide flexibility for tasks that need to run multiple times within a minute.

The plugin integrates seamlessly with CakePHP's console system and event manager.

## Requirements

* PHP 8.2+
* SignalHandler Plugin (for graceful termination)

See [Versions.md](docs/Versions.md) for the supported CakePHP versions.

## Documentation

For documentation, as well as tutorials, see the [Docs](docs/index.md) directory of this repository.

## License

Licensed under the [MIT](http://www.opensource.org/licenses/mit-license.php) License. Redistributions of the source code included in this repository must retain the copyright notice found in each file.
