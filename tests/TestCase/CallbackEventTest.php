<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase;

use Cake\TestSuite\TestCase;
use Crustum\Scheduling\CacheEventMutex;
use Crustum\Scheduling\CallbackEvent;
use LogicException;
use RuntimeException;

class CallbackEventTest extends TestCase
{
    protected CacheEventMutex $mutex;

    protected function setUp(): void
    {
        parent::setUp();
        $this->mutex = new CacheEventMutex();
    }

    public function testConstructorWithCallable(): void
    {
        $callback = (fn(): string => 'test');

        $event = new CallbackEvent($this->mutex, $callback);

        $this->assertInstanceOf(CallbackEvent::class, $event);
    }

    public function testConstructorWithString(): void
    {
        $event = new CallbackEvent($this->mutex, 'strlen');

        $this->assertInstanceOf(CallbackEvent::class, $event);
    }

    public function testConstructorWithInvalidCallback(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid scheduled callback event. Must be a string or callable.');

        new CallbackEvent($this->mutex, 123);
    }

    public function testConstructorWithParameters(): void
    {
        $callback = fn($a, $b): float|int|array => $a + $b;

        $event = new CallbackEvent($this->mutex, $callback, [1, 2]);

        $this->assertInstanceOf(CallbackEvent::class, $event);
    }

    public function testExecuteWithCallable(): void
    {
        $result = null;
        $callback = function () use (&$result): true {
            $result = 'executed';

            return true;
        };

        $event = new CallbackEvent($this->mutex, $callback);
        $reflection = new \ReflectionClass($event);
        $method = $reflection->getMethod('execute');

        $exitCode = $method->invoke($event);

        $this->assertEquals('executed', $result);
        $this->assertEquals(0, $exitCode);
    }

    public function testExecuteWithStringCallback(): void
    {
        $event = new CallbackEvent($this->mutex, 'strlen', ['test']);
        $reflection = new \ReflectionClass($event);
        $method = $reflection->getMethod('execute');

        $exitCode = $method->invoke($event);

        $this->assertEquals(0, $exitCode);
    }

    public function testExecuteWithFalseReturn(): void
    {
        $callback = (fn(): false => false);

        $event = new CallbackEvent($this->mutex, $callback);
        $reflection = new \ReflectionClass($event);
        $method = $reflection->getMethod('execute');

        $exitCode = $method->invoke($event);

        $this->assertEquals(1, $exitCode);
    }

    public function testExecuteWithException(): void
    {
        $callback = function (): void {
            throw new \RuntimeException('Test exception');
        };

        $event = new CallbackEvent($this->mutex, $callback);
        $reflection = new \ReflectionClass($event);
        $method = $reflection->getMethod('execute');

        $exitCode = $method->invoke($event);

        $this->assertEquals(1, $exitCode);

        $exceptionProperty = $reflection->getProperty('exception');
        $exception = $exceptionProperty->getValue($event);

        $this->assertNotNull($exception);
        $this->assertInstanceOf(\RuntimeException::class, $exception);
    }

    public function testRunThrowsExceptionIfSet(): void
    {
        $callback = function (): void {
            throw new \RuntimeException('Test exception');
        };

        $event = new CallbackEvent($this->mutex, $callback);
        $reflection = new \ReflectionClass($event);
        $method = $reflection->getMethod('execute');
        $method->invoke($event);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Test exception');

        $event->run();
    }

    public function testShouldSkipDueToOverlappingWithDescription(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);
        $event->name('test-event');

        $result = $event->shouldSkipDueToOverlapping();

        $this->assertIsBool($result);
    }

    public function testShouldSkipDueToOverlappingWithoutDescription(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);

        $result = $event->shouldSkipDueToOverlapping();

        $this->assertFalse($result);
    }

    public function testRunInBackgroundThrowsException(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Scheduled closures can not be run in the background.');

        $event->runInBackground();
    }

    public function testWithoutOverlappingRequiresName(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("A scheduled event name is required to prevent overlapping. Use the 'name' method before 'withoutOverlapping'.");

        $event->withoutOverlapping();
    }

    public function testWithoutOverlappingWithName(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);
        $event->name('test-event');

        $result = $event->withoutOverlapping(60);

        $this->assertSame($event, $result);
    }

    public function testOnOneServerRequiresName(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage("A scheduled event name is required to only run on one server. Use the 'name' method before 'onOneServer'.");

        $event->onOneServer();
    }

    public function testOnOneServerWithName(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);
        $event->name('test-event');

        $result = $event->onOneServer();

        $this->assertSame($event, $result);
    }

    public function testGetSummaryForDisplayWithDescription(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);
        $event->name('test-description');

        $summary = $event->getSummaryForDisplay();

        $this->assertEquals('test-description', $summary);
    }

    public function testGetSummaryForDisplayWithStringCallback(): void
    {
        $event = new CallbackEvent($this->mutex, 'strlen');

        $summary = $event->getSummaryForDisplay();

        $this->assertEquals('strlen', $summary);
    }

    public function testGetSummaryForDisplayWithCallable(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);

        $summary = $event->getSummaryForDisplay();

        $this->assertEquals('Callback', $summary);
    }

    public function testMutexName(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);
        $event->name('test-event');

        $mutexName = $event->mutexName();

        $this->assertStringStartsWith('schedule-', $mutexName);
    }

    public function testMutexNameWithoutDescription(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);

        $mutexName = $event->mutexName();

        $this->assertStringStartsWith('schedule-', $mutexName);
    }

    public function testRemoveMutexWithDescription(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);
        $event->name('test-event');

        $reflection = new \ReflectionClass($event);
        $method = $reflection->getMethod('removeMutex');

        $this->assertNull($method->invoke($event));
    }

    public function testRemoveMutexWithoutDescription(): void
    {
        $event = new CallbackEvent($this->mutex, fn(): true => true);

        $reflection = new \ReflectionClass($event);
        $method = $reflection->getMethod('removeMutex');

        $this->assertNull($method->invoke($event));
    }
}
