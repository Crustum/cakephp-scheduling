<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Crustum\Scheduling\Schedule;

class ScheduleMonitorSyncCommandTest extends TestCase
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
    }

    public function testSyncCommandExecutesSuccessfully(): void
    {
        $schedule = new Schedule();
        $schedule->command('test:command')->everyMinute()->useMonitoring()->monitorName('test-command');
        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule monitor sync');

        $this->assertExitSuccess();
        $this->assertOutputContains('Starting schedule sync...');
        $this->assertOutputContains('Successfully synced');
    }

    public function testSyncCommandCreatesTasks(): void
    {
        $schedule = new Schedule();
        $schedule->command('test:command')->everyMinute()->useMonitoring()->monitorName('test-command');
        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule monitor sync');

        $this->assertExitSuccess();

        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $task = $table->find()->where(['name' => 'test-command'])->first();

        $this->assertNotNull($task);
    }

    public function testSyncCommandWithDryRun(): void
    {
        $schedule = new Schedule();
        $schedule->command('test:command')->everyMinute()->useMonitoring()->monitorName('test-command');
        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule monitor sync --dry-run');

        $this->assertExitSuccess();
        $this->assertOutputContains('DRY RUN');
        $this->assertOutputContains('DRY RUN: Would sync monitored tasks');
    }

    public function testSyncCommandUpdatesExistingTasks(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $taskName = 'test-update-' . uniqid();
        $task = $table->newEntity([
            'name' => $taskName,
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $table->saveOrFail($task);

        $schedule = new Schedule();
        $schedule->command('test:command')->daily()->useMonitoring()->monitorName($taskName);
        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule monitor sync');

        $this->assertExitSuccess();

        $updatedTask = $table->find()->where(['name' => $taskName])->first();
        $this->assertNotNull($updatedTask);
        $this->assertEquals('0 0 * * *', $updatedTask->get('cron_expression'));
    }

    public function testSyncCommandRemovesOldTasks(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $oldTask = $table->newEntity([
            'name' => 'old-task',
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $table->saveOrFail($oldTask);

        $schedule = new Schedule();
        $schedule->command('test:command')->everyMinute()->useMonitoring()->monitorName('test-command');
        $this->mockService(Schedule::class, fn(): \Crustum\Scheduling\Schedule => $schedule);

        $this->exec('schedule monitor sync');

        $this->assertExitSuccess();

        $removedTask = $table->find()->where(['name' => 'old-task'])->first();
        $this->assertNull($removedTask);
    }
}
