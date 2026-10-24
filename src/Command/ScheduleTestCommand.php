<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Chronos\Chronos;
use Cake\Console\ConsoleOptionParser;
use Throwable;

/**
 * Schedule Test Command
 *
 * Runs a single scheduled command interactively for testing purposes.
 */
class ScheduleTestCommand extends BaseSchedulerCommand
{
    /**
     * Get the description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Run a single scheduled command interactively';
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
            ->addArgument('index', [
                'help' => 'The index number of the command to run (from schedule:list)',
                'required' => true,
            ])
            ->addOption('force', [
                'short' => 'f',
                'help' => 'Force execution even if not due',
                'boolean' => true,
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
        $index = (int)$this->args->getArgument('index');
        $force = $this->args->getOption('force');
        $verbose = $this->args->getOption('verbose');
        $schedule = $this->getSchedule();
        $events = $schedule->events();
        if ($events === []) {
            $this->io->error('No scheduled events are defined.');

            return static::CODE_ERROR;
        }

        if ($index < 1 || $index > count($events)) {
            $this->io->error(sprintf('Invalid index. Please choose between 1 and %d.', count($events)));

            return static::CODE_ERROR;
        }

        $event = $events[$index - 1];
        $summary = $event->getSummaryForDisplay();
        $this->io->info(sprintf('Testing scheduled command: %s', $summary));
        $this->io->out('');
        if (!$force && !$event->isDue()) {
            $this->io->warning('This command is not due to run at this time.');
            $nextRun = $event->nextRunDate();
            $this->io->out(sprintf('Next run: %s', $nextRun->format('Y-m-d H:i:s T')));
            $this->io->out('');

            if ($this->io->askChoice('Do you want to run it anyway?', ['y', 'n'], 'n') !== 'y') {
                $this->io->info('Command execution cancelled.');

                return static::CODE_SUCCESS;
            }
        }

        if (!$event->filtersPass()) {
            $this->io->warning('This command would be skipped due to filters.');
            if ($this->io->askChoice('Do you want to run it anyway?', ['y', 'n'], 'n') !== 'y') {
                $this->io->info('Command execution cancelled.');

                return static::CODE_SUCCESS;
            }
        }

        if ($event->onOneServer && !$schedule->serverShouldRun($event, Chronos::now())) {
            $this->io->warning('This command is already running on another server.');
            if ($this->io->askChoice('Do you want to run it anyway?', ['y', 'n'], 'n') !== 'y') {
                $this->io->info('Command execution cancelled.');

                return static::CODE_SUCCESS;
            }
        }

        $this->io->info('Executing command...');
        $this->io->out('');

        $startedAt = microtime(true);
        try {
            $event->run();
            $runtime = round((microtime(true) - $startedAt) * 1000.0, 2);

            $this->io->success(sprintf('Command executed successfully in %sms.', $runtime));

            return static::CODE_SUCCESS;
        } catch (Throwable $throwable) {
            $this->io->error(sprintf('Command failed: %s', $throwable->getMessage()));

            if ($verbose) {
                $this->io->out('');
                $this->io->out('Stack trace:');
                $this->io->out($throwable->getTraceAsString());
            }

            return static::CODE_ERROR;
        }
    }
}
