<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Command;

use Cake\Command\Command;
use Cake\Console\CommandFactoryInterface;
use Cake\Event\Event;
use Cake\Event\EventManager;
use Crustum\Scheduling\Schedule;

/**
 * Base Scheduler Command
 *
 * Provides common functionality for all scheduler commands.
 */
abstract class BaseSchedulerCommand extends Command
{
    /**
     * Schedule instance.
     *
     * @var \Crustum\Scheduling\Schedule
     */
    protected Schedule $schedule;

    /**
     * Constructor.
     *
     * @param \Crustum\Scheduling\Schedule $schedule The schedule instance
     * @param \Cake\Console\CommandFactoryInterface $commandFactory The command factory
     */
    public function __construct(Schedule $schedule, CommandFactoryInterface $commandFactory)
    {
        parent::__construct($commandFactory);
        $this->schedule = $schedule;
    }

    /**
     * Get the schedule instance.
     *
     * @return \Crustum\Scheduling\Schedule
     */
    protected function getSchedule(): Schedule
    {
        return $this->schedule;
    }

    /**
     * Dispatch a scheduler event.
     *
     * @param \Cake\Event\Event $event The event to dispatch
     * @return void
     */
    protected function dispatchSchedulerEvent(Event $event): void
    {
        EventManager::instance()->dispatch($event);
    }
}
