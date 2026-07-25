<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase\Event;

use Cake\TestSuite\TestCase;
use Crustum\Scheduling\CacheEventMutex;
use Crustum\Scheduling\Command\BaseSchedulerCommand;
use Crustum\Scheduling\Event;
use Crustum\Scheduling\Event\ScheduledTaskFailed;

class ScheduledTaskFailedTest extends TestCase
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
        $exception = new \Exception('Command failed');

        $scheduledEvent = new ScheduledTaskFailed($command, $event, $exception);

        $this->assertSame('Scheduling.ScheduledTaskFailed', $scheduledEvent->getName());
        $this->assertSame($command, $scheduledEvent->getSubject());
        $this->assertSame($event, $scheduledEvent->getData('event'));
        $this->assertSame($exception, $scheduledEvent->getData('exception'));
    }

    public function testEventCanBeDispatched(): void
    {
        $command = $this->createStub(BaseSchedulerCommand::class);
        $event = $this->createEvent('php -v');
        $exception = new \Exception('Command failed');

        $scheduledEvent = new ScheduledTaskFailed($command, $event, $exception);

        // Test that event can be dispatched (no exception thrown)
        $this->assertInstanceOf(\Cake\Event\Event::class, $scheduledEvent);
        $this->assertSame('Scheduling.ScheduledTaskFailed', $scheduledEvent->getName());
    }
}
