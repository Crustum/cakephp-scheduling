<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Scheduling\Schedule;

class ScheduleListCommandTest extends TestCase
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

        // Override the default configuration to have no scheduled events
        Configure::write('Scheduling.definitions', []);
    }

    public function testListWithNoEvents(): void
    {
        $schedule = new Schedule();
        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule list');

        $this->assertExitSuccess();
        $this->assertOutputContains('No scheduled events are defined');
    }

    public function testListWithEvents(): void
    {
        $schedule = new Schedule();
        $schedule->command('echo "daily"')->daily();
        $schedule->command('echo "hourly"')->hourly();

        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule list');

        $this->assertExitSuccess();
        $this->assertOutputContains('Found 2 scheduled event(s)');
        $this->assertOutputContains('echo "daily"');
        $this->assertOutputContains('echo "hourly"');
    }

    public function testListWithVerboseOutput(): void
    {
        $schedule = new Schedule();
        $schedule->command('echo "test"')->daily()->description('Test command');

        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule list --verbose');

        $this->assertExitSuccess();
        $this->assertOutputContains('Test command');
    }

    public function testListConvertsExpressionToDisplayTimezone(): void
    {
        \Cake\Chronos\Chronos::setTestNow('2026-01-15 12:00:00');

        $schedule = new Schedule();
        $schedule->command('echo "daily"')->dailyAt('08:00')->timezone('America/Los_Angeles');

        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule list --timezone=UTC');

        $this->assertExitSuccess();
        $this->assertOutputContains('0 16 * * *');

        \Cake\Chronos\Chronos::setTestNow();
    }

    public function testListSplitsExpressionWhenMixedCarry(): void
    {
        \Cake\Chronos\Chronos::setTestNow('2026-01-15 12:00:00');

        $schedule = new Schedule();
        $schedule->command('echo "twice"')->twiceDaily(13, 17)->timezone('America/Los_Angeles');

        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule list --timezone=UTC');

        $this->assertExitSuccess();
        $this->assertOutputContains('0 21 * * *');
        $this->assertOutputContains('0 1 * * *');
        $this->assertOutputContains('Found 1 scheduled event(s)');

        \Cake\Chronos\Chronos::setTestNow();
    }
}
