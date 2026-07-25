<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase\Listener;

use Cake\Event\EventManager;
use Cake\I18n\DateTime;
use Cake\ORM\TableRegistry;
use Cake\TestSuite\TestCase;
use Crustum\Scheduling\Command\BaseSchedulerCommand;
use Crustum\Scheduling\Event;
use Crustum\Scheduling\Event\ScheduledTaskFailed;
use Crustum\Scheduling\Event\ScheduledTaskFinished;
use Crustum\Scheduling\Event\ScheduledTaskSkipped;
use Crustum\Scheduling\Event\ScheduledTaskStarting;
use Crustum\Scheduling\Listener\ScheduleMonitorListener;

class ScheduleMonitorListenerTest extends TestCase
{
    protected ScheduleMonitorListener $listener;

    protected EventManager $eventManager;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setAppNamespace();

        $this->listener = new ScheduleMonitorListener();
        $this->eventManager = EventManager::instance();
        $this->eventManager->on($this->listener);
    }

    protected function tearDown(): void
    {
        $this->eventManager->off($this->listener);
        parent::tearDown();
    }

    public function testBeforeTaskCreatesLogItem(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $taskName = 'test-before-task-' . uniqid();
        $task = $table->newEntity([
            'name' => $taskName,
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $table->saveOrFail($task);

        $mutex = $this->createStub(\Crustum\Scheduling\EventMutexInterface::class);
        $scheduledEvent = new Event($mutex, 'test:command');
        $scheduledEvent->useMonitoring()->monitorName($taskName);

        $command = $this->createStub(BaseSchedulerCommand::class);
        $event = new ScheduledTaskStarting($command, $scheduledEvent);

        $this->eventManager->dispatch($event);

        $updatedTask = $table->getByName($taskName);
        $this->assertNotNull($updatedTask);
        $this->assertNotNull($updatedTask->get('last_started'));

        $logTable = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTaskLogItems');
        $logItems = $logTable->find()
            ->where(['monitored_scheduled_task_id' => $updatedTask->get('id')])
            ->toArray();

        $this->assertNotEmpty($logItems);
    }

    public function testAfterTaskMarksAsFinished(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $taskName = 'test-after-task-' . uniqid();
        $task = $table->newEntity([
            'name' => $taskName,
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now(),
        ]);
        $table->saveOrFail($task);

        $mutex = $this->createStub(\Crustum\Scheduling\EventMutexInterface::class);
        $scheduledEvent = new Event($mutex, 'test:command');
        $scheduledEvent->useMonitoring()->monitorName($taskName);

        $command = $this->createStub(BaseSchedulerCommand::class);
        $event = new ScheduledTaskFinished($command, $scheduledEvent, 1.5);

        $this->eventManager->dispatch($event);

        $updatedTask = $table->getByName($taskName);
        $this->assertNotNull($updatedTask);
        $this->assertNotNull($updatedTask->get('last_finished'));
    }

    public function testTaskFailedMarksAsFailed(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $taskName = 'test-failed-task-' . uniqid();
        $task = $table->newEntity([
            'name' => $taskName,
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now(),
        ]);
        $table->saveOrFail($task);

        $mutex = $this->createStub(\Crustum\Scheduling\EventMutexInterface::class);
        $scheduledEvent = new Event($mutex, 'test:command');
        $scheduledEvent->useMonitoring()->monitorName($taskName);

        $command = $this->createStub(BaseSchedulerCommand::class);
        $exception = new \Exception('Test failure');
        $event = new ScheduledTaskFailed($command, $scheduledEvent, $exception);

        $this->eventManager->dispatch($event);

        $updatedTask = $table->getByName($taskName);
        $this->assertNotNull($updatedTask);
        $this->assertNotNull($updatedTask->get('last_failed'));
    }

    public function testTaskSkippedMarksAsSkipped(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $taskName = 'test-skipped-task-' . uniqid();
        $task = $table->newEntity([
            'name' => $taskName,
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
        ]);
        $table->saveOrFail($task);

        $mutex = $this->createStub(\Crustum\Scheduling\EventMutexInterface::class);
        $scheduledEvent = new Event($mutex, 'test:command');
        $scheduledEvent->useMonitoring()->monitorName($taskName);

        $event = new ScheduledTaskSkipped($scheduledEvent);

        $this->eventManager->dispatch($event);

        $updatedTask = $table->getByName($taskName);
        $this->assertNotNull($updatedTask);
        $this->assertNotNull($updatedTask->get('last_skipped'));
    }

    public function testBeforeTaskSkipsNonMonitoredTasks(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');

        $mutex = $this->createStub(\Crustum\Scheduling\EventMutexInterface::class);
        $scheduledEvent = new Event($mutex, 'test:command');
        $scheduledEvent->disableMonitoring();

        $command = $this->createStub(BaseSchedulerCommand::class);
        $event = new ScheduledTaskStarting($command, $scheduledEvent);

        $this->eventManager->dispatch($event);

        $task = $table->find()->where(['name' => 'test-command'])->first();
        $this->assertNull($task);
    }

    public function testAfterTaskStoresOutputWhenEnabled(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $taskName = 'test-output-task-' . uniqid();
        $task = $table->newEntity([
            'name' => $taskName,
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now(),
        ]);
        $table->saveOrFail($task);

        $outputFile = TMP . 'test_output_' . uniqid() . '.txt';
        file_put_contents($outputFile, 'Test output content');

        $mutex = $this->createStub(\Crustum\Scheduling\EventMutexInterface::class);
        $scheduledEvent = new Event($mutex, 'test:command');
        $scheduledEvent->useMonitoring()->monitorName($taskName);
        $scheduledEvent->storeOutputInDb();
        $scheduledEvent->output = $outputFile;

        $command = $this->createStub(BaseSchedulerCommand::class);
        $event = new ScheduledTaskFinished($command, $scheduledEvent, 1.5);

        $this->eventManager->dispatch($event);

        $logTable = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTaskLogItems');
        $logItem = $logTable->find()
            ->where(['monitored_scheduled_task_id' => $task->get('id')])
            ->orderByDesc('created')
            ->first();

        $this->assertNotNull($logItem);
        $meta = $logItem->get('meta');
        $this->assertArrayHasKey('output', $meta);
        $this->assertEquals('Test output content', $meta['output']);

        unlink($outputFile);
    }

    public function testAfterTaskDoesNotStoreOutputWhenDisabled(): void
    {
        $table = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTasks');
        $taskName = 'test-no-output-task-' . uniqid();
        $task = $table->newEntity([
            'name' => $taskName,
            'type' => 'cake_command',
            'cron_expression' => '* * * * *',
            'grace_time_in_minutes' => 5,
            'last_started' => DateTime::now(),
        ]);
        $table->saveOrFail($task);

        $outputFile = TMP . 'test_output_' . uniqid() . '.txt';
        file_put_contents($outputFile, 'Test output content');

        $mutex = $this->createStub(\Crustum\Scheduling\EventMutexInterface::class);
        $scheduledEvent = new Event($mutex, 'test:command');
        $scheduledEvent->useMonitoring()->monitorName($taskName);
        $scheduledEvent->output = $outputFile;

        $command = $this->createStub(BaseSchedulerCommand::class);
        $event = new ScheduledTaskFinished($command, $scheduledEvent, 1.5);

        $this->eventManager->dispatch($event);

        $logTable = TableRegistry::getTableLocator()->get('Crustum/Scheduling.MonitoredScheduledTaskLogItems');
        $logItem = $logTable->find()
            ->where(['monitored_scheduled_task_id' => $task->get('id')])
            ->orderByDesc('created')
            ->first();

        $this->assertNotNull($logItem);
        $meta = $logItem->get('meta');
        $this->assertArrayNotHasKey('output', $meta);

        unlink($outputFile);
    }
}
