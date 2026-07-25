<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Model\Table;

use Cake\Core\Configure;
use Cake\I18n\DateTime;
use Cake\ORM\Query\SelectQuery;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem;

/**
 * MonitoredScheduledTaskLogItems Model
 *
 * @extends \Cake\ORM\Table<array{}, \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem>
 * @property \Crustum\Scheduling\Model\Table\MonitoredScheduledTasksTable $MonitoredScheduledTasks
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem newEmptyEntity()
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem newEntity(array<string, mixed> $data, array<string, mixed> $options = [])
 * @method array<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem> newEntities(array<array<string, mixed>> $data, array<string, mixed> $options = [])
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem get(mixed $primaryKey, array<string, mixed>|string $finder = 'all', mixed ...$args)
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem patchEntity(\Cake\Datasource\EntityInterface $entity, array<string, mixed> $data, array<string, mixed> $options = [])
 * @method array<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem> patchEntities(iterable<\Cake\Datasource\EntityInterface> $entities, array<string, mixed> $data, array<string, mixed> $options = [])
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method iterable<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem>|false saveMany(iterable<\Cake\Datasource\EntityInterface> $entities, $options = [])
 * @method iterable<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem> saveManyOrFail(iterable<\Cake\Datasource\EntityInterface> $entities, $options = [])
 * @method iterable<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem>|false deleteMany(iterable<\Cake\Datasource\EntityInterface> $entities, $options = [])
 * @method iterable<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem> deleteManyOrFail(iterable<\Cake\Datasource\EntityInterface> $entities, $options = [])
 */
