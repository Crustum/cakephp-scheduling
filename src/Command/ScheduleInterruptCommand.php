<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Console\ConsoleOptionParser;
use Crustum\Scheduling\Schedule;

/**
 * Schedule Interrupt Command
 *
 * Signals the current schedule run to stop repeating events at the end of the minute.
 */
class ScheduleInterruptCommand extends BaseSchedulerCommand
{
    /**
     * Get the description.
     *
     * @return string
     */
    public static function getDescription(): string
    {
        return 'Interrupt the current schedule run';
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
        Schedule::interrupt();
        $this->io->success('Broadcasting schedule interrupt signal.');

        return static::CODE_SUCCESS;
    }
}
