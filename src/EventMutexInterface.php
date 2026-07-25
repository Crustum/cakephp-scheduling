<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

/**
 * Event Mutex Interface
 *
 * Defines the contract for preventing overlapping event executions.
 */
interface EventMutexInterface
{
    /**
     * Attempt to obtain an event mutex for the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event to create mutex for
     * @param int|null $customTtlSeconds Custom TTL in seconds (optional)
     * @return bool True if mutex was created successfully
     */
    public function create(Event $event, ?int $customTtlSeconds = null): bool;

    /**
     * Determine if an event mutex exists for the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event to check
     * @param int|null $customTtlSeconds Custom TTL in seconds (optional)
     * @return bool True if mutex exists
     */
    public function exists(Event $event, ?int $customTtlSeconds = null): bool;

    /**
     * Clear the event mutex for the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event to clear mutex for
     * @return void
     */
    public function forget(Event $event): void;
}
