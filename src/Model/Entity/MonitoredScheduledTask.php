<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Model\Entity;

use Cake\ORM\Entity;

/**
 * MonitoredScheduledTask Entity
 *
 * @property int $id
 * @property string $name
 * @property string|null $type
 * @property string $cron_expression
 * @property string|null $timezone
 * @property int $grace_time_in_minutes
 * @property \Cake\I18n\DateTime|null $last_started
 * @property \Cake\I18n\DateTime|null $last_finished
 * @property \Cake\I18n\DateTime|null $last_failed
 * @property \Cake\I18n\DateTime|null $last_skipped
 * @property \Cake\I18n\DateTime|null $created
 * @property \Cake\I18n\DateTime|null $modified
 *
 * @property \Crustum\Scheduling\Model\Entity\MonitoredScheduledTaskLogItem[] $log_items
 */
class MonitoredScheduledTask extends Entity
{
    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'name' => true,
        'type' => true,
        'cron_expression' => true,
        'timezone' => true,
        'grace_time_in_minutes' => true,
        'last_started' => true,
        'last_finished' => true,
        'last_failed' => true,
        'last_skipped' => true,
    ];

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var array<string>
     */
    protected array $_hidden = [];

    /**
     * Check if the task is currently running.
     *
     * @return bool
     */
    public function isRunning(): bool
    {
        $lastStarted = $this->get('last_started');
        $lastFinished = $this->get('last_finished');
        $lastFailed = $this->get('last_failed');

        if (!$lastStarted) {
            return false;
        }

        if (!$lastFinished && !$lastFailed) {
            return true;
        }

        return $lastStarted > $lastFinished && $lastStarted > $lastFailed;
    }

    /**
     * Check if the task has failed.
     *
     * @return bool
     */
    public function hasFailed(): bool
    {
        $lastFailed = $this->get('last_failed');
        $lastFinished = $this->get('last_finished');

        if (!$lastFailed) {
            return false;
        }

        return !$lastFinished || $lastFailed > $lastFinished;
    }

    /**
     * Get the status of the task.
     *
     * @return string
     */
    public function getStatus(): string
    {
        if ($this->isRunning()) {
            return 'running';
        }

        if ($this->hasFailed()) {
            return 'failed';
        }

        $lastFinished = $this->get('last_finished');
        if ($lastFinished) {
            return 'completed';
        }

        return 'unknown';
    }
}
