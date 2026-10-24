<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Console\ConsoleOptionParser;
use Crustum\Scheduling\Event\ScheduleResumed;
use Crustum\Scheduling\Schedule;

/**
 * Schedule Resume Command
 *
 * Resumes scheduled task processing after a pause.
 */
class ScheduleResumeCommand extends BaseSchedulerCommand
{
    /**
     * Get the description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Resume the schedule';
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
        $parser->setDescription(self::getDescription());

        return $parser;
    }

    /**
     * Implement this method with your command's logic.
     *
     * @return int|null The exit code or null for success
     */
    public function execute(): ?int
    {
        Schedule::resume();
        $this->dispatchSchedulerEvent(new ScheduleResumed($this));
        $this->io->success('Scheduled task processing has resumed.');

        return static::CODE_SUCCESS;
    }
}
