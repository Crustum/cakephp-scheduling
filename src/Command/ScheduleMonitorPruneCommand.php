<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Command\Command;
use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Override;

/**
 * Command to prune old monitoring data.
 */
class ScheduleMonitorPruneCommand extends Command
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Prune old monitoring data';
    }

    /**
     * Build the option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        return $parser
            ->addOption('days', [
                'short' => 'd',
                'help' => 'Days to keep (default: 7)',
            ]);
    }

    /**
     * Execute the command.
     *
     * @param \Cake\Console\Arguments $args The command arguments.
     * @param \Cake\Console\ConsoleIo $io The console io
     * @return int|null The exit code or null for success
     * @phpcsSuppress SlevomatCodingStandard.TypeHints.ReturnTypeHint.MissingNativeTypeHint
     */
    public function execute(Arguments $args, ConsoleIo $io)
    {
        $days = $args->getOption('days');
        $days = is_numeric($days) ? (int)$days : null;

        /** @var \Crustum\Scheduling\Model\Table\MonitoredScheduledTaskLogItemsTable $LogItems */
        $LogItems = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTaskLogItems');
        $deleted = $LogItems->cleanupOldItems($days);

        $io->out("Pruned {$deleted} log items older than {$days} days.");

        return static::CODE_SUCCESS;
    }
}
