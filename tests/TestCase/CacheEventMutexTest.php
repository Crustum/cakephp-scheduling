<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase;

use Cake\Cache\Cache;
use Cake\TestSuite\TestCase;
use Crustum\Scheduling\CacheEventMutex;
use Crustum\Scheduling\CacheEventMutex as Mutex;
use Crustum\Scheduling\Event;

class CacheEventMutexTest extends TestCase
{
    private function createEvent(string $command): Event
    {
        $mutex = new Mutex();

        return new Event($mutex, $command);
    }

    protected function setUp(): void
    {
        parent::setUp();
        if (!Cache::getConfig('test')) {
            Cache::setConfig('test', [
                'className' => 'Array',
            ]);
        }
    }

    protected function tearDown(): void
    {
        Cache::clear('test');
        parent::tearDown();
    }

    public function testMutexPreventsDuplicateExecution(): void
    {
        $mutex = new CacheEventMutex();
        $mutex->useStore('test');

        $event = $this->createEvent('php -v');

        // First creation should succeed
        $this->assertTrue($mutex->create($event));

        // Second creation should fail (already exists)
        $this->assertFalse($mutex->create($event));
    }

    public function testMutexExpiresAfterTtl(): void
    {
        $mutex = new CacheEventMutex();
        $mutex->useStore('test');

        $event = $this->createEvent('php -v');
        $event->expiresAt = 1; // 1 minute

        $this->assertTrue($mutex->create($event, 1));

        $this->assertTrue($mutex->exists($event));

        $event->expiresAt = 0;
        $this->assertFalse($mutex->exists($event));
    }

    public function testMutexCanBeCleared(): void
    {
        $mutex = new CacheEventMutex();
        $mutex->useStore('test');

        $event = $this->createEvent('php -v');

        // Create mutex
        $this->assertTrue($mutex->create($event));
        $this->assertTrue($mutex->exists($event));

        // Clear mutex
        $mutex->forget($event);
        $this->assertFalse($mutex->exists($event));
    }

    public function testMutexWorksWithDifferentEvents(): void
    {
        $mutex = new CacheEventMutex();
        $mutex->useStore('test');

        $event1 = $this->createEvent('php -v');
        $event2 = $this->createEvent('php -m');

        // Both should be able to create mutexes independently
        $this->assertTrue($mutex->create($event1));
        $this->assertTrue($mutex->create($event2));

        // Both should exist
        $this->assertTrue($mutex->exists($event1));
        $this->assertTrue($mutex->exists($event2));
    }

    public function testMutexHandlesCustomTtl(): void
    {
        $mutex = new CacheEventMutex();
        $mutex->useStore('test');

        $event = $this->createEvent('php -v');

        // Create with custom TTL
        $this->assertTrue($mutex->create($event, 300)); // 5 minutes

        // Should exist
        $this->assertTrue($mutex->exists($event));
    }

    public function testMutexNameIsConsistent(): void
    {
        new CacheEventMutex();
        $event = $this->createEvent('php -v');

        $name1 = $event->mutexName();
        $name2 = $event->mutexName();

        $this->assertSame($name1, $name2);
        $this->assertNotEmpty($name1);
    }
}
