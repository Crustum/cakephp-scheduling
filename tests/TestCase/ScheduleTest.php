<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase;

use Cake\Chronos\Chronos;
use Cake\TestSuite\TestCase;
use Crustum\Scheduling\Event;
use Crustum\Scheduling\Schedule;

class ScheduleTest extends TestCase
{
    private function createSchedule(): Schedule
    {
        return new Schedule();
    }

    public function testScheduleAddsEventsCorrectly(): void
    {
        $schedule = $this->createSchedule();
        $schedule->command('php -v')->daily();
        $schedule->command('php -m')->hourly();

        $events = $schedule->events();

        $this->assertCount(2, $events);
        $this->assertStringContainsString('php -v', $events[0]->getCommand());
        $this->assertStringContainsString('php -m', $events[1]->getCommand());
    }

    public function testScheduleFiltersDueEvents(): void
    {
        $schedule = $this->createSchedule();
        $schedule->command('php -v')->daily()->at('10:30');
        $schedule->command('php -m')->hourly()->at('15');

        // Mock current time as 10:30
        Chronos::setTestNow('2024-01-01 10:30:00');
        $dueEvents = $schedule->dueEvents();

        $this->assertCount(1, $dueEvents);
        $this->assertStringContainsString('php -v', $dueEvents[0]->getCommand());

        // Reset test time
        Chronos::setTestNow();
    }

    public function testScheduleHandlesRepeatableEvents(): void
    {
        $schedule = $this->createSchedule();
        $event1 = $schedule->command('php -v')->daily();
        $event1->repeatSeconds = 10;
        $schedule->command('php -m')->daily();

        $events = $schedule->events();

        $this->assertCount(2, $events);
        $this->assertTrue($events[0]->isRepeatable());
        $this->assertFalse($events[1]->isRepeatable());
    }

    public function testScheduleHandlesOverlappingEvents(): void
    {
        $schedule = $this->createSchedule();
        $event = $schedule->command('php -v')->daily();
        $event->withoutOverlapping();

        // Test that serverShouldRun method exists and can be called
        $shouldRun = $schedule->serverShouldRun($event, Chronos::now());

        $this->assertIsBool($shouldRun);
    }

    public function testScheduleHandlesCallbackEvents(): void
    {
        $schedule = $this->createSchedule();
        $callback = (fn(): string => 'test result');

        $event = $schedule->call($callback);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertInstanceOf(\Crustum\Scheduling\CallbackEvent::class, $event);
    }

    public function testScheduleGroupAppliesBaseAttributesAndAllowsOverrides(): void
    {
        $schedule = $this->createSchedule();
        $schedule->daily()->group(function (Schedule $schedule): void {
            $schedule->command('php -v');
            $schedule->command('php -m')->twiceDaily();
            $schedule->command('php -i');
        });

        $events = $schedule->events();

        $this->assertCount(3, $events);
        $this->assertSame('0 0 * * *', $events[0]->getExpression());
        $this->assertSame('0 1,13 * * *', $events[1]->getExpression());
        $this->assertSame('0 0 * * *', $events[2]->getExpression());
    }

    public function testUseCacheAcceptsStringStore(): void
    {
        $mutex = new \Crustum\Scheduling\CacheEventMutex();
        $schedule = new Schedule(null, $mutex);
        $schedule->useCache('custom_store');

        $this->assertSame('custom_store', $mutex->store);
    }

    public function testUseCacheAcceptsBackedEnumStore(): void
    {
        $mutex = new \Crustum\Scheduling\CacheEventMutex();
        $schedule = new Schedule(null, $mutex);
        $schedule->useCache(TestCacheStore::Custom);

        $this->assertSame('custom_store', $mutex->store);
    }

    public function testGroupForwardsReleaseOnTerminationSignals(): void
    {
        $schedule = $this->createSchedule();
        $schedule->withoutOverlapping(60, false)->group(function (Schedule $schedule): void {
            $schedule->command('echo test')->everyMinute();
        });

        $event = $schedule->events()[0];
        $this->assertTrue($event->withoutOverlapping);
        $this->assertFalse($event->releaseOnTerminationSignals);
        $this->assertEquals(60, $event->expiresAt);
    }

    public function testGroupDeferredLifecycleCallbacks(): void
    {
        $beforeCalled = false;
        $schedule = $this->createSchedule();
        $schedule->before(function () use (&$beforeCalled): void {
            $beforeCalled = true;
        })->group(function (Schedule $schedule): void {
            $schedule->call(function (): void {
            })->everyMinute();
        });

        $event = $schedule->events()[0];
        $event->callBeforeCallbacks();

        $this->assertTrue($beforeCalled);
    }
}
