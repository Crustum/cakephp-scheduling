<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Model\Table;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Crustum\Scheduling\Model\Entity\MonitoredScheduledTask;
use Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem;

/**
 * MonitoredScheduledTasks Model
 *
 * @extends \Cake\ORM\Table<array{}, \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask>
 * @property \Crustum\Scheduling\Model\Table\MonitoredScheduledTaskLogItemsTable $MonitoredScheduledTaskLogItems
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask newEmptyEntity()
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method array<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> newEntities(array<array<string, mixed>> $data, array<string, mixed> $options = [])
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask get(mixed $primaryKey, array<string, mixed>|string $finder = 'all', mixed ...$args)
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask patchEntity(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $data, array<string, mixed> $options = [])
 * @method array<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> patchEntities(iterable<\Cake\Datasource\EntityInterface> $entities, array<string, mixed> $data, array<string, mixed> $options = [])
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method iterable<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask>|false saveMany(iterable<\Cake\Datasource\EntityInterface> $entities, $options = [])
 * @method iterable<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> saveManyOrFail(iterable<\Cake\Datasource\EntityInterface> $entities, $options = [])
 * @method iterable<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask>|false deleteMany(iterable<\Cake\Datasource\EntityInterface> $entities, $options = [])
 * @method iterable<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> deleteManyOrFail(iterable<\Cake\Datasource\EntityInterface> $entities, $options = [])
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class MonitoredScheduledTasksTable extends Table
{
    /**
     * Initialize method
     *
     * @param array<string, mixed> $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('monitored_scheduled_tasks');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->hasMany('MonitoredScheduledTaskLogItems', [
            'foreignKey' => 'monitored_scheduled_task_id',
            'className' => 'Crustum/Scheduling.MonitoredScheduledTaskLogItems',
            'dependent' => true,
        ]);
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('id')
            ->allowEmptyString('id', null, 'create');

        $validator
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        $validator
            ->scalar('type')
            ->maxLength('type', 50)
            ->allowEmptyString('type');

        $validator
            ->scalar('cron_expression')
            ->maxLength('cron_expression', 255)
            ->requirePresence('cron_expression', 'create')
            ->notEmptyString('cron_expression');

        $validator
            ->scalar('timezone')
            ->maxLength('timezone', 50)
            ->allowEmptyString('timezone');

        $validator
            ->integer('grace_time_in_minutes')
            ->notEmptyString('grace_time_in_minutes');

        $validator
            ->dateTime('last_started')
            ->allowEmptyDateTime('last_started');

        $validator
            ->dateTime('last_finished')
            ->allowEmptyDateTime('last_finished');

        $validator
            ->dateTime('last_failed')
            ->allowEmptyDateTime('last_failed');

        $validator
            ->dateTime('last_skipped')
            ->allowEmptyDateTime('last_skipped');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->isUnique(['name']), ['errorField' => 'name']);

        return $rules;
    }

    /**
     * Find a monitored task by name.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> $query The query to modify
     * @param array<string, mixed> $options The options containing the name
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask>
     */
    public function findByName(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where(['name' => $options['name']]);
    }

    /**
     * Find tasks that are currently running.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> $query The query to modify
     * @param array<string, mixed> $options The options
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask>
     */
    public function findRunning(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'last_started IS NOT' => null,
            'OR' => [
                [
                    'last_finished IS' => null,
                    'last_failed IS' => null,
                ],
                [
                    'last_started > last_finished',
                    'last_started > last_failed',
                ],
            ],
        ]);
    }

    /**
     * Find tasks that have failed.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> $query The query to modify
     * @param array<string, mixed> $options The options
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask>
     */
    public function findFailed(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'last_failed IS NOT' => null,
            'OR' => [
                'last_finished IS' => null,
                'last_failed > last_finished',
            ],
        ]);
    }

    /**
     * Find tasks that completed successfully.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> $query The query to modify
     * @param array<string, mixed> $options The options
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask>
     */
    public function findCompleted(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where([
            'last_finished IS NOT' => null,
            'last_failed IS' => null,
        ]);
    }

    /**
     * Find tasks that haven't run recently.
     *
     * A task is overdue if it started more than grace time ago
     * and is still running (hasn't finished or failed since starting).
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> $query The query to modify
     * @param array<string, mixed> $options The options containing the grace time in minutes
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask>
     */
    public function findOverdue(SelectQuery $query, array $options): SelectQuery
    {
        $graceTime = $options['grace_time_minutes'] ?? 5;
        $cutoffTime = DateTime::now()->subMinutes($graceTime);

        return $this->find('running', options: $options)
            ->andWhere(['last_started <' => $cutoffTime]);
    }

    /**
     * Find tasks by type.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> $query The query to modify
     * @param array<string, mixed> $options The options containing the type
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask>
     */
    public function findByType(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where(['type' => $options['type']]);
    }

    /**
     * Find tasks with recent activity.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask> $query The query to modify
     * @param array<string, mixed> $options The options containing the hours
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask>
     */
    public function findRecent(SelectQuery $query, array $options): SelectQuery
    {
        $hours = $options['hours'] ?? 24;
        $cutoffTime = DateTime::now()->subHours($hours);

        return $query->where([
            'OR' => [
                'last_started >' => $cutoffTime,
                'last_finished >' => $cutoffTime,
                'last_failed >' => $cutoffTime,
                'last_skipped >' => $cutoffTime,
            ],
        ]);
    }

    /**
     * Get a monitored task by name.
     *
     * @param string $name The task name
     * @return \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask|null
     */
    public function getByName(string $name): ?MonitoredScheduledTask
    {
        return $this->find()->where(['name' => $name])->first();
    }

    /**
     * Sync a schedule task with the monitoring database.
     *
     * @param array<string, mixed> $taskData The task data
     * @return \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask
     */
    public function syncTask(array $taskData): MonitoredScheduledTask
    {
        $existingTask = $this->find()->where(['name' => $taskData['name']])->first();

        $task = $existingTask ? $this->patchEntity($existingTask, $taskData) : $this->newEntity($taskData);

        return $this->saveOrFail($task);
    }

    /**
     * Mark a task as started.
     *
     * @param string $name The task name
     * @param array<string, mixed> $meta The metadata
     * @return \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask|null
     */
    public function markAsStarted(string $name, array $meta = []): ?MonitoredScheduledTask
    {
        $task = $this->getByName($name);
        if (!$task instanceof \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask) {
            return null;
        }

        $task->set('last_started', DateTime::now());
        $this->saveOrFail($task);

        $this->MonitoredScheduledTaskLogItems->createLogItem(
            $task->get('id'),
            MonitoredScheduledTaskLogItem::TYPE_STARTING,
            $meta
        );

        return $task;
    }

    /**
     * Get task statistics.
     *
     * @return array<string, mixed>
     */
    public function getStatistics(): array
    {
        $total = $this->find()->count();
        $running = $this->findRunning($this->find(), [])->count();
        $failed = $this->findFailed($this->find(), [])->count();
        $completed = $this->findCompleted($this->find(), [])->count();
        $overdue = $this->findOverdue($this->find(), [])->count();

        return [
            'total' => $total,
            'running' => $running,
            'failed' => $failed,
            'completed' => $completed,
            'overdue' => $overdue,
        ];
    }

    /**
     * Get all tasks with their status information for display.
     *
     * @return array<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTask>
     */
    public function getAllTasksWithStatus(): array
    {
        return $this->find()
            ->orderBy(['modified' => 'DESC'])
            ->toArray();
    }

    /**
     * Get task status information for a single task.
     *
     * @param \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask $task The task
     * @return array<string, mixed>
     */
    public function getTaskStatusInfo($task): array
    {
        $status = $this->getTaskStatus($task);
        $isOverdue = $this->isTaskOverdue($task);

        return [
            'name' => $task->get('name'),
            'type' => $task->get('type') ?? 'N/A',
            'status' => $status,
            'cron_expression' => $task->get('cron_expression'),
            'last_started' => $this->formatDateTime($task->get('last_started')),
            'last_finished' => $this->formatDateTime($task->get('last_finished')),
            'last_failed' => $this->formatDateTime($task->get('last_failed')),
            'last_skipped' => $this->formatDateTime($task->get('last_skipped')),
            'grace_time_in_minutes' => $task->get('grace_time_in_minutes'),
            'is_overdue' => $isOverdue,
            'created' => $this->formatDateTime($task->get('created')),
            'modified' => $this->formatDateTime($task->get('modified')),
        ];
    }

    /**
     * Get all tasks with their status information for display.
     *
     * @return array<array<string, mixed>>
     */
    public function getAllTasksStatusInfo(): array
    {
        $tasks = $this->getAllTasksWithStatus();
        $result = [];

        foreach ($tasks as $task) {
            $result[] = $this->getTaskStatusInfo($task);
        }

        return $result;
    }

    /**
     * Get the status of a task.
     *
     * @param \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask $task The task
     * @return string
     */
    protected function getTaskStatus($task): string
    {
        if ($task->isRunning()) {
            return 'running';
        }

        if ($task->hasFailed()) {
            return 'failed';
        }

        if ($task->get('last_finished')) {
            return 'completed';
        }

        return 'unknown';
    }

    /**
     * Check if task is overdue.
     *
     * @param \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask $task The task
     * @return bool
     */
    protected function isTaskOverdue($task): bool
    {
        $lastFinished = $task->get('last_finished');
        if (!$lastFinished) {
            return false;
        }

        $graceTime = $task->get('grace_time_in_minutes') ?? 5;
        $expectedNextRun = $lastFinished->addMinutes($graceTime);

        return $expectedNextRun->isPast();
    }

    /**
     * Format a datetime for display.
     *
     * @param \Cake\I18n\DateTime|null $datetime The datetime
     * @param string $format The format
     * @return string
     */
    protected function formatDateTime(?DateTime $datetime, ?string $format = null): string
    {
        if (!$datetime instanceof \Cake\I18n\DateTime) {
            return 'Never';
        }

        if ($format === null) {
            $format = Configure::read('Scheduling.date_format', 'Y-m-d H:i:s');
        }

        return $datetime->format($format);
    }

    /**
     * Clean up old log items.
     *
     * @param int|null $days The number of days to keep (uses config if null)
     * @return int The number of deleted items
     */
    public function cleanupOldLogItems(?int $days = null): int
    {
        if ($days === null) {
            $days = Configure::read('Scheduling.delete_log_items_older_than_days', 30);
        }

        $cutoffDate = DateTime::now()->subDays($days);

        return $this->MonitoredScheduledTaskLogItems->deleteAll([
            'created <' => $cutoffDate,
        ]);
    }

    /**
     * Mark a task as starting.
     *
     * @param string $taskName The task name
     * @param array<string, mixed> $meta Additional metadata
     * @return bool
     */
    public function markAsStarting(string $taskName, array $meta = []): bool
    {
        $task = $this->getByName($taskName);
        if (!$task instanceof \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask) {
            return false;
        }

        $logItem = $this->MonitoredScheduledTaskLogItems->newEntity([
            'monitored_scheduled_task_id' => $task->get('id'),
            'type' => MonitoredScheduledTaskLogItem::TYPE_STARTING,
            'meta' => $meta,
        ]);
        $this->MonitoredScheduledTaskLogItems->save($logItem);

        $task->set('last_started', DateTime::now());

        return $this->save($task) !== false;
    }

    /**
     * Mark a task as finished.
     *
     * @param string $taskName The task name
     * @param array<string, mixed> $meta Additional metadata
     * @return bool
     */
    public function markAsFinished(string $taskName, array $meta = []): bool
    {
        $task = $this->getByName($taskName);
        if (!$task instanceof \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask) {
            return false;
        }

        $logItem = $this->MonitoredScheduledTaskLogItems->newEntity([
            'monitored_scheduled_task_id' => $task->get('id'),
            'type' => MonitoredScheduledTaskLogItem::TYPE_FINISHED,
            'meta' => $meta,
        ]);
        $this->MonitoredScheduledTaskLogItems->save($logItem);

        $task->set('last_finished', DateTime::now());

        return $this->save($task) !== false;
    }

    /**
     * Mark a task as failed.
     *
     * @param string $taskName The task name
     * @param array<string, mixed> $meta Additional metadata
     * @return bool
     */
    public function markAsFailed(string $taskName, array $meta = []): bool
    {
        $task = $this->getByName($taskName);
        if (!$task instanceof \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask) {
            return false;
        }

        $logItem = $this->MonitoredScheduledTaskLogItems->newEntity([
            'monitored_scheduled_task_id' => $task->get('id'),
            'type' => MonitoredScheduledTaskLogItem::TYPE_FAILED,
            'meta' => $meta,
        ]);
        $this->MonitoredScheduledTaskLogItems->save($logItem);

        $task->set('last_failed', DateTime::now());

        return $this->save($task) !== false;
    }

    /**
     * Mark a task as skipped.
     *
     * @param string $taskName The task name
     * @param array<string, mixed> $meta Additional metadata
     * @return bool
     */
    public function markAsSkipped(string $taskName, array $meta = []): bool
    {
        $task = $this->getByName($taskName);
        if (!$task instanceof \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask) {
            return false;
        }

        $logItem = $this->MonitoredScheduledTaskLogItems->newEntity([
            'monitored_scheduled_task_id' => $task->get('id'),
            'type' => MonitoredScheduledTaskLogItem::TYPE_SKIPPED,
            'meta' => $meta,
        ]);
        $this->MonitoredScheduledTaskLogItems->save($logItem);

        $task->set('last_skipped', DateTime::now());

        return $this->save($task) !== false;
    }
}