class MonitoredScheduledTaskLogItemsTable extends Table
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

        $this->setTable('monitored_scheduled_task_log_items');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->getSchema()->setColumnType('meta', 'json');

        $this->addBehavior('Timestamp');

        $this->belongsTo('MonitoredScheduledTasks', [
            'foreignKey' => 'monitored_scheduled_task_id',
            'joinType' => 'INNER',
            'className' => 'Crustum/Scheduling.MonitoredScheduledTasks',
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
            ->integer('monitored_scheduled_task_id')
            ->requirePresence('monitored_scheduled_task_id', 'create')
            ->notEmptyString('monitored_scheduled_task_id');

        $validator
            ->scalar('type')
            ->maxLength('type', 20)
            ->requirePresence('type', 'create')
            ->notEmptyString('type')
            ->inList('type', [
                MonitoredScheduledTaskLogItem::TYPE_STARTING,
                MonitoredScheduledTaskLogItem::TYPE_FINISHED,
                MonitoredScheduledTaskLogItem::TYPE_FAILED,
                MonitoredScheduledTaskLogItem::TYPE_SKIPPED,
            ]);

        $validator
            ->allowEmptyArray('meta');

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
        $rules->add($rules->existsIn('monitored_scheduled_task_id', 'MonitoredScheduledTasks'), [
            'errorField' => 'monitored_scheduled_task_id',
        ]);

        return $rules;
    }

    /**
     * Find log items by type.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem> $query The query to modify
     * @param array<string, mixed> $options The options containing the type
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem>
     */
    public function findByType(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where(['type' => $options['type']]);
    }

    /**
     * Find log items for a specific task.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem> $query The query to modify
     * @param array<string, mixed> $options The options containing the task ID
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem>
     */
    public function findByTask(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where(['monitored_scheduled_task_id' => $options['task_id']]);
    }

    /**
     * Find log items by task name.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem> $query The query to modify
     * @param array<string, mixed> $options The options containing the task name
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem>
     */
    public function findByTaskName(SelectQuery $query, array $options): SelectQuery
    {
        return $query->matching('MonitoredScheduledTasks', fn(SelectQuery $q) => $q->where(['MonitoredScheduledTasks.name' => $options['task_name']]));
    }

    /**
     * Find recent log items.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem> $query The query to modify
     * @param array<string, mixed> $options The options containing the hours
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem>
     */
    public function findRecent(SelectQuery $query, array $options): SelectQuery
    {
        $hours = $options['hours'] ?? 24;
        $cutoffTime = DateTime::now()->subHours($hours);

        return $query->where(['created >' => $cutoffTime]);
    }

    /**
     * Find log items with failures.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem> $query The query to modify
     * @param array<string, mixed> $options The options
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem>
     */
    public function findFailures(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where(['type' => MonitoredScheduledTaskLogItem::TYPE_FAILED]);
    }

    /**
     * Find log items with successful completions.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem> $query The query to modify
     * @param array<string, mixed> $options The options
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem>
     */
    public function findSuccesses(SelectQuery $query, array $options): SelectQuery
    {
        return $query->where(['type' => MonitoredScheduledTaskLogItem::TYPE_FINISHED]);
    }

    /**
     * Find log items by date range.
     *
     * @param \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem> $query The query to modify
     * @param array<string, mixed> $options The options containing start and end dates
     * @return \Cake\ORM\Query\SelectQuery<\Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem>
     */
    public function findByDateRange(SelectQuery $query, array $options): SelectQuery
    {
        $conditions = [];

        if (isset($options['start_date'])) {
            $conditions['created >='] = $options['start_date'];
        }

        if (isset($options['end_date'])) {
            $conditions['created <='] = $options['end_date'];
        }

        return $query->where($conditions);
    }

    /**
     * Create a new log item.
     *
     * @param int $taskId The monitored scheduled task ID
     * @param string $type The log item type
     * @param array<string, mixed> $meta The metadata
     * @return \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem
     */
    public function createLogItem(int $taskId, string $type, array $meta = []): MonitoredScheduledTaskLogItem
    {
        $logItem = $this->newEntity([
            'monitored_scheduled_task_id' => $taskId,
            'type' => $type,
            'meta' => $meta,
            'created' => DateTime::now(),
        ]);

        return $this->saveOrFail($logItem);
    }

    /**
     * Get log items for a specific task.
     *
     * @param int $taskId The monitored scheduled task ID
     * @param int $limit The maximum number of items to return
     * @return \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem[]
     */
    public function getTaskLogItems(int $taskId, int $limit = 50): array
    {
        return $this->findByTask($this->find(), ['task_id' => $taskId])
            ->orderByDesc('created')
            ->limit($limit)
            ->toArray();
    }

    /**
     * Get the latest log item for a task.
     *
     * @param int $taskId The monitored scheduled task ID
     * @return \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem|null
     */
    public function getLatestLogItem(int $taskId): ?MonitoredScheduledTaskLogItem
    {
        return $this->findByTask($this->find(), ['task_id' => $taskId])
            ->orderByDesc('created')
            ->first();
    }

    /**
     * Get failure count for a task.
     *
     * @param int $taskId The monitored scheduled task ID
     * @param \Cake\I18n\DateTime|null $since Optional start date
     * @return int
     */
    public function getFailureCount(int $taskId, ?DateTime $since = null): int
    {
        $query = $this->findByTask($this->find(), ['task_id' => $taskId])
            ->where(['type' => MonitoredScheduledTaskLogItem::TYPE_FAILED]);

        if ($since instanceof \Cake\I18n\DateTime) {
            $query->where(['created >=' => $since]);
        }

        return $query->count();
    }

    /**
     * Get success count for a task.
     *
     * @param int $taskId The monitored scheduled task ID
     * @param \Cake\I18n\DateTime|null $since Optional start date
     * @return int
     */
    public function getSuccessCount(int $taskId, ?DateTime $since = null): int
    {
        $query = $this->findByTask($this->find(), ['task_id' => $taskId])
            ->where(['type' => MonitoredScheduledTaskLogItem::TYPE_FINISHED]);

        if ($since instanceof \Cake\I18n\DateTime) {
            $query->where(['created >=' => $since]);
        }

        return $query->count();
    }

    /**
     * Get average runtime for a task.
     *
     * @param int $taskId The monitored scheduled task ID
     * @param \Cake\I18n\DateTime|null $since Optional start date
     * @return float|null
     */
    public function getAverageRuntime(int $taskId, ?DateTime $since = null): ?float
    {
        $query = $this->findByTask($this->find(), ['task_id' => $taskId])
            ->where(['type' => MonitoredScheduledTaskLogItem::TYPE_FINISHED]);

        if ($since instanceof \Cake\I18n\DateTime) {
            $query->where(['created >=' => $since]);
        }

        $items = $query->toArray();
        $runtimes = array_filter(array_map(fn($item) => $item->getRuntime(), $items));

        if ($runtimes === []) {
            return null;
        }

        return array_sum($runtimes) / count($runtimes);
    }

    /**
     * Clean up old log items.
     *
     * @param int|null $days The number of days to keep (uses config if null)
     * @return int The number of deleted items
     */
    public function cleanupOldItems(?int $days = null): int
    {
        if ($days === null) {
            $days = Configure::read('Scheduling.delete_log_items_older_than_days', 30);
        }

        $cutoffDate = DateTime::now()->subDays($days);

        return $this->deleteAll([
            'created <' => $cutoffDate,
        ]);
    }
}
