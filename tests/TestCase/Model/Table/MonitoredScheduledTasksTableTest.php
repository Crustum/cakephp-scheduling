<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase\Model\Table;

use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem;
use Crustum\Scheduling\Model\Table\MonitoredScheduledTasksTable;

class MonitoredScheduledTasksTableTest extends TestCase
{
    protected MonitoredScheduledTasksTable $table;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAppNamespace();

        $this->table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $this->table->deleteAll([]);

        $logTable = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTaskLogItems');
        $logTable->deleteAll([]);
    }

    public function testMarkAsStarting(): void
    {
        $task = $this->table->newEntity([
            'name' => 'test-mark-starting-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $this->table->saveOrFail($task);

        $taskName = $task->get('name');
        $result = $this->table->markAsStarting($taskName, ['memory' => 1024]);

        $this->assertTrue($result);

        $updatedTask = $this->table->getByName($taskName);
        $this->assertNotNull($updatedTask);
        $this->assertNotNull($updatedTask->get('last_started'));

        $logTable = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTaskLogItems');
        $logItem = $logTable->find()
            ->where([
                'monitored_scheduled_task_id' => $updatedTask->get('id'),
                'type' => MonitoredScheduledTaskLogItem::TYPE_STARTING,
            ])
            ->first();

        $this->assertNotNull($logItem);
    }

    public function testMarkAsFinished(): void
    {
        $task = $this->table->newEntity([
            'name' => 'test-mark-finished-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now(),
        ]);
        $this->table->saveOrFail($task);

        $taskName = $task->get('name');
        $result = $this->table->markAsFinished($taskName, [
            'runtime' => 1.5,
            'exit_code' => 0,
        ]);

        $this->assertTrue($result);

        $updatedTask = $this->table->getByName($taskName);
        $this->assertNotNull($updatedTask);
        $this->assertNotNull($updatedTask->get('last_finished'));
    }

    public function testMarkAsFailed(): void
    {
        $task = $this->table->newEntity([
            'name' => 'test-mark-failed-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now(),
        ]);
        $this->table->saveOrFail($task);

        $taskName = $task->get('name');
        $result = $this->table->markAsFailed($taskName, [
            'failure_message' => 'Test failure',
            'exit_code' => 1,
        ]);

        $this->assertTrue($result);

        $updatedTask = $this->table->getByName($taskName);
        $this->assertNotNull($updatedTask);
        $this->assertNotNull($updatedTask->get('last_failed'));
    }

    public function testMarkAsSkipped(): void
    {
        $task = $this->table->newEntity([
            'name' => 'test-mark-skipped-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $this->table->saveOrFail($task);

        $taskName = $task->get('name');
        $result = $this->table->markAsSkipped($taskName, [
            'skip_reason' => 'Filter condition not met',
        ]);

        $this->assertTrue($result);

        $updatedTask = $this->table->getByName($taskName);
        $this->assertNotNull($updatedTask);
        $this->assertNotNull($updatedTask->get('last_skipped'));
    }

    public function testFindRunning(): void
    {
        $runningTask = $this->table->newEntity([
            'name' => 'running-task-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now(),
        ]);
        $this->table->saveOrFail($runningTask);

        $completedTask = $this->table->newEntity([
            'name' => 'completed-task',
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now()->subMinutes(10),
            'last_finished' => DateTime::now(),
        ]);
        $this->table->saveOrFail($completedTask);

        $query = $this->table->find();
        $runningQuery = $this->table->findRunning($query, []);
        $runningTasks = $runningQuery->toArray();

        $this->assertCount(1, $runningTasks);
        $this->assertEquals($runningTask->get('name'), $runningTasks[0]->get('name'));
    }

    public function testFindFailed(): void
    {
        $failedTask = $this->table->newEntity([
            'name' => 'failed-task-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_failed' => DateTime::now(),
        ]);
        $this->table->saveOrFail($failedTask);

        $query = $this->table->find();
        $failedQuery = $this->table->findFailed($query, []);
        $failedTasks = $failedQuery->toArray();

        $this->assertCount(1, $failedTasks);
        $this->assertEquals($failedTask->get('name'), $failedTasks[0]->get('name'));
    }

    public function testFindCompleted(): void
    {
        $completedTask = $this->table->newEntity([
            'name' => 'completed-task-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_finished' => DateTime::now(),
        ]);
        $this->table->saveOrFail($completedTask);

        $query = $this->table->find();
        $completedQuery = $this->table->findCompleted($query, []);
        $completedTasks = $completedQuery->toArray();

        $this->assertCount(1, $completedTasks);
        $this->assertEquals($completedTask->get('name'), $completedTasks[0]->get('name'));
    }

    public function testFindOverdue(): void
    {
        $overdueTask = $this->table->newEntity([
            'name' => 'overdue-task-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now()->subMinutes(10),
        ]);
        $this->table->saveOrFail($overdueTask);

        $query = $this->table->find();
        $overdueQuery = $this->table->findOverdue($query, []);
        $overdueTasks = $overdueQuery->toArray();

        $this->assertCount(1, $overdueTasks);
        $this->assertEquals($overdueTask->get('name'), $overdueTasks[0]->get('name'));
    }

    public function testFindOverdueWithCustomGraceTime(): void
    {
        $graceTime = 15;
        $notOverdueTask = $this->table->newEntity([
            'name' => 'not-overdue-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => $graceTime,
            'last_started' => DateTime::now()->subMinutes($graceTime),
        ]);
        $this->table->saveOrFail($notOverdueTask);

        $overdueTask = $this->table->newEntity([
            'name' => 'overdue-custom-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => $graceTime,
            'last_started' => DateTime::now()->subMinutes($graceTime + 1),
        ]);
        $this->table->saveOrFail($overdueTask);

        $query = $this->table->find();
        $overdueQuery = $this->table->findOverdue($query, ['grace_time_minutes' => $graceTime]);
        $overdueTasks = $overdueQuery->toArray();

        $this->assertCount(1, $overdueTasks);
        $this->assertEquals($overdueTask->get('name'), $overdueTasks[0]->get('name'));
    }

    public function testFindOverdueAtExactGraceTime(): void
    {
        $graceTime = 5;
        $taskAtGraceTime = $this->table->newEntity([
            'name' => 'at-grace-time-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => $graceTime,
            'last_started' => DateTime::now()->subMinutes($graceTime),
        ]);
        $this->table->saveOrFail($taskAtGraceTime);

        $query = $this->table->find();
        $overdueQuery = $this->table->findOverdue($query, ['grace_time_minutes' => $graceTime]);
        $overdueTasks = $overdueQuery->toArray();

        $this->assertCount(0, $overdueTasks);
    }

    public function testFindOverdueOneSecondOverGraceTime(): void
    {
        $graceTime = 5;
        $taskOneSecondOver = $this->table->newEntity([
            'name' => 'one-second-over-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => $graceTime,
            'last_started' => DateTime::now()->subMinutes($graceTime)->subSeconds(1),
        ]);
        $this->table->saveOrFail($taskOneSecondOver);

        $query = $this->table->find();
        $overdueQuery = $this->table->findOverdue($query, ['grace_time_minutes' => $graceTime]);
        $overdueTasks = $overdueQuery->toArray();

        $this->assertCount(1, $overdueTasks);
        $this->assertEquals($taskOneSecondOver->get('name'), $overdueTasks[0]->get('name'));
    }

    public function testFindOverdueExcludesFinishedTasks(): void
    {
        $graceTime = 5;
        $finishedTask = $this->table->newEntity([
            'name' => 'finished-not-overdue-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => $graceTime,
            'last_started' => DateTime::now()->subMinutes($graceTime + 10),
            'last_finished' => DateTime::now()->subMinutes($graceTime + 5),
        ]);
        $this->table->saveOrFail($finishedTask);

        $query = $this->table->find();
        $overdueQuery = $this->table->findOverdue($query, ['grace_time_minutes' => $graceTime]);
        $overdueTasks = $overdueQuery->toArray();

        $this->assertCount(0, $overdueTasks);
    }

    public function testGetStatistics(): void
    {
        $task1 = $this->table->newEntity([
            'name' => 'task1-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now(),
        ]);
        $this->table->saveOrFail($task1);

        $task2 = $this->table->newEntity([
            'name' => 'task2-' . uniqid(),
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_failed' => DateTime::now(),
        ]);
        $this->table->saveOrFail($task2);

        $stats = $this->table->getStatistics();

        $this->assertArrayHasKey('total', $stats);
        $this->assertArrayHasKey('running', $stats);
        $this->assertArrayHasKey('failed', $stats);
        $this->assertArrayHasKey('completed', $stats);
        $this->assertArrayHasKey('overdue', $stats);
    }

    public function testGetByName(): void
    {
        $taskName = 'test-get-by-name-' . uniqid();
        $task = $this->table->newEntity([
            'name' => $taskName,
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $this->table->saveOrFail($task);

        $found = $this->table->getByName($taskName);

        $this->assertNotNull($found);
        $this->assertEquals($taskName, $found->get('name'));
    }
}
