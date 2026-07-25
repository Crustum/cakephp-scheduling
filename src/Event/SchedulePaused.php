<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Event;

use Cake\Event\Event;
use Crustum\Scheduling\Command\BaseSchedulerCommand;

/**
 * Schedule Paused Event
 *
 * Dispatched when scheduled task processing has been paused.
 *
 * @extends \Cake\Event\Event<\Crustum\Scheduling\Command\BaseSchedulerCommand>
 */
class SchedulePaused extends Event
{
    /**
     * Create a new event instance.
     *
     * @param \Crustum\Scheduling\Command\BaseSchedulerCommand $subject The command that paused the schedule
     */
    public function __construct(BaseSchedulerCommand $subject)
    {
        parent::__construct('Scheduling.SchedulePaused', $subject);
    }
}
