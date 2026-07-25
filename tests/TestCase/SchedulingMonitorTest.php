<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\Scheduling\CacheEventMutex;
use Crustum\Scheduling\Event;
use Crustum\Scheduling\SchedulingMonitor;

class SchedulingMonitorTest extends TestCase
{
    public function testLimitMonitorNameLeavesShortNamesUnchanged(): void
    {
        $this->assertSame('short-name', SchedulingMonitor::limitMonitorName('short-name'));
    }

    public function testLimitMonitorNameTruncatesToMaxLength(): void
    {
        $name = str_repeat('a', SchedulingMonitor::MAX_NAME_LENGTH + 50);
        $limited = SchedulingMonitor::limitMonitorName($name);

        $this->assertSame(SchedulingMonitor::MAX_NAME_LENGTH, mb_strlen($limited));
        $this->assertSame(str_repeat('a', SchedulingMonitor::MAX_NAME_LENGTH), $limited);
    }

    public function testGenerateEventNameTruncatesCustomMonitorName(): void
    {
        $event = new Event(new CacheEventMutex(), 'php foo');
        $event->monitorName(str_repeat('m', SchedulingMonitor::MAX_NAME_LENGTH + 20));

        $name = SchedulingMonitor::generateEventName($event, 'php foo');

        $this->assertSame(SchedulingMonitor::MAX_NAME_LENGTH, mb_strlen($name));
    }

    public function testGenerateEventNameTruncatesDescription(): void
    {
        $event = new Event(new CacheEventMutex(), 'php foo');
        $event->description(str_repeat('d', SchedulingMonitor::MAX_NAME_LENGTH + 10));

        $name = SchedulingMonitor::generateEventName($event, 'php foo');

        $this->assertSame(SchedulingMonitor::MAX_NAME_LENGTH, mb_strlen($name));
    }

    public function testGenerateEventNameTruncatesCommandDerivedName(): void
    {
        $longCommand = 'php ' . str_repeat('x', SchedulingMonitor::MAX_NAME_LENGTH);
        $event = new Event(new CacheEventMutex(), $longCommand);

        $name = SchedulingMonitor::generateEventName($event, $longCommand);

        $this->assertLessThanOrEqual(SchedulingMonitor::MAX_NAME_LENGTH, mb_strlen($name));
    }
}
