<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Event;

use Cake\Event\Event;
use Crustum\Scheduling\Command\BaseSchedulerCommand;
use Crustum\Scheduling\Event as ScheduledEvent;

/**
 * Scheduled Task Starting Event
 *
 * Dispatched when a scheduled task is about to start.
 *
 * @extends \Cake\Event\Event<\Crustum\Scheduling\Command\BaseSchedulerCommand>
 */
class ScheduledTaskStarting extends Event
{
    /**
     * Create a new event instance.
     *
     * @param \Crustum\Scheduling\Command\BaseSchedulerCommand $subject The command that triggered this event
     * @param \Crustum\Scheduling\Event $event The scheduled event
     */
    public function __construct(BaseSchedulerCommand $subject, ScheduledEvent $event)
    {
        parent::__construct('Scheduling.ScheduledTaskStarting', $subject, [
            'event' => $event,
        ]);
    }
}
