<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Console\ConsoleOptionParser;
use Crustum\Scheduling\Event\SchedulePaused;
use Crustum\Scheduling\Schedule;

/**
 * Schedule Pause Command
 *
 * Pauses scheduled task processing until resumed.
 */
class SchedulePauseCommand extends BaseSchedulerCommand
{
    /**
     * Get the description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Pause the scheduler';
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
        if (!Schedule::$pausable) {
            $this->io->error('Schedule pausing is currently disabled.');

            return static::CODE_ERROR;
        }

        Schedule::pause();
        $this->dispatchSchedulerEvent(new SchedulePaused($this));
        $this->io->success('Scheduled task processing has been paused.');

        return static::CODE_SUCCESS;
    }
}
