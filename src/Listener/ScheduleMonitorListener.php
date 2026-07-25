<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Listener;

use Cake\Event\EventInterface;
use Cake\Event\EventListenerInterface;
use Cake\ORM\TableRegistry;
use Crustum\Scheduling\SchedulingMonitor;

/**
 * Schedule Monitor Listener
 *
 * Listens to scheduling events and tracks task lifecycle for monitoring.
 */
class ScheduleMonitorListener implements EventListenerInterface
{
    /**
     * Return a list of events this listener implements.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        return [
            'Scheduling.ScheduledTaskStarting' => 'beforeTask',
            'Scheduling.ScheduledTaskFinished' => 'afterTask',
            'Scheduling.ScheduledTaskFailed' => 'taskFailed',
            'Scheduling.ScheduledTaskSkipped' => 'taskSkipped',
        ];
    }

    /**
     * Handle task start event.
     *
     * @param \Cake\Event\EventInterface $event The event
     * @return void
     */
    public function beforeTask(EventInterface $event): void
    {
        $task = $event->getData('event');

        if (!$task->shouldMonitor()) {
            return;
        }

        $taskName = $this->getTaskName($task);

        if ($taskName) {
            /** @var \Crustum\Scheduling\Model\Table\MonitoredScheduledTasksTable $MonitoredTasks */
            $MonitoredTasks = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
            $MonitoredTasks->markAsStarting($taskName, [
                'memory' => memory_get_usage(true),
            ]);
        }
    }

    /**
     * Handle task completion event.
     *
     * @param \Cake\Event\EventInterface $event The event
     * @return void
     */
    public function afterTask(EventInterface $event): void
    {
        $task = $event->getData('event');

        if (!$task->shouldMonitor()) {
            return;
        }

        $taskName = $this->getTaskName($task);

        if ($taskName) {
            $runtime = $event->getData('runtime') ?? 0;
            /** @var \Crustum\Scheduling\Model\Table\MonitoredScheduledTasksTable $MonitoredTasks */
            $MonitoredTasks = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');

            $meta = [
                'runtime' => $runtime,
                'exit_code' => 0,
                'memory' => memory_get_usage(true),
            ];

            if ($task->shouldStoreOutputInDb()) {
                $output = $this->getTaskOutput($task);
                if ($output !== null) {
                    $meta['output'] = $output;
                }
            }

            $MonitoredTasks->markAsFinished($taskName, $meta);
        }
    }

    /**
     * Handle task failure event.
     *
     * @param \Cake\Event\EventInterface $event The event
     * @return void
     */
    public function taskFailed(EventInterface $event): void
    {
        $task = $event->getData('event');

        if (!$task->shouldMonitor()) {
            return;
        }

        $taskName = $this->getTaskName($task);

        if ($taskName) {
            $exception = $event->getData('exception');
            /** @var \Crustum\Scheduling\Model\Table\MonitoredScheduledTasksTable $MonitoredTasks */
            $MonitoredTasks = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');

            $meta = [
                'failure_message' => $exception ? $exception->getMessage() : 'Unknown error',
                'exit_code' => $exception ? $exception->getCode() : 1,
                'memory' => memory_get_usage(true),
            ];

            if ($task->shouldStoreOutputInDb()) {
                $output = $this->getTaskOutput($task);
                if ($output !== null) {
                    $meta['output'] = $output;
                }
            }

            $MonitoredTasks->markAsFailed($taskName, $meta);
        }
    }

    /**
     * Handle task skip event.
     *
     * @param \Cake\Event\EventInterface $event The event
     * @return void
     */
    public function taskSkipped(EventInterface $event): void
    {
        $task = $event->getData('event');

        if (!$task->shouldMonitor()) {
            return;
        }

        $taskName = $this->getTaskName($task);

        if ($taskName) {
            $reason = $event->getData('reason') ?? 'Unknown';
            /** @var \Crustum\Scheduling\Model\Table\MonitoredScheduledTasksTable $MonitoredTasks */
            $MonitoredTasks = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
            $MonitoredTasks->markAsSkipped($taskName, [
                'skip_reason' => $reason,
            ]);
        }
    }

    /**
     * Get the task name for monitoring.
     *
     * @param mixed $task The task object
     * @return string|null
     */
    protected function getTaskName($task): ?string
    {
        if (!$task) {
            return null;
        }

        $command = null;
        if (is_object($task) && method_exists($task, 'getCommand')) {
            try {
                $command = $task->getCommand();
            } catch (\LogicException) {
                $command = null;
            }
        }

        return SchedulingMonitor::generateEventName($task, $command);
    }

    /**
     * Get the task output for storage.
     *
     * @param \Crustum\Scheduling\Event $task The scheduled task
     * @return string|null
     */
    private function getTaskOutput($task): ?string
    {
        if ($task->output === $task->getDefaultOutput()) {
            return null;
        }

        if (empty($task->output) || !is_file($task->output)) {
            return null;
        }

        $output = file_get_contents($task->output);

        return $output ?: null;
    }
}
