<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Model\Entity;

use Cake\ORM\Entity;

/**
 * MonitoredScheduledTaskLogItem Entity
 *
 * @property int $id
 * @property int $monitored_scheduled_task_id
 * @property string $type
 * @property array<string, mixed>|null $meta
 * @property \Cake\I18n\DateTime|null $created
 *
 * @property \Crustum\Scheduling\Model\Entity\MonitoredScheduledTask $monitored_scheduled_task
 */
class MonitoredScheduledTaskLogItem extends Entity
{
    /**
     * Log item type constants.
     */
    public const TYPE_STARTING = 'starting';

    public const TYPE_FINISHED = 'finished';

    public const TYPE_FAILED = 'failed';

    public const TYPE_SKIPPED = 'skipped';

    /**
     * Fields that can be mass assigned using newEntity() or patchEntity().
     *
     * @var array<string, bool>
     */
    protected array $_accessible = [
        'monitored_scheduled_task_id' => true,
        'type' => true,
        'meta' => true,
    ];

    /**
     * Fields that are excluded from JSON versions of the entity.
     *
     * @var array<string>
     */
    protected array $_hidden = [];

    /**
     * Check if this is a starting log item.
     *
     * @return bool
     */
    public function isStarting(): bool
    {
        return $this->get('type') === self::TYPE_STARTING;
    }

    /**
     * Check if this is a finished log item.
     *
     * @return bool
     */
    public function isFinished(): bool
    {
        return $this->get('type') === self::TYPE_FINISHED;
    }

    /**
     * Check if this is a failed log item.
     *
     * @return bool
     */
    public function isFailed(): bool
    {
        return $this->get('type') === self::TYPE_FAILED;
    }

    /**
     * Check if this is a skipped log item.
     *
     * @return bool
     */
    public function isSkipped(): bool
    {
        return $this->get('type') === self::TYPE_SKIPPED;
    }

    /**
     * Get the runtime from meta data.
     *
     * @return float|null
     */
    public function getRuntime(): ?float
    {
        $meta = $this->get('meta');

        return $meta['runtime'] ?? null;
    }

    /**
     * Get the memory usage from meta data.
     *
     * @return int|null
     */
    public function getMemoryUsage(): ?int
    {
        $meta = $this->get('meta');

        return $meta['memory'] ?? null;
    }

    /**
     * Get the exit code from meta data.
     *
     * @return int|null
     */
    public function getExitCode(): ?int
    {
        $meta = $this->get('meta');

        return $meta['exit_code'] ?? null;
    }

    /**
     * Get the output from meta data.
     *
     * @return string|null
     */
    public function getOutput(): ?string
    {
        $meta = $this->get('meta');

        return $meta['output'] ?? null;
    }

    /**
     * Get the failure message from meta data.
     *
     * @return string|null
     */
    public function getFailureMessage(): ?string
    {
        $meta = $this->get('meta');

        return $meta['failure_message'] ?? null;
    }
}
