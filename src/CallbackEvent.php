<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

use InvalidArgumentException;
use LogicException;
use RuntimeException;
use Throwable;

/**
 * Callback Event
 *
 * Represents a scheduled event that executes a callback function.
 */
class CallbackEvent extends Event
{
    /**
     * The callback to call.
     *
     * @var string|callable|mixed
     */
    protected $callback;

    /**
     * The parameters to pass to the method.
     *
     * @var array<mixed>
     */
    protected array $parameters;

    /**
     * The result of the callback's execution.
     *
     * @var mixed
     */
    protected $result;

    /**
     * The exception that was thrown when calling the callback, if any.
     *
     * @var \Throwable|null
     */
    protected ?Throwable $exception = null;

    /**
     * Create a new event instance.
     *
     * @param \Crustum\Scheduling\EventMutexInterface $mutex The mutex implementation
     * @param mixed $callback The callback
     * @param array<mixed> $parameters The parameters
     * @param \DateTimeZone|string|null $timezone The timezone
     * @throws \InvalidArgumentException
     */
    public function __construct(EventMutexInterface $mutex, $callback, array $parameters = [], $timezone = null)
    {
        if (!is_string($callback) && !is_callable($callback)) {
            throw new InvalidArgumentException(
                'Invalid scheduled callback event. Must be a string or callable.'
            );
        }

        $this->mutex = $mutex;
        $this->callback = $callback;
        $this->parameters = $parameters;
        $this->timezone = $timezone;
    }

    /**
     * Run the callback event.
     *
     * @return void
     * @throws \Throwable
     */
    public function run(): void
    {
        parent::run();

        if ($this->exception instanceof \Throwable) {
            throw $this->exception;
        }
    }

    /**
     * Determine if the event should skip because another process is overlapping.
     *
     * @return bool True if should skip
     */
    public function shouldSkipDueToOverlapping(): bool
    {
        return $this->description && parent::shouldSkipDueToOverlapping();
    }

    /**
     * Indicate that the callback should run in the background.
     *
     * @return $this
     * @throws \RuntimeException
     */
    public function runInBackground()
    {
        throw new RuntimeException('Scheduled closures can not be run in the background.');
    }

    /**
     * Run the callback.
     *
     * @return int The exit code
     */
    protected function execute(): int
    {
        try {
            if (is_object($this->callback)) {
                if (!is_callable($this->callback)) {
                    throw new \LogicException('Object callback is not callable.');
                }

                $this->result = call_user_func_array($this->callback, $this->parameters);
            } else {
                if (!is_callable($this->callback)) {
                    throw new \LogicException('Callback is not callable.');
                }

                $this->result = call_user_func_array($this->callback, $this->parameters);
            }

            return $this->result === false ? 1 : 0;
        } catch (Throwable $throwable) {
            $this->exception = $throwable;

            return 1;
        }
    }

    /**
     * Do not allow the event to overlap each other.
     * The expiration time of the underlying cache lock may be specified in minutes.
     *
     * @param int $expiresAt The expiration time in minutes
     * @param bool $releaseOnTerminationSignals Whether to release the mutex on termination signals
     * @return $this
     * @throws \LogicException
     */
    public function withoutOverlapping(int $expiresAt = 1440, bool $releaseOnTerminationSignals = true)
    {
        if (!isset($this->description)) {
            throw new LogicException(
                "A scheduled event name is required to prevent overlapping. Use the 'name' method before 'withoutOverlapping'."
            );
        }

        return parent::withoutOverlapping($expiresAt, $releaseOnTerminationSignals);
    }

    /**
     * Allow the event to only run on one server for each cron expression.
     *
     * @return $this
     * @throws \LogicException
     */
    public function onOneServer()
    {
        if (!isset($this->description)) {
            throw new LogicException(
                "A scheduled event name is required to only run on one server. Use the 'name' method before 'onOneServer'."
            );
        }

        return parent::onOneServer();
    }

    /**
     * Get the summary of the event for display.
     *
     * @return string The summary
     */
    public function getSummaryForDisplay(): string
    {
        if (is_string($this->description)) {
            return $this->description;
        }

        return is_string($this->callback) ? $this->callback : 'Callback';
    }

    /**
     * Get the mutex name for the scheduled command.
     *
     * @return string The mutex name
     */
    public function mutexName(): string
    {
        return 'schedule-' . sha1($this->description ?? '');
    }

    /**
     * Clear the mutex for the event.
     *
     * @return void
     */
    protected function removeMutex(): void
    {
        if ($this->description) {
            parent::removeMutex();
        }
    }
}
