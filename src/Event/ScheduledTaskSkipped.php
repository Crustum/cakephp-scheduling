<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Event;

use Cake\Event\Event;
use Crustum\Scheduling\Event as ScheduledEvent;

/**
 * Scheduled Task Skipped Event
 *
 * Dispatched when a scheduled task is skipped due to filters.
 *
 * @extends \Cake\Event\Event<\Crustum\Scheduling\Event>
 */
class ScheduledTaskSkipped extends Event
{
    /**
     * Create a new event instance.
     *
     * @param \Crustum\Scheduling\Event $event The scheduled event
     */
    public function __construct(ScheduledEvent $event)
    {
        parent::__construct('Scheduling.ScheduledTaskSkipped', $event, [
            'event' => $event,
        ]);
    }
}
