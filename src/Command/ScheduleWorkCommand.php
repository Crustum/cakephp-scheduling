<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Chronos\Chronos;
use Cake\Console\ConsoleIoInterface;
use Cake\Console\ConsoleOptionParser;
use Crustum\Scheduling\Event;
use Crustum\Scheduling\Schedule;
use Crustum\Scheduling\Service\ScheduleMonitorService;
use Exception;
use SignalHandler\Command\Trait\SignalHandlerTrait;
use Throwable;

/**
 * Schedule Work Command
 *
 * Continuously runs the scheduler in development mode.
 */
class ScheduleWorkCommand extends BaseSchedulerCommand
{
    use SignalHandlerTrait;

    /**
     * Whether the command is running
     *
     * @var bool
     */
    protected bool $isRunning = false;

    /**
     * Get the description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Continuously run the scheduler in development mode';
    }

    /**
     * Hook method for defining this command's option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser = parent::buildOptionParser($parser);
        $parser
            ->setDescription(self::getDescription())
            ->addOption('interval', [
                'short' => 'i',
                'help' => 'The interval in seconds between scheduler runs (default: 60)',
                'default' => '60',
            ])
            ->addOption('max-runs', [
                'short' => 'm',
                'help' => 'Maximum number of runs before stopping (0 = unlimited)',
                'default' => '0',
            ]);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @return int|null The exit code or null for success
     */
    public function execute(): ?int
    {
        $interval = (int)$this->args->getOption('interval');
        $maxRuns = (int)$this->args->getOption('max-runs');
        $verbose = (bool)$this->args->getOption('verbose');

        if ($interval < 1) {
            $this->io->error('Interval must be at least 1 second.');

            return static::CODE_ERROR;
        }

        $this->io->info('Starting scheduler worker...');
        $this->io->info(sprintf('Interval: %d seconds', $interval));
        $this->io->info(sprintf('Max runs: %s', $maxRuns === 0 ? 'unlimited' : (string)$maxRuns));
        $this->io->info('Press Ctrl+C to stop.');
        $this->io->out('');

        $runCount = 0;
        $startTime = time();

        $this->bindGracefulTermination(function (int $signal): void {
            $this->handleGracefulTermination($signal, $this->io);
        });

        $this->isRunning = true;

        try {
            $schedule = $this->getSchedule();
            $service = new ScheduleMonitorService($schedule);
            $service->syncMonitoredTasks();
            if ($verbose) {
                $this->io->info('Synced monitored tasks');
            }
        } catch (Exception $exception) {
            $this->io->warning(sprintf('Failed to sync monitored tasks: %s', $exception->getMessage()));
        }

        $lastExecutionStartedAt = Chronos::now()->subMinutes(10);

        while ($this->isRunning) {
            usleep(100_000);

            $now = Chronos::now();

            $currentMinute = $now->format('Y-m-d H:i');
            $lastMinute = $lastExecutionStartedAt->format('Y-m-d H:i');

            if ($now->second === 0 && $currentMinute !== $lastMinute) {
                $runCount++;

                if ($verbose) {
                    $this->io->info(sprintf('Run #%d at %s', $runCount, $now->format('Y-m-d H:i:s')));
                }

                try {
                    $this->runScheduler($this->io, $verbose);
                } catch (Throwable $e) {
                    $this->io->error(sprintf('Scheduler run failed: %s', $e->getMessage()));

                    if ($verbose) {
                        $this->io->out('Stack trace:');
                        $this->io->out($e->getTraceAsString());
                    }
                }

                $lastExecutionStartedAt = $now;

                if ($maxRuns > 0 && $runCount >= $maxRuns) {
                    $this->io->info(sprintf('Reached maximum runs (%d). Stopping.', $maxRuns));
                    break;
                }
            }
        }

        $this->unbindSignals();

        $totalTime = time() - $startTime;
        $this->io->success(sprintf('Scheduler worker stopped after %d runs in %d seconds.', $runCount, $totalTime));

        return static::CODE_SUCCESS;
    }

    /**
     * Run the scheduler once.
     *
     * @param \Cake\Console\ConsoleIoInterface $io The console io
     * @param bool $verbose Whether to show verbose output
     * @return void
     */
    protected function runScheduler(ConsoleIoInterface $io, bool $verbose): void
    {
        $schedule = $this->getSchedule();
        $dueEvents = $schedule->dueEvents();

        if ($dueEvents === []) {
            if ($verbose) {
                $this->io->info('No scheduled events are due to run.');
            }

            return;
        }

        if ($verbose) {
            $this->io->info(sprintf('Running %d scheduled event(s)...', count($dueEvents)));
        }

        $startedAt = microtime(true);
        $paused = Schedule::isPaused();

        foreach ($dueEvents as $event) {
            if ($event->isRepeatable()) {
                continue;
            }

            if ($paused && !$event->runsWhenPaused()) {
                if ($verbose) {
                    $this->io->info(sprintf('Skipping [%s] - scheduler is paused.', $event->getSummaryForDisplay()));
                }

                continue;
            }

            if (!$event->filtersPass()) {
                if ($verbose) {
                    $this->io->info(sprintf('Skipping [%s] - filters did not pass.', $event->getSummaryForDisplay()));
                }

                continue;
            }

            if ($event->onOneServer && !$schedule->serverShouldRun($event, Chronos::now())) {
                if ($verbose) {
                    $this->io->info(sprintf('Skipping [%s] because the command already ran on another server.', $event->getSummaryForDisplay()));
                }

                continue;
            }

            $this->runEvent($event, $this->io, $verbose);
        }

        $repeatableEvents = array_filter($dueEvents, fn($event) => $event->isRepeatable());

        if ($verbose) {
            $this->io->info(sprintf('Found %d repeatable events out of %d total events', count($repeatableEvents), count($dueEvents)));
        }

        if ($repeatableEvents !== []) {
            $this->repeatEvents($repeatableEvents, $this->io, $verbose);
        }

        $runtime = round((microtime(true) - $startedAt) * 1000.0, 2);

        if ($verbose) {
            $this->io->success(sprintf('Scheduled events completed in %sms.', $runtime));
        }
    }

