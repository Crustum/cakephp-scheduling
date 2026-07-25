<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

use DateTimeInterface;

/**
 * Scheduling Mutex Interface
 *
 * Defines the contract for preventing multiple servers from running the same event.
 */
interface SchedulingMutexInterface
{
    /**
     * Attempt to obtain a scheduling mutex for the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event to create mutex for
     * @param \DateTimeInterface $time The time to create mutex for
     * @return bool True if mutex was created successfully
     */
    public function create(Event $event, DateTimeInterface $time): bool;

    /**
     * Determine if a scheduling mutex exists for the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event to check
     * @param \DateTimeInterface $time The time to check
     * @return bool True if mutex exists
     */
    public function exists(Event $event, DateTimeInterface $time): bool;
}
