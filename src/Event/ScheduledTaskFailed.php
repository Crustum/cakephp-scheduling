<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Event;

use Cake\Event\Event;
use Crustum\Scheduling\Command\BaseSchedulerCommand;
use Crustum\Scheduling\Event as ScheduledEvent;
use Throwable;

/**
 * Scheduled Task Failed Event
 *
 * Dispatched when a scheduled task has failed.
 *
 * @extends \Cake\Event\Event<\Crustum\Scheduling\Command\BaseSchedulerCommand>
 */
class ScheduledTaskFailed extends Event
{
    /**
     * Create a new event instance.
     *
     * @param \Crustum\Scheduling\Command\BaseSchedulerCommand $subject The command that triggered this event
     * @param \Crustum\Scheduling\Event $event The scheduled event
     * @param \Throwable $exception The exception
     */
    public function __construct(BaseSchedulerCommand $subject, ScheduledEvent $event, Throwable $exception)
    {
        parent::__construct('Scheduling.ScheduledTaskFailed', $subject, [
            'event' => $event,
            'exception' => $exception,
        ]);
    }
}
