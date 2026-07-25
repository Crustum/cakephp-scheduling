<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

use Cake\Cache\Cache;
use DateTimeInterface;

/**
 * Cache Scheduling Mutex
 *
 * Cache-based implementation of scheduling mutex for single-server execution.
 */
class CacheSchedulingMutex implements SchedulingMutexInterface, CacheAwareInterface
{
    /**
     * The cache store that should be used.
     *
     * @var string
     */
    public string $store = 'default';

    /**
     * Attempt to obtain a scheduling mutex for the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @param \DateTimeInterface $time The time
     * @return bool True if mutex was created successfully
     */
    public function create(Event $event, DateTimeInterface $time): bool
    {
        $key = $event->mutexName() . $time->format('Hi');

        if ($this->exists($event, $time)) {
            return false;
        }

        return Cache::write($key, time(), $this->store);
    }

    /**
     * Determine if a scheduling mutex exists for the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @param \DateTimeInterface $time The time
     * @return bool True if mutex exists and is still valid
     */
    public function exists(Event $event, DateTimeInterface $time): bool
    {
        $key = $event->mutexName() . $time->format('Hi');
        $value = Cache::read($key, $this->store);

        return $value !== false;
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
