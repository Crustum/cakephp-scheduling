<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\Core\Configure;
use Crustum\Scheduling\CronExpressionTimezoneConverter;
use Crustum\Scheduling\Event;
use DateTimeZone;

/**
 * Schedule List Command
 *
 * Lists all scheduled events with their expressions, next run dates, and mutex status.
 */
class ScheduleListCommand extends BaseSchedulerCommand
{
    /**
     * Get the description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'List all scheduled events';
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
            ->addOption('timezone', [
                'help' => 'The timezone that times and expressions should be displayed in',
                'default' => null,
            ]);

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     */
    public function execute(Arguments $args, ConsoleIo $io): ?int
    {
        $schedule = $this->getSchedule();
        $events = $schedule->events();

        if ($events === []) {
            $io->info('No scheduled events are defined.');

            return static::CODE_SUCCESS;
        }

        $displayTimezone = $this->resolveDisplayTimezone($args);

        $io->info(sprintf('Found %d scheduled event(s):', count($events)));
        $io->out('');

        $verbose = (bool)$args->getOption('verbose');
        $index = 0;

        foreach ($events as $event) {
            $expressions = CronExpressionTimezoneConverter::forEvent($event, $displayTimezone);

            foreach ($expressions as $expression) {
                $index++;
                $this->displayEvent($event, $index, $io, $verbose, $expression, $displayTimezone);
            }
        }

        return static::CODE_SUCCESS;
    }

    /**
     * Resolve the timezone used for list display.
     *
     * @param \Cake\Console\Arguments $args The command arguments
     * @return \DateTimeZone
     */
    protected function resolveDisplayTimezone(Arguments $args): DateTimeZone
    {
        $timezone = $args->getOption('timezone')
            ?: Configure::read('App.defaultTimezone')
            ?: date_default_timezone_get();

        return new DateTimeZone((string)$timezone);
    }

    /**
     * Display information about a single event expression row.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @param int $index The event index
     * @param \Cake\Console\ConsoleIo $io The console io
     * @param bool $verbose Whether to show verbose information
     * @param string $expression The display cron expression
     * @param \DateTimeZone $displayTimezone The display timezone
     * @return void
     */
    protected function displayEvent(
        Event $event,
        int $index,
        ConsoleIo $io,
        bool $verbose,
        string $expression,
        DateTimeZone $displayTimezone
    ): void {
        $summary = $event->getSummaryForDisplay();
        $repeatExpression = $this->getRepeatExpression($event);
        $nextRun = $event->nextRunDate()->setTimezone($displayTimezone);

        $io->out(sprintf('<info>%d.</info> %s', $index, $summary));
        $io->out(sprintf('    Expression: %s%s', $expression, $repeatExpression));
        $io->out(sprintf('    Next Run: %s', $nextRun->format('Y-m-d H:i:s T')));

        if ($verbose) {
            $this->displayVerboseInfo($event, $io);
        }

        $io->out('');
    }

    /**
     * Display verbose information about an event.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return void
     */
    protected function displayVerboseInfo(Event $event, ConsoleIo $io): void
    {
        if ($event->withoutOverlapping) {
            $mutexExists = $event->mutex->exists($event);
            $status = $mutexExists ? '<error>LOCKED</error>' : '<success>FREE</success>';
            $io->out(sprintf('    Mutex: %s (expires in %d minutes)', $status, $event->expiresAt));
        }

        if ($event->onOneServer) {
            $io->out('    Server: Single server only');
        }

        if ($event->timezone) {
            $timezone = is_string($event->timezone) ? $event->timezone : $event->timezone->getName();
            $io->out(sprintf('    Event Timezone: %s', $timezone));
        }

        if ($event->user) {
            $io->out(sprintf('    User: %s', $event->user));
        }

        if ($event->evenInMaintenanceMode) {
            $io->out('    Maintenance: Runs even in maintenance mode');
        }

        if ($event->evenWhenPaused) {
            $io->out('    Pause: Runs even when scheduler is paused');
        }

        if ($event->runInBackground) {
            $io->out('    Execution: Background');
        }

        if ($event->repeatSeconds) {
            $io->out(sprintf('    Repeat: Every %d seconds', $event->repeatSeconds));
        }

        if ($event->output !== $event->getDefaultOutput()) {
            $io->out(sprintf('    Output: %s', $event->output));
        }
    }

    /**
     * Get the repeat expression for an event.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @return string The repeat expression
     */
    protected function getRepeatExpression(Event $event): string
    {
        return $event->isRepeatable() ? " (every {$event->repeatSeconds}s)" : '';
    }
}
