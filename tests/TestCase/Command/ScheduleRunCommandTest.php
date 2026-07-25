<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\Core\Configure;
use Cake\TestSuite\TestCase;
use Crustum\Scheduling\Schedule;

class ScheduleRunCommandTest extends TestCase
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

        Configure::write('Scheduling.definitions', []);
    }

    public function testRunWithNoDueEvents(): void
    {
        $this->exec('schedule run -v');
        $this->assertExitSuccess();
        $this->assertOutputContains('No scheduled events are due to run.');
    }

    public function testRunWithDueEventWindows(): void
    {
        if (DIRECTORY_SEPARATOR !== '\\') {
            $this->markTestSkipped('Windows-specific test');
        }

        // Create a real schedule with a command that runs every minute
        $schedule = new Schedule();
        $schedule->command('echo "test"')->everyMinute();

        // Override the container to use our real schedule
        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule run -v');

        $this->assertExitSuccess();
        $this->assertOutputContains('Running scheduled command: php bin\\cake.php echo "test"');
    }

    public function testRunWithDueEventUnix(): void
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->markTestSkipped('Unix-specific test');
        }

        // Create a real schedule with a command that runs every minute
        $schedule = new Schedule();
        $schedule->command('echo "test"')->everyMinute();

        // Override the container to use our real schedule
        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule run -v');

        $this->assertExitSuccess();
        $this->assertOutputContains('Running scheduled command: bin/cake.php echo "test"');
    }
}
