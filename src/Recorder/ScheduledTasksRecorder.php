<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Recorder;

use Cake\Event\EventListenerInterface;
use Cake\ORM\TableRegistry;
use Crustum\Rhythm\Event\SharedBeat;
use Crustum\Rhythm\Recorder\BaseRecorder;
use Crustum\Rhythm\Recorder\Trait\ThrottlingTrait;

/**
 * Scheduled Tasks Recorder
 *
 * Records monitored scheduled tasks status to Rhythm.
 */
class ScheduledTasksRecorder extends BaseRecorder implements EventListenerInterface
{
    use ThrottlingTrait;

    /**
     * Implemented events.
     *
     * @return array<string, mixed>
     */
    public function implementedEvents(): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        return [
            SharedBeat::class => 'record',
        ];
    }

    /**
     * Record scheduled tasks status.
     *
     * @param mixed $data The shared beat event
     * @return void
     */
    public function record(mixed $data): void
    {
        if (!$data instanceof SharedBeat) {
            return;
        }

        if (!$this->isEnabled()) {
            return;
        }

        $this->throttle(15, $data, function (SharedBeat $event): void {
            $timestamp = $event->getTimestamp()->getTimestamp();

            try {
                /** @var \Crustum\Scheduling\Model\Table\MonitoredScheduledTasksTable $monitoredTasksTable */
                $monitoredTasksTable = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
                $stats = $monitoredTasksTable->getStatistics();
                $taskDetails = $monitoredTasksTable->getAllTasksStatusInfo();
                $this->rhythm->set(
                    'scheduled_tasks',
                    'summary',
                    json_encode([
                        'total' => $stats['total'],
                        'running' => $stats['running'],
                        'failed' => $stats['failed'],
                        'completed' => $stats['completed'],
                        'overdue' => $stats['overdue'],
                        'timestamp' => $timestamp,
                    ], JSON_THROW_ON_ERROR),
                    $timestamp
                );

                $this->rhythm->set(
                    'scheduled_tasks',
                    'details',
                    json_encode($taskDetails, JSON_THROW_ON_ERROR),
                    $timestamp
                );
            } catch (\Exception $exception) {
                debug('ScheduledTasksRecorder error: ' . $exception->getMessage());
            }
        });
    }
}
