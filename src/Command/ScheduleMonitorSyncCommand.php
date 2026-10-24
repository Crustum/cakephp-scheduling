<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Console\ConsoleOptionParser;
use Crustum\Scheduling\Service\ScheduleMonitorService;
use Exception;
use Override;

/**
 * Schedule Monitor Sync Command
 *
 * Syncs the schedule with the monitoring database.
 */
class ScheduleMonitorSyncCommand extends BaseSchedulerCommand
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'Sync the schedule with the monitoring database';
    }

    /**
     * Hook method for defining this command's option parser.
     *
     * @param \Cake\Console\ConsoleOptionParser $parser The parser to be defined
     * @return \Cake\Console\ConsoleOptionParser The built parser.
     */
    protected function buildOptionParser(ConsoleOptionParser $parser): ConsoleOptionParser
    {
        $parser
            ->addOption('keep-old', [
                'help' => 'Keep existing monitored tasks not in current schedule',
                'boolean' => true,
            ])
            ->addOption('dry-run', [
                'help' => 'Show what would be synced without making changes',
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
        $this->io->info('Starting schedule sync...');
        $dryRun = $this->args->getOption('dry-run');
        if ($dryRun) {
            $this->io->warning('DRY RUN MODE - No changes will be made');
        }

        try {
            $schedule = $this->getSchedule();
            $service = new ScheduleMonitorService($schedule);

            if ($dryRun) {
                $this->io->info('DRY RUN: Would sync monitored tasks');
                $this->io->success('DRY RUN completed');
            } else {
                $result = $service->syncMonitoredTasks();

                $this->io->success(sprintf(
                    'Successfully synced %d tasks (%d created, %d updated, %d removed)',
                    $result['synced'],
                    $result['created'],
                    $result['updated'],
                    $result['removed'],
                ));
            }

            return static::CODE_SUCCESS;
        } catch (Exception $exception) {
            $this->io->error(sprintf('Error syncing schedule: %s', $exception->getMessage()));

            return static::CODE_ERROR;
        }
    }
}