    /**
     * Run repeatable events in a tight loop for the rest of the minute.
     *
     * @param array<\Crustum\Scheduling\Event> $events The repeatable events
     * @param \Cake\Console\ConsoleIoInterface $io The console io
     * @param bool $verbose Whether to show verbose output
     * @return void
     */
    protected function repeatEvents(array $events, ConsoleIoInterface $io, bool $verbose): void
    {
        $startedAt = Chronos::now();
        $endOfMinute = $startedAt->setTime(
            (int)$startedAt->hour,
            (int)$startedAt->minute,
            59,
            999999,
        );

        if ($verbose) {
            $this->io->info(sprintf(
                'Running %d repeatable event(s) until %s...',
                count($events),
                $endOfMinute->format('H:i:s'),
            ));
            foreach ($events as $event) {
                $this->io->info(sprintf('  - %s (repeat every %ds)', $event->getSummaryForDisplay(), $event->repeatSeconds));
            }
        }

        while ($this->isRunning && Chronos::now()->lessThanOrEquals($endOfMinute)) {
            $paused = Schedule::isPaused();

            foreach ($events as $event) {
                if (!$this->isRunning || Schedule::shouldInterrupt($startedAt)) {
                    return;
                }

                if (!$event->shouldRepeatNow()) {
                    continue;
                }

                if (Chronos::now()->greaterThan($endOfMinute)) {
                    return;
                }

                if ($paused && !$event->runsWhenPaused()) {
                    continue;
                }

                if ($event->shouldSkipDueToOverlapping()) {
                    $event->skippedBecauseOverlapping = true;
                    if ($verbose) {
                        $this->io->info(sprintf('Skipping repeatable [%s] - overlapping execution.', $event->getSummaryForDisplay()));
                    }

                    continue;
                }

                if (!$event->filtersPass()) {
                    if ($verbose) {
                        $this->io->info(sprintf('Skipping repeatable [%s] - other filters did not pass.', $event->getSummaryForDisplay()));
                    }

                    continue;
                }

                if ($event->onOneServer) {
                    $schedule = $this->getSchedule();
                    if (!$schedule->serverShouldRun($event, Chronos::now())) {
                        if ($verbose) {
                            $this->io->info(sprintf('Skipping repeatable [%s] because the command already ran on another server.', $event->getSummaryForDisplay()));
                        }

                        continue;
                    }
                }

                $this->runEvent($event, $this->io, $verbose);
            }

            if (!$this->isRunning) {
                break;
            }

            usleep(100_000);
        }

        if ($verbose) {
            $runtime = round((microtime(true) - (float)$startedAt->getTimestamp()) * 1000.0, 2);
            $this->io->info(sprintf('Repeatable events loop completed in %sms.', $runtime));
        }
    }

    /**
     * Run a single scheduled event.
     *
     * @param \Crustum\Scheduling\Event $event The event to run
     * @param \Cake\Console\ConsoleIoInterface $io The console io
     * @param bool $verbose Whether to show verbose output
     * @return void
     */
    protected function runEvent(Event $event, ConsoleIoInterface $io, bool $verbose): void
    {
        $summary = $event->getSummaryForDisplay();

        if ($verbose) {
            $this->io->info(sprintf('Running scheduled command: %s', $summary));
        }

        $startedAt = microtime(true);

        try {
            $event->run();
            $runtime = round((microtime(true) - $startedAt) * 1000.0, 2);

            if ($verbose) {
                $this->io->success(sprintf('Successfully ran scheduled command: %s (%sms)', $summary, $runtime));
            }
        } catch (Throwable $throwable) {
            $this->io->error(sprintf('Failed to run scheduled command: %s - %s', $summary, $throwable->getMessage()));
        }
    }

    /**
     * Handle graceful termination signal.
     *
     * @param int $signal The signal received
     * @param \Cake\Console\ConsoleIoInterface $io The console io
     * @return void
     */
    protected function handleGracefulTermination(int $signal, ConsoleIoInterface $io): void
    {
        $this->io->out('');
        $this->io->info('Received termination signal. Stopping scheduler worker...');

        $this->isRunning = false;
    }
}
