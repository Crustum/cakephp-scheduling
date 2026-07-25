<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Event;

use Cake\Event\Event;
use Crustum\Scheduling\Command\BaseSchedulerCommand;
use Crustum\Scheduling\Event as ScheduledEvent;

/**
 * Scheduled Task Finished Event
 *
 * Dispatched when a scheduled task has finished successfully.
 *
 * @extends \Cake\Event\Event<\Crustum\Scheduling\Command\BaseSchedulerCommand>
 */
class ScheduledTaskFinished extends Event
{
    /**
     * Create a new event instance.
     *
     * @param \Crustum\Scheduling\Command\BaseSchedulerCommand $subject The command that triggered this event
     * @param \Crustum\Scheduling\Event $event The scheduled event
     * @param float $runtime The execution time in seconds
     */
    public function __construct(BaseSchedulerCommand $subject, ScheduledEvent $event, float $runtime)
    {
        parent::__construct('Scheduling.ScheduledTaskFinished', $subject, [
            'event' => $event,
            'runtime' => $runtime,
        ]);
    }
}
