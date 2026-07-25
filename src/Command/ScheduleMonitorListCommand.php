<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Console\Arguments;
use Cake\Console\ConsoleIo;
use Cake\Console\ConsoleOptionParser;
use Cake\ORM\TableRegistry;
use Override;

/**
 * Schedule Monitor List Command
 *
 * Lists all monitored scheduled tasks with their status.
 */
class ScheduleMonitorListCommand extends BaseSchedulerCommand
{
    /**
     * @inheritDoc
     */
    #[Override]
    public static function getDescription(): string
    {
        return 'List all monitored scheduled tasks';
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
            ->addOption('status', [
                'help' => 'Filter by status (running, failed, completed, overdue)',
                'choices' => ['running', 'failed', 'completed', 'overdue'],
            ])
            ->addOption('type', [
                'help' => 'Filter by task type',
            ])
            ->addOption('recent', [
                'help' => 'Show only tasks with recent activity (hours)',
                'default' => null,
            ])
            ->addOption('format', [
                'help' => 'Output format (table, json)',
                'choices' => ['table', 'json'],
                'default' => 'table',
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
        try {
            /** @var \Crustum\Scheduling\Model\Table\MonitoredScheduledTasksTable $MonitoredTasks */
            $MonitoredTasks = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
            $query = $MonitoredTasks->find()
                ->contain(['MonitoredScheduledTaskLogItems' => fn($q) => $q->orderByDesc('created')->limit(1)]);

            $status = $args->getOption('status');
            if ($status) {
                switch ($status) {
                    case 'running':
                        $query = $MonitoredTasks->findRunning($query, []);
                        break;
                    case 'failed':
                        $query = $MonitoredTasks->findFailed($query, []);
                        break;
                    case 'completed':
                        $query = $MonitoredTasks->findCompleted($query, []);
                        break;
                    case 'overdue':
                        $query = $MonitoredTasks->findOverdue($query, []);
                        break;
                }
            }

            $type = $args->getOption('type');
            if ($type) {
                $query = $MonitoredTasks->findByType($query, ['type' => $type]);
            }

            $recent = $args->getOption('recent');
            if ($recent) {
                $query = $MonitoredTasks->findRecent($query, ['hours' => (int)$recent]);
            }

            $tasks = $query->orderByDesc('last_started')->toArray();

            if (empty($tasks)) {
                $io->info('No monitored tasks found');

                return static::CODE_SUCCESS;
            }

            $format = $args->getOption('format');
            if ($format === 'json') {
                $this->outputJson($io, $tasks);
            } else {
                $this->outputTable($io, $tasks);
            }

            $stats = $MonitoredTasks->getStatistics();
            $io->out('');
            $io->info(sprintf(
                'Statistics: %d total, %d running, %d failed, %d completed, %d overdue',
                $stats['total'],
                $stats['running'],
                $stats['failed'],
                $stats['completed'],
                $stats['overdue']
            ));

            return static::CODE_SUCCESS;
        } catch (\Exception $exception) {
            $io->error(sprintf('Error listing tasks: %s', $exception->getMessage()));

            return static::CODE_ERROR;
        }
    }

    /**
     * Output tasks in table format.
     *
     * @param \Cake\Console\ConsoleIo $io The console io
     * @param array<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> $tasks The tasks
     * @return void
     */
    private function outputTable(ConsoleIo $io, array $tasks): void
    {
        $headers = ['Name', 'Type', 'Status', 'Last Started', 'Last Finished', 'Last Failed', 'Cron Expression'];
        $rows = [$headers];

        /** @var \Crustum\Scheduling\Model\Table\MonitoredScheduledTasksTable $MonitoredTasks */
        $MonitoredTasks = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');

        foreach ($tasks as $task) {
            $statusInfo = $MonitoredTasks->getTaskStatusInfo($task);

            $rows[] = [
                $statusInfo['name'],
                $statusInfo['type'],
                $statusInfo['status'],
                $statusInfo['last_started'],
                $statusInfo['last_finished'],
                $statusInfo['last_failed'],
                $statusInfo['cron_expression'],
            ];
        }

        $io->helper('Table')->output($rows);
    }

    /**
     * Output tasks in JSON format.
     *
     * @param \Cake\Console\ConsoleIo $io The console io
     * @param array<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> $tasks The tasks
     * @return void
     */
    private function outputJson(ConsoleIo $io, array $tasks): void
    {
        /** @var \Crustum\Scheduling\Model\Table\MonitoredScheduledTasksTable $MonitoredTasks */
        $MonitoredTasks = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');

        $data = [];
        foreach ($tasks as $task) {
            $statusInfo = $MonitoredTasks->getTaskStatusInfo($task);
            $data[] = [
                'name' => $statusInfo['name'],
                'type' => $statusInfo['type'],
                'status' => $statusInfo['status'],
                'cron_expression' => $statusInfo['cron_expression'],
                'timezone' => $task->get('timezone'),
                'grace_time_in_minutes' => $statusInfo['grace_time_in_minutes'],
                'last_started' => $statusInfo['last_started'],
                'last_finished' => $statusInfo['last_finished'],
                'last_failed' => $statusInfo['last_failed'],
                'last_skipped' => $statusInfo['last_skipped'],
                'created' => $statusInfo['created'],
                'modified' => $statusInfo['modified'],
            ];
        }

        $io->out(json_encode($data, JSON_PRETTY_PRINT) ?: '{}');
    }
}
