<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase\Service;

use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Crustum\Scheduling\Schedule;
use Crustum\Scheduling\Service\ScheduleMonitorService;

class ScheduleMonitorServiceTest extends TestCase
{
    protected Schedule $schedule;

    protected ScheduleMonitorService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAppNamespace();

        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $table->deleteAll([]);

        Configure::write('Scheduling.definitions', []);
        $this->schedule = new Schedule();
        $this->service = new ScheduleMonitorService($this->schedule);
    }

    public function testSyncMonitoredTasksCreatesNewTasks(): void
    {
        $taskName = 'test-create-' . uniqid();
        $this->schedule->command('test:command')->everyMinute()->useMonitoring()->monitorName($taskName);

        $result = $this->service->syncMonitoredTasks();

        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(1, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['removed']);

        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $task = $table->find()->where(['name' => $taskName])->first();

        $this->assertNotNull($task);
        $this->assertEquals($taskName, $task->get('name'));
        $this->assertEquals('* * * * *', $task->get('cron_expression'));
    }

    public function testSyncMonitoredTasksUpdatesExistingTasks(): void
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

        $this->schedule->command('test:command')->daily()->useMonitoring()->monitorName($taskName);

        $result = $this->service->syncMonitoredTasks();

        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(0, $result['created']);
        $this->assertEquals(1, $result['updated']);
        $this->assertEquals(0, $result['removed']);

        $updatedTask = $table->find()->where(['name' => $taskName])->first();
        $this->assertNotNull($updatedTask);
        $this->assertEquals('0 0 * * *', $updatedTask->get('cron_expression'));
    }

    public function testSyncMonitoredTasksRemovesOldTasks(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $oldTaskName = 'old-task-' . uniqid();
        $newTaskName = 'new-task-' . uniqid();
        $oldTask = $table->newEntity([
            'name' => $oldTaskName,
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $table->saveOrFail($oldTask);

        $this->schedule->command('test:command')->everyMinute()->useMonitoring()->monitorName($newTaskName);

        $result = $this->service->syncMonitoredTasks();

        $this->assertEquals(1, $result['synced']);
        $this->assertEquals(1, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['removed']);

        $removedTask = $table->find()->where(['name' => $oldTaskName])->first();
        $this->assertNull($removedTask);
    }

    public function testSyncMonitoredTasksSkipsNonMonitoredEvents(): void
    {
        $this->schedule->command('test:command')->everyMinute()->disableMonitoring();

        $result = $this->service->syncMonitoredTasks();

        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(0, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['removed']);

        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $task = $table->find()->where(['name' => 'test-command'])->first();
        $this->assertNull($task);
    }

    public function testSyncMonitoredTasksRemovesTasksThatBecomeNonMonitored(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $taskName = 'test-become-non-monitored-' . uniqid();
        $task = $table->newEntity([
            'name' => $taskName,
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $table->saveOrFail($task);

        $this->schedule->command('test:command')->everyMinute()->disableMonitoring();

        $result = $this->service->syncMonitoredTasks();

        $this->assertEquals(0, $result['synced']);
        $this->assertEquals(0, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(1, $result['removed']);

        $removedTask = $table->find()->where(['name' => $taskName])->first();
        $this->assertNull($removedTask);
    }

    public function testSyncMonitoredTasksGeneratesNameForCallbacksWithoutName(): void
    {
        $this->schedule->call(fn(): true => true)->everyMinute()->useMonitoring();

        $result = $this->service->syncMonitoredTasks();

        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $tasks = $table->find()->toArray();

        $this->assertEquals(1, $result['synced']);
        $this->assertCount(1, $tasks);
        $this->assertStringStartsWith('callback-', $tasks[0]->get('name'));
    }

    public function testSyncMonitoredTasksHandlesMultipleTasks(): void
    {
        $this->schedule->command('test:command1')->everyMinute()->useMonitoring()->monitorName('test-command-1');
        $this->schedule->command('test:command2')->hourly()->useMonitoring()->monitorName('test-command-2');
        $this->schedule->exec('echo test')->daily()->useMonitoring()->monitorName('test-exec');

        $result = $this->service->syncMonitoredTasks();

        $this->assertEquals(3, $result['synced']);
        $this->assertEquals(3, $result['created']);
        $this->assertEquals(0, $result['updated']);
        $this->assertEquals(0, $result['removed']);

        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $tasks = $table->find()->toArray();
        $this->assertCount(3, $tasks);
    }

    public function testSyncMonitoredTasksHandlesCustomGraceTime(): void
    {
        $taskName = 'test-grace-time-' . uniqid();
        $this->schedule->command('test:command')->everyMinute()
            ->useMonitoring()
            ->monitorName($taskName)
            ->graceTimeInMinutes(15);

        $this->service->syncMonitoredTasks();

        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $task = $table->find()->where(['name' => $taskName])->first();

        $this->assertNotNull($task);
        $this->assertEquals(15, $task->get('grace_time_in_minutes'));
    }

    public function testSyncMonitoredTasksHandlesTimezone(): void
    {
        $taskName = 'test-timezone-' . uniqid();
        $this->schedule->command('test:command')->everyMinute()
            ->useMonitoring()
            ->monitorName($taskName)
            ->timezone('Asia/Kolkata');

        $this->service->syncMonitoredTasks();

        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $task = $table->find()->where(['name' => $taskName])->first();

        $this->assertNotNull($task);
        $this->assertEquals('Asia/Kolkata', $task->get('timezone'));
    }

    public function testSyncMonitoredTasksHandlesRepeatSeconds(): void
    {
        $taskName = 'test-repeat-' . uniqid();
        $event = $this->schedule->command('test:command')->everyMinute()->useMonitoring()->monitorName($taskName);
        $event->repeatSeconds = 30;

        $this->service->syncMonitoredTasks();

        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $task = $table->find()->where(['name' => $taskName])->first();

        $this->assertNotNull($task);
        $this->assertEquals('*/30 * * * * *', $task->get('cron_expression'));
    }

    public function testSyncMonitoredTasksHandlesCallbackEvents(): void
    {
        $taskName = 'test-callback-' . uniqid();
        $this->schedule->call(fn(): true => true)->everyMinute()->useMonitoring()->monitorName($taskName);

        $result = $this->service->syncMonitoredTasks();

        $this->assertEquals(1, $result['synced']);

        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $task = $table->find()->where(['name' => $taskName])->first();

        $this->assertNotNull($task);
        $this->assertEquals('callback', $task->get('type'));
    }
}
