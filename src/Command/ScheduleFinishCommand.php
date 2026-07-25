<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Crustum\Scheduling\Event\ScheduledBackgroundTaskFinished;

/**
 * Schedule Finish Command
 *
 * Handles the completion of background scheduled tasks.
 */
class ScheduleFinishCommand extends BaseSchedulerCommand
{
    /**
     * Get the description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Handle the completion of a background scheduled task';
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
            ->addArgument('mutex', [
                'help' => 'The mutex name of the completed task',
                'required' => true,
            ])
            ->addArgument('exit-code', [
                'help' => 'The exit code of the completed task',
                'required' => true,
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
        $mutexName = $args->getArgument('mutex');
        $exitCode = (int)$args->getArgument('exit-code');

        $schedule = $this->getSchedule();
        $events = $schedule->events();

        $matchingEvents = array_filter($events, fn($event): bool => $event->mutexName() === $mutexName);

        foreach ($matchingEvents as $event) {
            $event->finish($exitCode);

            $this->dispatchSchedulerEvent(new ScheduledBackgroundTaskFinished($this, $event));
        }

        if ($io->level() >= ConsoleIo::VERBOSE) {
            $io->info(sprintf('Background task finished: %s (exit code: %d)', $mutexName, $exitCode));
        }

        return static::CODE_SUCCESS;
    }
}
