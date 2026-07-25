<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

use Cake\Cache\Cache;

/**
 * Cache Event Mutex
 *
 * Cache-based implementation of event mutex for preventing overlapping executions.
 */
class CacheEventMutex implements EventMutexInterface, CacheAwareInterface
{
    /**
     * The cache store that should be used.
     *
     * @var string
     */
    public string $store = 'scheduler_mutex';

    /**
     * Attempt to obtain an event mutex for the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @param int|null $customTtlSeconds Custom TTL in seconds (optional)
     * @return bool True if mutex was created successfully
     */
    public function create(Event $event, ?int $customTtlSeconds = null): bool
    {
        $key = $event->mutexName();
        $exists = $this->exists($event, $customTtlSeconds);

        if ($exists) {
            return false;
        }

        return Cache::write($key, time(), $this->store);
    }

    /**
     * Determine if an event mutex exists for the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @param int|null $customTtlSeconds Custom TTL in seconds (optional)
     * @return bool True if mutex exists and is still valid
     */
    public function exists(Event $event, ?int $customTtlSeconds = null): bool
    {
        $key = $event->mutexName();
        $value = Cache::read($key, $this->store);

        if ($value === false) {
            return false;
        }

        $ttlSeconds = $customTtlSeconds ?? $event->expiresAt * 60;
        $createdAt = is_numeric($value) ? (int)$value : 0;
        $expiresAt = $createdAt + $ttlSeconds;
        $currentTime = time();

        $isValid = $currentTime < $expiresAt;

        if (!$isValid) {
            Cache::delete($key, $this->store);
        }

        return $isValid;
    }

    /**
     * Clear the event mutex for the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @return void
     */
    public function forget(Event $event): void
    {
        $key = $event->mutexName();

        Cache::delete($key, $this->store);
    }

    /**
     * Specify the cache store that should be used.
     *
     * @param string $store The cache store name
     * @return static
     */
    public function useStore(string $store): static
    {
        $this->store = $store;

        return $this;
    }
}
