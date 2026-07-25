<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase\Command;

use Cake\Console\TestSuite\ConsoleIntegrationTestTrait;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;

class ScheduleMonitorListCommandTest extends TestCase
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

    public function testListCommandWithNoTasks(): void
    {
        $this->exec('schedule monitor list');

        $this->assertExitSuccess();
        $this->assertOutputContains('No monitored tasks found');
    }

    public function testListCommandShowsTasks(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $task = $table->newEntity([
            'name' => 'test-command-list',
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $table->saveOrFail($task);

        $this->exec('schedule monitor list');

        $this->assertExitSuccess();
        $this->assertOutputContains('test-command-list');
        $this->assertOutputContains('Statistics:');
    }

    public function testListCommandWithJsonFormat(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $task = $table->newEntity([
            'name' => 'test-command-json',
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $table->saveOrFail($task);

        $this->exec('schedule monitor list --format=json');

        $this->assertExitSuccess();
        $output = $this->_out->output();
        $this->assertStringContainsString('test-command-json', $output);
    }

    public function testListCommandFiltersByStatus(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $failedTask = $table->newEntity([
            'name' => 'failed-task',
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_failed' => DateTime::now(),
        ]);
        $table->saveOrFail($failedTask);

        $this->exec('schedule monitor list --status=failed');

        $this->assertExitSuccess();
        $this->assertOutputContains('failed-task');
    }

    public function testListCommandFiltersByType(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $commandTask = $table->newEntity([
            'name' => 'command-task',
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $table->saveOrFail($commandTask);

        $execTask = $table->newEntity([
            'name' => 'exec-task',
            'type' => 'exec',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $table->saveOrFail($execTask);

        $this->exec('schedule monitor list --type=cake_command');

        $this->assertExitSuccess();
        $this->assertOutputContains('command-task');
        $this->assertOutputNotContains('exec-task');
    }

    public function testListCommandFiltersByRecent(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $recentTask = $table->newEntity([
            'name' => 'recent-task',
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now(),
        ]);
        $table->saveOrFail($recentTask);

        $oldTask = $table->newEntity([
            'name' => 'old-task',
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now()->subDays(2),
        ]);
        $table->saveOrFail($oldTask);

        $this->exec('schedule monitor list --recent=24');

        $this->assertExitSuccess();
        $this->assertOutputContains('recent-task');
    }
}
