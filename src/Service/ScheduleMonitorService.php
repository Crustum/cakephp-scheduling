<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Service;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Crustum\Scheduling\Event;
use Crustum\Scheduling\Schedule;
use Crustum\Scheduling\SchedulingMonitor;

/**
 * Schedule Monitor Service
 *
 * Handles synchronization of scheduled tasks with the monitoring database.
 */
class ScheduleMonitorService
{
    /**
     * The schedule instance.
     *
     * @var \Crustum\Scheduling\Schedule
     */
    protected Schedule $schedule;

    /**
     * Constructor.
     *
     * @param \Crustum\Scheduling\Schedule $schedule The schedule instance
     */
    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * Sync monitored tasks with the database.
     *
     * @return array<string, mixed> Sync results
     */
    public function syncMonitoredTasks(): array
    {
        /** @var \Crustum\Scheduling\Model\Table\MonitoredScheduledTasksTable $monitoredTasksTable */
        $monitoredTasksTable = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');

        $events = $this->schedule->events();
        $currentNames = [];
        $syncedCount = 0;
        $createdCount = 0;
        $updatedCount = 0;

        foreach ($events as $event) {
            $taskData = $this->extractEventData($event);
            if (!$taskData) {
                continue;
            }

            $currentNames[] = $taskData['name'];

            $existingTask = $monitoredTasksTable->find()
                ->where(['name' => $taskData['name']])
                ->first();

            if ($existingTask) {
                $monitoredTasksTable->patchEntity($existingTask, $taskData);
                $monitoredTasksTable->saveOrFail($existingTask);
                $updatedCount++;
            } else {
                $newTask = $monitoredTasksTable->newEntity($taskData);
                $monitoredTasksTable->saveOrFail($newTask);
                $createdCount++;
            }

            $syncedCount++;
        }

        $oldTasks = [];
        if ($currentNames !== []) {
            $oldTasks = $monitoredTasksTable->find()
                ->whereNotInList('name', $currentNames)
                ->toArray();
        } else {
            $oldTasks = $monitoredTasksTable->find()->toArray();
        }

        foreach ($oldTasks as $oldTask) {
            $monitoredTasksTable->delete($oldTask);
        }

        return [
            'synced' => $syncedCount,
            'created' => $createdCount,
            'updated' => $updatedCount,
            'removed' => count($oldTasks),
        ];
    }

    /**
     * Extract monitoring data from a schedule event.
     *
     * @param \Crustum\Scheduling\Event $event The schedule event
     * @return array<string, mixed>|null
     */
    protected function extractEventData(Event $event): ?array
    {
        try {
            if (!$event->shouldMonitor()) {
                return null;
            }

            $command = null;
            try {
                $command = $event->getCommand();
            } catch (\LogicException) {
                $command = null;
            }

            $name = SchedulingMonitor::generateEventName($event, $command);
            $cronExpression = $this->getCronExpression($event);
            if (!$cronExpression) {
                return null;
            }

            $timezone = $this->getEventTimezone($event);
            $graceTime = $event->getGraceTimeInMinutes() ?? Configure::read('Scheduling.grace_time_in_minutes', 5);
            $type = $this->getEventType($event, $command);

            return [
                'name' => $name,
                'type' => $type,
                'cron_expression' => $cronExpression,
                'timezone' => $timezone,
                'grace_time_in_minutes' => $graceTime,
            ];
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Get the cron expression for the event.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @return string|null
     */
    protected function getCronExpression(Event $event): ?string
    {
        if ($event->repeatSeconds) {
            return sprintf('*/%d * * * * *', $event->repeatSeconds);
        }

        return $event->expression;
    }

    /**
     * Get the timezone for the event.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @return string|null
     */
    protected function getEventTimezone(Event $event): ?string
    {
        if ($event->timezone instanceof \DateTimeZone) {
            return $event->timezone->getName();
        }

        return $event->timezone;
    }

    /**
     * Get the event type based on the event and command.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @param string|null $command The command string
     * @return string
     */
    protected function getEventType(Event $event, ?string $command): string
    {
        if ($command === null) {
            return SchedulingMonitor::EVENT_TYPE_CALLBACK;
        }

        return SchedulingMonitor::getEventType($event, $command);
    }
}
