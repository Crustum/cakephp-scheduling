# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.2.0] - 2026-10-09

### Added
- `Schedule::alwaysOnOneServer()` to run every named scheduled event on only one server
- `Schedule::hasBeenInterruptedSince()` to check for interrupt signals anchored to a run start time

### Changed
- `schedule interrupt` now stores a millisecond timestamp instead of an end-of-minute flag, so long-running scheduled commands observe the signal; `schedule run` and `schedule work` no longer clear the signal when repeatable events are due, and stale signals no longer interrupt newer runs

## [1.1.0] - 2026-09-15

### Added
- `schedule pause`, `schedule resume`, and `schedule interrupt` commands with shared cache control flags
- `SchedulePaused` / `ScheduleResumed` events when pausing or resuming the scheduler
- `evenWhenPaused()` so individual tasks can still run while the scheduler is paused
- `Schedule::withoutInterruptionPolling()` to disable pause/interrupt cache checks for a process
- `withoutOverlapping()` second argument (`releaseOnTerminationSignals`) with SignalHandler-based mutex release on SIGTERM/SIGINT/SIGQUIT
- Group deferred lifecycle callbacks (`before` / `after` / `then` / `onSuccess` / `onFailure`) via pending attributes
- Typed scheduled `Event` injection into lifecycle and filter callbacks
- `skippedBecauseOverlapping` flag on events skipped due to overlap
- `daysOfMonth()` frequency helper for scheduling on specific days of the month
- `schedule list --timezone` option to display cron expressions and next-run times in a chosen timezone
- Cron expression timezone conversion for list output, including split rows when an expression crosses a day boundary
- Scheduled Tasks widget for Rhythm dashboard integration
- Scheduled Tasks recorder for collecting scheduled task metrics and statistics
- Rhythm widget configuration support for displaying monitored scheduled tasks
- Monitored task names are truncated to 255 characters to fit the database column

### Fixed
- Cron expression timezone conversion now expands ranges, steps, and wildcards before shifting, so converted expressions stay equivalent to the original schedule
- Timezone offsets are resolved at the event's next run date, keeping `schedule list --timezone` correct across daylight saving transitions; events straddling a transition are shown unconverted
- Day-of-month shifts respect month lengths and February is left unconverted when the result would depend on the year
- `between()` / `unlessBetween()` resolve timezone at filter evaluation time so `->between(...)->timezone(...)` order is correct
- Sub-minute repeat loops use a stable end-of-minute boundary and stop early when interrupted or past the minute
- Shell commands run as another user escape the inner command with `escapeArgument()` instead of broken single quotes
- `onSuccess()` / `onFailure()` callbacks invoke directly (no broken `$app->call()` assumption)
- Schedule groups no longer mutate shared pending attributes; group attributes are cloned and applied in the correct order
- `repeatEvery()` rejects non-positive second intervals to avoid division-by-zero errors

### Changed
- Mixed day-boundary carries merge into a single list row when all day fields are wildcards
- Skip messages for single-server overlaps use clearer wording
- `schedule work` honors pause/interrupt flags alongside SignalHandler graceful termination

## [1.0.0] - Initial Release

Initial release of the Scheduling plugin for CakePHP, providing comprehensive task scheduling capabilities with monitoring, mutex support, and integration with CakePHP's event system.
