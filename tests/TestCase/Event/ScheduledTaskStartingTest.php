<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase\Event;

use Cake\TestSuite\TestCase;
use Crustum\Scheduling\CacheEventMutex;
use Crustum\Scheduling\Command\BaseSchedulerCommand;
use Crustum\Scheduling\Event;
use Crustum\Scheduling\Event\ScheduledTaskStarting;

class ScheduledTaskStartingTest extends TestCase
{
    private function createEvent(string $command): Event
    {
        $mutex = new CacheEventMutex();

        return new Event($mutex, $command);
    }

    public function testEventContainsCorrectData(): void
    {
        $command = $this->createStub(BaseSchedulerCommand::class);
        $event = $this->createEvent('php -v');

        $scheduledEvent = new ScheduledTaskStarting($command, $event);

        $this->assertSame('Scheduling.ScheduledTaskStarting', $scheduledEvent->getName());
        $this->assertSame($command, $scheduledEvent->getSubject());
        $this->assertSame($event, $scheduledEvent->getData('event'));
    }

    public function testEventCanBeDispatched(): void
    {
        $command = $this->createStub(BaseSchedulerCommand::class);
        $event = $this->createEvent('php -v');

        $scheduledEvent = new ScheduledTaskStarting($command, $event);

        $this->assertInstanceOf(\Cake\Event\Event::class, $scheduledEvent);
        $this->assertSame('Scheduling.ScheduledTaskStarting', $scheduledEvent->getName());
    }
}
