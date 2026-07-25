<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase;

use Cake\Chronos\Chronos;
use Cake\TestSuite\TestCase;
use Crustum\Scheduling\CacheEventMutex;
use Crustum\Scheduling\Event;

class EventTest extends TestCase
{
    private function createEvent(string $command): Event
    {
        $mutex = new CacheEventMutex();

        return new Event($mutex, $command);
    }

    public function testBuildCommandWithOutputRedirectionUnix(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Unix-specific test');
        }

        $event = $this->createEvent('php -v');
        $event->sendOutputTo('/tmp/test.log');

        $command = $event->buildCommand();

        $this->assertStringContainsString('php -v', $command);
        $this->assertStringContainsString('/tmp/test.log', $command);
    }

    public function testBuildCommandWithOutputRedirectionWindows(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('Windows-specific test');
        }

        $event = $this->createEvent('php -v');
        $event->sendOutputTo('C:\\temp\\test.log');

        $command = $event->buildCommand();

        $this->assertStringContainsString('php -v', $command);
        $this->assertStringContainsString('C:\\temp\\test.log', $command);
    }

    public function testBuildCommandWithAppendOutputUnix(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Unix-specific test');
        }

        $event = $this->createEvent('php -v');
        $event->appendOutputTo('/var/log/app.log');

        $command = $event->buildCommand();

        $this->assertStringContainsString('php -v', $command);
        $this->assertStringContainsString(">> '/var/log/app.log'", $command);
    }

    public function testBuildCommandWithAppendOutputWindows(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('Windows-specific test');
        }

        $event = $this->createEvent('php -v');
        $event->appendOutputTo('C:\\logs\\app.log');

        $command = $event->buildCommand();

        $this->assertStringContainsString('php -v', $command);
        $this->assertStringContainsString('>> "C:\\logs\\app.log"', $command);
    }

    public function testCronExpressionGeneration(): void
    {
        $event = $this->createEvent('php -v');
        $event->daily()->at('10:30');

        $this->assertSame('30 10 * * *', $event->getExpression());
    }

    public function testCronExpressionWithMultipleFrequencies(): void
    {
        $event = $this->createEvent('php -v');
        $event->hourly()->at('15');

        $this->assertSame('0 15 * * *', $event->getExpression());
    }

    public function testEventRunsAtCorrectTime(): void
    {
        $event = $this->createEvent('php -v');
        $event->daily()->at('10:30');

        // Test at 10:30 - should be due
        Chronos::setTestNow('2024-01-01 10:30:00');
        $this->assertTrue($event->isDue());

        // Test at 10:29 - should not be due
        Chronos::setTestNow('2024-01-01 10:29:00');
        $this->assertFalse($event->isDue());

        // Reset test time
        Chronos::setTestNow();
    }

    public function testMutexPreventsOverlappingExecution(): void
    {
        $mutex = $this->createMock(\Crustum\Scheduling\EventMutexInterface::class);
        $mutex->expects($this->once())->method('create')->willReturn(false);

        $event = new Event($mutex, 'php -v');
        $event->withoutOverlapping();

        $this->assertTrue($event->shouldSkipDueToOverlapping());
    }

    public function testEventFiltersPreventExecution(): void
    {
        $event = $this->createEvent('php -v');
        $event->when(function (): false {
            return false; // Never run
        });

        $this->assertFalse($event->filtersPass());
    }

    public function testEventFiltersAllowExecution(): void
    {
        $event = $this->createEvent('php -v');
        $event->when(fn(): true => true);

        $this->assertTrue($event->filtersPass());
    }

    public function testRepeatableEventBehavior(): void
    {
        $event = $this->createEvent('php -v');
        $event->repeatSeconds = 30;

        $this->assertTrue($event->isRepeatable());
        $this->assertTrue($event->shouldRepeatNow());
    }

    public function testWhenWithCallable(): void
    {
        $event = $this->createEvent('php -v');
        $result = $event->when(fn(): true => true);

        $this->assertSame($event, $result);
        $this->assertTrue($event->filtersPass());
    }

    public function testWhenWithBooleanTrue(): void
    {
        $event = $this->createEvent('php -v');
        $event->when(true);

        $this->assertTrue($event->filtersPass());
    }

    public function testWhenWithBooleanFalse(): void
    {
        $event = $this->createEvent('php -v');
        $event->when(false);

        $this->assertFalse($event->filtersPass());
    }

    public function testWhenWithMultipleFilters(): void
    {
        $event = $this->createEvent('php -v');
        $event->when(fn(): true => true);
        $event->when(fn(): true => true);

        $this->assertTrue($event->filtersPass());
    }

    public function testWhenWithMultipleFiltersOneFails(): void
    {
        $event = $this->createEvent('php -v');
        $event->when(fn(): true => true);
        $event->when(fn(): false => false);

        $this->assertFalse($event->filtersPass());
    }

    public function testSkipWithCallable(): void
    {
        $event = $this->createEvent('php -v');
        $result = $event->skip(fn(): false => false);

        $this->assertSame($event, $result);
        $this->assertTrue($event->filtersPass());
    }

    public function testSkipWithCallableReturningTrue(): void
    {
        $event = $this->createEvent('php -v');
        $event->skip(fn(): true => true);

        $this->assertFalse($event->filtersPass());
    }

    public function testSkipWithBooleanTrue(): void
    {
        $event = $this->createEvent('php -v');
        $event->skip(true);

        $this->assertFalse($event->filtersPass());
    }

    public function testSkipWithBooleanFalse(): void
    {
        $event = $this->createEvent('php -v');
        $event->skip(false);

        $this->assertTrue($event->filtersPass());
    }

    public function testBetweenTimeFilter(): void
    {
        Chronos::setTestNow('2024-01-01 09:00:00');

        $event = $this->createEvent('php -v');
        $event->between('08:00', '10:00');

        $this->assertTrue($event->filtersPass());

        Chronos::setTestNow();
    }

    public function testBetweenTimeFilterOutsideRange(): void
    {
        Chronos::setTestNow('2024-01-01 11:00:00');

        $event = $this->createEvent('php -v');
        $event->between('08:00', '10:00');

        $this->assertFalse($event->filtersPass());

        Chronos::setTestNow();
    }

    public function testBetweenTimeFilterWithOvernightRange(): void
    {
        Chronos::setTestNow('2024-01-01 23:00:00');

        $event = $this->createEvent('php -v');
        $event->between('22:00', '02:00');

        $this->assertTrue($event->filtersPass());

        Chronos::setTestNow();
    }

    public function testUnlessBetweenTimeFilter(): void
    {
        Chronos::setTestNow('2024-01-01 11:00:00');

        $event = $this->createEvent('php -v');
        $event->unlessBetween('08:00', '10:00');

        $this->assertTrue($event->filtersPass());

        Chronos::setTestNow();
    }

    public function testUnlessBetweenTimeFilterInsideRange(): void
    {
        Chronos::setTestNow('2024-01-01 09:00:00');

        $event = $this->createEvent('php -v');
        $event->unlessBetween('08:00', '10:00');

        $this->assertFalse($event->filtersPass());

        Chronos::setTestNow();
    }

    public function testEvenInMaintenanceMode(): void
    {
        $event = $this->createEvent('php -v');
        $event->evenInMaintenanceMode();

        $this->assertTrue($event->runsInMaintenanceMode());
    }

    public function testFiltersPassWithNoFilters(): void
    {
        $event = $this->createEvent('php -v');

        $this->assertTrue($event->filtersPass());
    }

    public function testFiltersPassWithWhenAndSkip(): void
    {
        $event = $this->createEvent('php -v');
        $event->when(fn(): true => true);
        $event->skip(fn(): false => false);

        $this->assertTrue($event->filtersPass());
    }

    public function testFiltersPassWithWhenAndSkipRejecting(): void
    {
        $event = $this->createEvent('php -v');
        $event->when(fn(): true => true);
        $event->skip(fn(): true => true);

        $this->assertFalse($event->filtersPass());
    }

    public function testRunInBackground(): void
    {
        $event = $this->createEvent('php -v');
        $result = $event->runInBackground();

        $this->assertSame($event, $result);
        $this->assertTrue($event->runInBackground);
    }

    public function testOnOneServer(): void
    {
        $event = $this->createEvent('php -v');
        $result = $event->onOneServer();

        $this->assertSame($event, $result);
        $this->assertTrue($event->onOneServer);
    }

    public function testUserAttribute(): void
    {
        $event = $this->createEvent('php -v');
        $event->user('testuser');

        $this->assertEquals('testuser', $event->user);
    }

    public function testBetweenResolvesTimezoneAtFilterTime(): void
    {
        Chronos::setTestNow('2024-01-01 14:00:00');

        $event = $this->createEvent('php -v');
        $event->between('08:00', '10:00')->timezone('America/New_York');

        $this->assertTrue($event->filtersPass());

        Chronos::setTestNow();
    }

    public function testBuildCommandAsUserEscapesInnerCommand(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Unix-specific quoting');
        }

        $event = $this->createEvent("echo 'hello'");
        $event->user = 'deploy';

        $command = $event->buildCommand();

        $this->assertStringContainsString("sudo -u deploy -- sh -c '", $command);
        $this->assertStringNotContainsString("sh -c 'echo 'hello''", $command);
    }

    public function testSkippedBecauseOverlappingFlag(): void
    {
        $event = $this->createEvent('php -v');
        $event->name('overlap-test')->withoutOverlapping();

        $event->mutex->create($event);
        $event->run();

        $this->assertTrue($event->skippedBecauseOverlapping);
    }

    public function testBeforeCallbackReceivesTypedEvent(): void
    {
        $received = null;
        $event = $this->createEvent('php -v');
        $event->before(function (Event $scheduled) use (&$received): void {
            $received = $scheduled;
        });

        $event->callBeforeCallbacks();

        $this->assertSame($event, $received);
    }

    public function testOnSuccessCallbackRuns(): void
    {
        $called = false;
        $event = $this->createEvent('php -v');
        $event->exitCode = 0;
        $event->onSuccess(function () use (&$called): void {
            $called = true;
        });
        $event->callAfterCallbacks();

        $this->assertTrue($called);
    }
}
