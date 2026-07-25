<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

/**
 * Pending Event Attributes
 *
 * Manages pending attributes for scheduled events.
 */
class PendingEventAttributes
{
    use ManageAttributesTrait;
    use ManageFrequenciesTrait;

    /**
     * Event lifecycle and output methods that should be deferred and replayed on each event in the group.
     *
     * @var array<int, string>
     */
    public const DEFERRED_EVENT_METHODS = [
        'before',
        'after',
        'then',
        'onSuccess',
        'onFailure',
    ];

    /**
     * The output location for the command.
     *
     * @var string|null
     */
    public ?string $output = null;

    /**
     * Whether to append to the output file.
     *
     * @var bool
     */
    public bool $shouldAppendOutput = false;

    /**
     * The recorded deferred method calls to replay on each event.
     *
     * @var array<int, array{0: string, 1: array<mixed>}>
     */
    protected array $macros = [];

    /**
     * Create a new pending event attributes instance.
     *
     * @param \Crustum\Scheduling\Schedule $schedule The schedule instance
     */
    public function __construct(
        protected Schedule $schedule,
    ) {
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

        return $this;
    }

    /**
     * Merge the current attributes into the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @return void
     */
    public function mergeAttributes(Event $event): void
    {
        $event->expression = $this->expression;
        $event->repeatSeconds = $this->repeatSeconds;

        if ($this->description !== null) {
            $event->name($this->description);
        }

        if ($this->timezone !== null) {
            $event->timezone($this->timezone);
        }

        if ($this->user !== null) {
            $event->user = $this->user;
        }

        if ($this->evenInMaintenanceMode) {
            $event->evenInMaintenanceMode();
        }

        if ($this->evenWhenPaused) {
            $event->evenWhenPaused();
        }

        if ($this->withoutOverlapping) {
            $event->withoutOverlapping($this->expiresAt, $this->releaseOnTerminationSignals);
        }

        if ($this->onOneServer) {
            $event->onOneServer();
        }

        if ($this->runInBackground) {
            $event->runInBackground();
        }

        if ($this->output !== null) {
            $event->sendOutputTo($this->output, $this->shouldAppendOutput);
        }

        foreach ($this->filters as $filter) {
            $event->when($filter);
        }

        foreach ($this->rejects as $reject) {
            $event->skip($reject);
        }

        foreach ($this->macros as [$method, $parameters]) {
            $event->{$method}(...$parameters);
        }
    }

    /**
     * Proxy missing methods onto the underlying schedule.
     *
     * @param string $method The method name
     * @param array<mixed> $parameters The parameters
     * @return mixed
     */
    public function __call(string $method, array $parameters): mixed
    {
        if (in_array($method, static::DEFERRED_EVENT_METHODS, true)) {
            $this->macros[] = [$method, $parameters];

            return $this;
        }

        return $this->schedule->{$method}(...$parameters);
    }
}
