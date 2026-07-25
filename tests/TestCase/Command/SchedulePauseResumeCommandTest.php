<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Scheduling\Schedule;

class SchedulePauseResumeCommandTest extends TestCase
{
    use ConsoleIntegrationTestTrait;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAppNamespace();
        $this->configApplication(
            \TestApp\Application::class,
            [PLUGIN_TESTS . 'TestApp' . DS . 'config'],
        );

        Schedule::$pausable = true;
        Schedule::$interruptible = true;
        Schedule::resume();
        Schedule::clearInterruptSignal();
        Configure::write('Scheduling.definitions', []);
    }

    protected function tearDown(): void
    {
        Schedule::$pausable = true;
        Schedule::$interruptible = true;
        Schedule::resume();
        Schedule::clearInterruptSignal();
        parent::tearDown();
    }

    public function testPauseAndResume(): void
    {
        $this->exec('schedule pause');
        $this->assertExitSuccess();
        $this->assertOutputContains('Scheduled task processing has been paused.');
        $this->assertTrue(Schedule::isPaused());

        $this->exec('schedule resume');
        $this->assertExitSuccess();
        $this->assertOutputContains('Scheduled task processing has resumed.');
        $this->assertFalse(Schedule::isPaused());
    }

    public function testPauseWarnsWhenDisabled(): void
    {
        Schedule::$pausable = false;

        $this->exec('schedule pause');
        $this->assertExitError();
        $this->assertErrorContains('Schedule pausing is currently disabled.');
        $this->assertFalse(Schedule::isPaused());
    }

    public function testInterruptSetsSignal(): void
    {
        $this->exec('schedule interrupt');
        $this->assertExitSuccess();
        $this->assertOutputContains('Broadcasting schedule interrupt signal.');
        $this->assertTrue(Schedule::shouldInterrupt());
    }

    public function testWithoutInterruptionPollingDisablesChecks(): void
    {
        Schedule::pause();
        Schedule::interrupt();
        Schedule::withoutInterruptionPolling();

        $this->assertFalse(Schedule::isPaused());
        $this->assertFalse(Schedule::shouldInterrupt());
    }

    public function testPausedRunSkipsEventsUnlessEvenWhenPaused(): void
    {
        Schedule::pause();

        $schedule = new Schedule();
        $ran = false;
        $schedule->call(function () use (&$ran): void {
            $ran = true;
        })->everyMinute();

        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule run -v');
        $this->assertExitSuccess();
        $this->assertFalse($ran);

        $ranWhenPaused = false;
        $schedule2 = new Schedule();
        $schedule2->call(function () use (&$ranWhenPaused): void {
            $ranWhenPaused = true;
        })->everyMinute()->evenWhenPaused();

        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule2);

        $this->exec('schedule run -v');
        $this->assertExitSuccess();
        $this->assertTrue($ranWhenPaused);
    }
}
