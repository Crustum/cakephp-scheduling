<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\Scheduling\CacheEventMutex;
use Crustum\Scheduling\Event;
use Crustum\Scheduling\PendingEventAttributes;
use Crustum\Scheduling\Schedule;

class PendingEventAttributesTest extends TestCase
{
    protected Schedule $schedule;

    protected PendingEventAttributes $attributes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->schedule = new Schedule();
        $this->attributes = new PendingEventAttributes($this->schedule);
    }

    public function testConstructor(): void
    {
        $this->assertInstanceOf(PendingEventAttributes::class, $this->attributes);
        $this->assertNull($this->attributes->output);
        $this->assertFalse($this->attributes->shouldAppendOutput);
    }

    public function testWithoutOverlapping(): void
    {
        $result = $this->attributes->withoutOverlapping(60);

        $this->assertSame($this->attributes, $result);
        $this->assertTrue($this->attributes->withoutOverlapping);
        $this->assertEquals(60, $this->attributes->expiresAt);
        $this->assertTrue($this->attributes->releaseOnTerminationSignals);
    }

    public function testWithoutOverlappingDisablesReleaseOnTerminationSignals(): void
    {
        $this->attributes->withoutOverlapping(120, false);

        $this->assertTrue($this->attributes->withoutOverlapping);
        $this->assertEquals(120, $this->attributes->expiresAt);
        $this->assertFalse($this->attributes->releaseOnTerminationSignals);
    }

    public function testWithoutOverlappingWithDefaultExpiration(): void
    {
        $this->attributes->withoutOverlapping();

        $this->assertTrue($this->attributes->withoutOverlapping);
        $this->assertEquals(1440, $this->attributes->expiresAt);
    }

    public function testMergeAttributesWithBasicProperties(): void
    {
        $mutex = new CacheEventMutex();
        $event = new Event($mutex, 'test:command');

        $this->attributes->expression = '0 0 * * *';
        $this->attributes->repeatSeconds = 30;
        $this->attributes->timezone = 'UTC';
        $this->attributes->user = 'testuser';
        $this->attributes->name('Test Description');

        $this->attributes->mergeAttributes($event);

        $this->assertEquals('0 0 * * *', $event->expression);
        $this->assertEquals(30, $event->repeatSeconds);
        $this->assertEquals('Test Description', $event->getSummaryForDisplay());
        $this->assertEquals('UTC', $event->timezone);
        $this->assertEquals('testuser', $event->user);
    }

    public function testMergeAttributesWithEvenInMaintenanceMode(): void
    {
        $mutex = new CacheEventMutex();
        $event = new Event($mutex, 'test:command');

        $this->attributes->evenInMaintenanceMode = true;

        $this->attributes->mergeAttributes($event);

        $this->assertTrue($event->runsInMaintenanceMode());
    }

    public function testMergeAttributesWithWithoutOverlapping(): void
    {
        $mutex = new CacheEventMutex();
        $event = new Event($mutex, 'test:command');
        $event->name('test-event');

        $this->attributes->withoutOverlapping(120);

        $this->attributes->mergeAttributes($event);

        $this->assertTrue($this->attributes->withoutOverlapping);
    }

    public function testMergeAttributesWithOnOneServer(): void
    {
        $mutex = new CacheEventMutex();
        $event = new Event($mutex, 'test:command');

        $this->attributes->onOneServer = true;

        $this->attributes->mergeAttributes($event);

        $this->assertTrue($this->attributes->onOneServer);
    }

    public function testMergeAttributesWithRunInBackground(): void
    {
        $mutex = new CacheEventMutex();
        $event = new Event($mutex, 'test:command');

        $this->attributes->runInBackground = true;

        $this->attributes->mergeAttributes($event);

        $this->assertTrue($this->attributes->runInBackground);
    }

    public function testMergeAttributesWithFilters(): void
    {
        $mutex = new CacheEventMutex();
        $event = new Event($mutex, 'test:command');

        $this->attributes->when(fn(): true => true);

        $this->attributes->mergeAttributes($event);

        $this->assertTrue($event->filtersPass());
    }

    public function testMergeAttributesWithRejects(): void
    {
        $mutex = new CacheEventMutex();
        $event = new Event($mutex, 'test:command');

        $this->attributes->skip(fn(): true => true);

        $this->attributes->mergeAttributes($event);

        $this->assertFalse($event->filtersPass());
    }

    public function testMergeAttributesWithNullDescription(): void
    {
        $mutex = new CacheEventMutex();
        $event = new Event($mutex, 'test:command');
        $originalDescription = $event->getSummaryForDisplay();

        $this->attributes->mergeAttributes($event);

        $this->assertEquals($originalDescription, $event->getSummaryForDisplay());
    }

    public function testMergeAttributesWithNullTimezone(): void
    {
        $mutex = new CacheEventMutex();
        $event = new Event($mutex, 'test:command');
        $originalTimezone = $event->timezone;

        $this->attributes->timezone = null;

        $this->attributes->mergeAttributes($event);

        $this->assertEquals($originalTimezone, $event->timezone);
    }

    public function testMagicCallProxiesToSchedule(): void
    {
        $result = $this->attributes->daily();

        $this->assertSame($this->attributes, $result);
        $this->assertNotNull($this->attributes->expression);
    }

    public function testMagicCallWithParameters(): void
    {
        $result = $this->attributes->hourly();

        $this->assertSame($this->attributes, $result);
    }
}
