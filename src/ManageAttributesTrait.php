<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

/**
 * Manage Attributes Trait
 *
 * Provides common attributes and methods for scheduled events.
 */
trait ManageAttributesTrait
{
    /**
     * The cron expression representing the event's frequency.
     *
     * @var string
     */
    public string $expression = '* * * * *';

    /**
     * How often to repeat the event during a minute.
     *
     * @var int|null
     */
    public ?int $repeatSeconds = null;

    /**
     * The timezone the date should be evaluated on.
     *
     * @var \DateTimeZone|string|null
     */
    public $timezone;

    /**
     * The user the command should run as.
     *
     * @var string|null
     */
    public ?string $user = null;

    /**
     * Indicates if the command should run in maintenance mode.
     *
     * @var bool
     */
    public bool $evenInMaintenanceMode = false;

    /**
     * Indicates if the command should run when the scheduler is paused.
     *
     * @var bool
     */
    public bool $evenWhenPaused = false;

    /**
     * Indicates if the command should not overlap itself.
     *
     * @var bool
     */
    public bool $withoutOverlapping = false;

    /**
     * Indicates if the mutex should be released on termination signals.
     *
     * @var bool
     */
    public bool $releaseOnTerminationSignals = true;

    /**
     * Indicates if the command should only be allowed to run on one server for each cron expression.
     *
     * @var bool
     */
    public bool $onOneServer = false;

    /**
     * The number of minutes the mutex should be valid.
     *
     * @var int
     */
    public int $expiresAt = 1440;

    /**
     * Indicates if the command should run in the background.
     *
     * @var bool
     */
    public bool $runInBackground = false;

    /**
     * The array of filter callbacks.
     *
     * @var array<callable>
     */
    protected array $filters = [];

    /**
     * The array of reject callbacks.
     *
     * @var array<callable>
     */
    protected array $rejects = [];

    /**
     * The human readable description of the event.
     *
     * @var string|null
     */
    protected ?string $description = null;

    /**
     * The custom monitor name for this event.
     *
     * @var string|null
     */
    protected ?string $monitorName = null;

    /**
     * The grace time in minutes before considering the task overdue.
     *
     * @var int|null
     */
    protected ?int $graceTimeInMinutes = null;

    /**
     * Whether this event should be monitored.
     *
     * @var bool
     */
    protected bool $useMonitoring = false;

    /**
     * Whether to store command output in the database.
     *
     * @var bool
     */
    protected bool $storeOutputInDb = false;

    /**
     * Set which user the command should run as.
     *
     * @param string $user The user to run as
     * @return $this
     */
    public function user(string $user)
    {
        $this->user = $user;

        return $this;
    }

    /**
     * State that the command should run even in maintenance mode.
     *
     * @return $this
     */
    public function evenInMaintenanceMode()
    {
        $this->evenInMaintenanceMode = true;

        return $this;
    }

    /**
     * State that the command should run even when the scheduler is paused.
     *
     * @return $this
     */
    public function evenWhenPaused()
    {
        $this->evenWhenPaused = true;

        return $this;
    }

    /**
     * Do not allow the event to overlap each other.
     * The expiration time of the underlying cache lock may be specified in minutes.
     *
     * @param int $expiresAt The expiration time in minutes
     * @param bool $releaseOnTerminationSignals Whether to release the mutex on termination signals
     * @return $this
     */
    public function withoutOverlapping(int $expiresAt = 1440, bool $releaseOnTerminationSignals = true)
    {
        $this->withoutOverlapping = true;
        $this->expiresAt = $expiresAt;
        $this->releaseOnTerminationSignals = $releaseOnTerminationSignals;

        return $this->skip(function () {
            $customTtlSeconds = null;
            if ($this->isRepeatable()) {
                $customTtlSeconds = $this->repeatSeconds * 2;
            }

            return $this->mutex->exists($this, $customTtlSeconds);
        });
    }

    /**
     * Allow the event to only run on one server for each cron expression.
     *
     * @return $this
     */
    public function onOneServer()
    {
        $this->onOneServer = true;

        return $this;
    }

    /**
     * State that the command should run in the background.
     *
     * @return $this
     */
    public function runInBackground()
    {
        $this->runInBackground = true;

        return $this;
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param callable|bool $callback The callback or boolean value
     * @return $this
     */
    public function when($callback)
    {
        $this->filters[] = is_callable($callback) ? $callback : (fn() => $callback);

        return $this;
    }

    /**
     * Register a callback to further filter the schedule.
     *
     * @param callable|bool $callback The callback or boolean value
     * @return $this
     */
    public function skip($callback)
    {
        $this->rejects[] = is_callable($callback) ? $callback : (fn() => $callback);

        return $this;
    }

    /**
     * Set the human-friendly description of the event.
     *
     * @param string $description The description
     * @return $this
     */
    public function name(string $description)
    {
        return $this->description($description);
    }

    /**
     * Set the human-friendly description of the event.
     *
     * @param string $description The description
     * @return $this
     */
    public function description(string $description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Send the output of the command to a given location.
     *
     * @param string $location The output location
     * @param bool $append Whether to append to the file
     * @return $this
     */
    public function sendOutputTo(string $location, bool $append = false)
    {
        $this->output = $location;
        $this->shouldAppendOutput = $append;

        return $this;
    }

    /**
     * Append the output of the command to a given location.
     *
     * @param string $location The output location
     * @return $this
     */
    public function appendOutputTo(string $location)
    {
        return $this->sendOutputTo($location, true);
    }

    /**
     * Set a custom monitor name for this event.
     *
     * @param string $monitorName The monitor name
     * @return $this
     */
    public function monitorName(string $monitorName)
    {
        $this->monitorName = $monitorName;

        return $this;
    }

    /**
     * Set the grace time in minutes before considering the task overdue.
     *
     * @param int $minutes The grace time in minutes
     * @return $this
     */
    public function graceTimeInMinutes(int $minutes)
    {
        $this->graceTimeInMinutes = $minutes;

        return $this;
    }

    /**
     * Enable monitoring for this event.
     *
     * @return $this
     */
    public function useMonitoring()
    {
        $this->useMonitoring = true;

        return $this;
    }

    /**
     * Disable monitoring for this event.
     *
     * @return $this
     */
    public function disableMonitoring()
    {
        $this->useMonitoring = false;

        return $this;
    }

    /**
     * Enable storing command output in the database.
     *
     * @param bool $store Whether to store output (default: true)
     * @return $this
     */
    public function storeOutputInDb(bool $store = true)
    {
        $this->storeOutputInDb = $store;

        return $this;
    }

    /**
     * Get the description of the event.
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * Get the custom monitor name for this event.
     *
     * @return string|null
     */
    public function getMonitorName(): ?string
    {
        return $this->monitorName;
    }

    /**
     * Get the grace time in minutes before considering the task overdue.
     *
     * @return int|null
     */
    public function getGraceTimeInMinutes(): ?int
    {
        return $this->graceTimeInMinutes;
    }

    /**
     * Check if this event should be monitored.
     *
     * @return bool
     */
    public function shouldMonitor(): bool
    {
        return $this->useMonitoring;
    }

    /**
     * Check if command output should be stored in the database.
     *
     * @return bool
     */
    public function shouldStoreOutputInDb(): bool
    {
        return $this->storeOutputInDb;
    }
}
