<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

use Cake\Chronos\Chronos;
use Cake\Event\Event as CakeEvent;
use Cake\Event\EventManager;
use Cron\CronExpression;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Event
 *
 * Represents a scheduled event that can be executed.
 */
class Event
{
    use ManageAttributesTrait;
    use ManageFrequenciesTrait;

    /**
     * The command string.
     *
     * @var string|null
     */
    public ?string $command = null;

    /**
     * Get the command string.
     *
     * @return string The command string
     * @throws \LogicException If command is not set
     */
    public function getCommand(): string
    {
        if ($this->command === null) {
            throw new \LogicException('Command is not set for this event.');
        }

        return $this->command;
    }

    /**
     * The location that output should be sent to.
     *
     * @var string
     */
    public string $output = '/dev/null';

    /**
     * Indicates whether output should be appended.
     *
     * @var bool
     */
    public bool $shouldAppendOutput = false;

    /**
     * The array of callbacks to be run before the event is started.
     *
     * @var array<callable>
     */
    protected array $beforeCallbacks = [];

    /**
     * The array of callbacks to be run after the event is finished.
     *
     * @var array<callable>
     */
    protected array $afterCallbacks = [];

    /**
     * The event mutex implementation.
     *
     * @var \Crustum\Scheduling\EventMutexInterface
     */
    public EventMutexInterface $mutex;

    /**
     * The last time the event was checked for eligibility to run.
     * Utilized by sub-minute repeated events.
     *
     * @var \Cake\Chronos\Chronos|null
     */
    protected ?Chronos $lastChecked = null;

    /**
     * The exit status code of the command.
     *
     * @var int|null
     */
    public ?int $exitCode = null;

    /**
     * Indicates whether the event was skipped because of overlapping.
     *
     * @var bool
     */
    public bool $skippedBecauseOverlapping = false;

    /**
     * Create a new event instance.
     *
     * @param \Crustum\Scheduling\EventMutexInterface $mutex The mutex implementation
     * @param string $command The command to execute
     * @param \DateTimeZone|string|null $timezone The timezone
     */
    public function __construct(EventMutexInterface $mutex, string $command, $timezone = null)
    {
        $this->mutex = $mutex;
        $this->command = $command;
        $this->timezone = $timezone;

        $this->output = $this->getDefaultOutput();
    }

    /**
     * Get the default output depending on the OS.
     *
     * @return string The default output path
     */
    public function getDefaultOutput(): string
    {
        return DIRECTORY_SEPARATOR === '\\' ? 'NUL' : '/dev/null';
    }

    /**
     * Run the given event.
     *
     * @return void
     * @throws \Throwable
     */
    public function run(): void
    {
        $this->skippedBecauseOverlapping = false;

        if ($this->shouldSkipDueToOverlapping()) {
            $this->skippedBecauseOverlapping = true;
            $this->dispatchSchedulerEvent(new \Crustum\Scheduling\Event\ScheduledTaskSkipped($this));

            return;
        }

        $this->ensureMutexIsReleasedOnSignal();

        if ($this->isRepeatable()) {
            $this->lastChecked = Chronos::now();
            if ($this->timezone) {
                $this->lastChecked = $this->lastChecked->setTimezone($this->timezone);
            }
        }

        $exitCode = $this->start();

        if (!$this->runInBackground) {
            $this->finish($exitCode);
        }
    }

    /**
     * Determine if the event should skip because another process is overlapping.
     *
     * @return bool True if should skip
     */
    public function shouldSkipDueToOverlapping(): bool
    {
        if (!$this->withoutOverlapping) {
            return false;
        }

        $customExpiresAt = null;
        if ($this->isRepeatable()) {
            $customExpiresAt = $this->repeatSeconds * 2;
        }

        $created = $this->mutex->create($this, $customExpiresAt);

        if ($this->isRepeatable() && $created) {
            $this->lastChecked = Chronos::now();
            if ($this->timezone) {
                $this->lastChecked = $this->lastChecked->setTimezone($this->timezone);
            }
        }

        return !$created;
    }

    /**
     * Determine if the event has been configured to repeat multiple times per minute.
     *
     * @return bool True if repeatable
     */
    public function isRepeatable(): bool
    {
        return $this->repeatSeconds !== null;
    }

    /**
     * Determine if the event is ready to repeat.
     *
     * @return bool True if should repeat now
     */
    public function shouldRepeatNow(): bool
    {
        if (!$this->isRepeatable()) {
            return false;
        }

        if (!$this->lastChecked instanceof \Cake\Chronos\Chronos) {
            return true;
        }

        return $this->lastChecked->diffInSeconds() >= $this->repeatSeconds;
    }

    /**
     * Run the command process.
     *
     * @return int The exit code
     * @throws \Throwable
     */
    protected function start(): int
    {
        try {
            $this->callBeforeCallbacks();

            return $this->execute();
        } catch (Throwable $throwable) {
            $this->removeMutex();

            throw $throwable;
        }
    }

    /**
     * Run the command process.
     *
     * @return int The exit code
     */
    protected function execute(): int
    {
        $process = Process::fromShellCommandline(
            $this->buildCommand(),
            ROOT,
            null,
            null,
            null
        );

        if (!$this->runInBackground) {
            return $this->runForegroundProcess($process);
        }

        return $process->run();
    }

    /**
     * Run a foreground process with output handling.
     *
     * @param \Symfony\Component\Process\Process $process The process
     * @return int The exit code
     */
    protected function runForegroundProcess($process): int
    {
        if ($this->output !== $this->getDefaultOutput()) {
            return $process->run();
        }

        $process->run(function ($type, $buffer) use ($process): void {
            if ($type === $process::ERR) {
                \Cake\Log\Log::error('Scheduled command error: ' . trim($buffer));
            }
        });

        return $process->getExitCode() ?? 1;
    }

    /**
     * Mark the command process as finished and run callbacks/cleanup.
     *
     * @param int $exitCode The exit code
     * @return void
     */
    public function finish(int $exitCode): void
    {
        $this->exitCode = $exitCode;

        try {
            $this->callAfterCallbacks();
        } finally {
            $this->removeMutex();
        }
    }

    /**
     * Call all of the "before" callbacks for the event.
     *
     * @return void
     */
    public function callBeforeCallbacks(): void
    {
        foreach ($this->beforeCallbacks as $callback) {
            $this->callEventCallback($callback);
        }
    }

    /**
     * Call all of the "after" callbacks for the event.
     *
     * @return void
     */
    public function callAfterCallbacks(): void
    {
        foreach ($this->afterCallbacks as $callback) {
            $this->callEventCallback($callback);
        }
    }

    /**
     * Call the given event callback, injecting this event when type-hinted.
     *
     * @param callable $callback The callback
     * @return mixed
     */
    protected function callEventCallback(callable $callback): mixed
    {
        if ($callback instanceof \Closure) {
            $parameters = $this->eventParametersForCallback($callback);
            if ($parameters !== []) {
                return $callback(...$parameters);
            }
        }

        return call_user_func($callback);
    }

    /**
     * Get event injection parameters for the given callback.
     *
     * @param \Closure $callback The callback
     * @return array<string, mixed>
     */
    protected function eventParametersForCallback(\Closure $callback): array
    {
        $reflection = new \ReflectionFunction($callback);

        foreach ($reflection->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $typeName = $type->getName();
                if (is_a($this, $typeName)) {
                    return [$parameter->getName() => $this];
                }
            }
        }

        return [];
    }

    /**
     * Build the command string.
     *
     * @return string The built command
     */
    public function buildCommand(): string
    {
        return (new CommandBuilder())->buildCommand($this);
    }

    /**
     * Determine if the given event should run based on the Cron expression.
     *
     * @return bool True if due
     */
    public function isDue(): bool
    {
        if (!$this->expressionPasses()) {
            return false;
        }

        if ($this->repeatSeconds !== null) {
            return $this->shouldRepeat();
        }

        return true;
    }

    /**
     * Determine if the event runs in maintenance mode.
     *
     * @return bool True if runs in maintenance mode
     */
    public function runsInMaintenanceMode(): bool
    {
        return $this->evenInMaintenanceMode;
    }

    /**
     * Determine if the event runs when the scheduler is paused.
     *
     * @return bool
     */
    public function runsWhenPaused(): bool
    {
        return $this->evenWhenPaused;
    }

    /**
     * Determine if the Cron expression passes.
     *
     * @return bool True if expression passes
     */
    protected function expressionPasses(): bool
    {
        $date = Chronos::now();

        if ($this->timezone) {
            $date = $date->setTimezone($this->timezone);
        }

        return (new CronExpression($this->expression))->isDue($date->toDateTimeString());
    }

    /**
     * Determine if the event should repeat based on the repeat seconds interval.
     *
     * @return bool True if should repeat
     */
    protected function shouldRepeat(): bool
    {
        if (!$this->lastChecked instanceof \Cake\Chronos\Chronos) {
            return true;
        }

        $now = Chronos::now();
        if ($this->timezone) {
            $now = $now->setTimezone($this->timezone);
        }

        $secondsSinceLastCheck = $now->diffInSeconds($this->lastChecked, false);

        return $secondsSinceLastCheck >= $this->repeatSeconds;
    }

    /**
     * Determine if the filters pass for the event.
     *
     * @return bool True if filters pass
     */
    public function filtersPass(): bool
    {
        foreach ($this->filters as $callback) {
            if (!$this->callEventCallback($callback)) {
                return false;
            }
        }

        foreach ($this->rejects as $callback) {
            if ($this->callEventCallback($callback)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Register a callback to be called before the operation.
     *
     * @param callable $callback The callback
     * @return $this
     */
    public function before(callable $callback)
    {
        $this->beforeCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param callable $callback The callback
     * @return $this
     */
    public function after(callable $callback)
    {
        return $this->then($callback);
    }

    /**
     * Register a callback to be called after the operation.
     *
     * @param callable $callback The callback
     * @return $this
     */
    public function then(callable $callback)
    {
        $this->afterCallbacks[] = $callback;

        return $this;
    }

    /**
     * Register a callback to be called if the operation succeeds.
     *
     * @param callable $callback The callback
     * @return $this
     */
    public function onSuccess(callable $callback)
    {
        return $this->then(function () use ($callback): void {
            if ($this->exitCode === 0) {
                $this->callEventCallback($callback);
            }
        });
    }

    /**
     * Register a callback to be called if the operation fails.
     *
     * @param callable $callback The callback
     * @return $this
     */
    public function onFailure(callable $callback)
    {
        return $this->then(function () use ($callback): void {
            if ($this->exitCode !== 0) {
                $this->callEventCallback($callback);
            }
        });
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

        return $this->buildCommand();
    }

    /**
     * Determine the next due date for an event.
     *
     * @param \DateTimeInterface|string $currentTime The current time
     * @param int $nth The nth occurrence
     * @param bool $allowCurrentDate Whether to allow current date
     * @return \Cake\Chronos\Chronos The next run date
     */
    public function nextRunDate($currentTime = 'now', int $nth = 0, bool $allowCurrentDate = false): Chronos
    {
        $timezone = $this->timezone instanceof \DateTimeZone ? $this->timezone->getName() : $this->timezone;

        return Chronos::instance((new CronExpression($this->getExpression()))
            ->getNextRunDate($currentTime, $nth, $allowCurrentDate, $timezone));
    }

    /**
     * Get the Cron expression for the event.
     *
     * @return string The cron expression
     */
    public function getExpression(): string
    {
        return $this->expression;
    }

    /**
     * Get the mutex name for the scheduled command.
     *
     * @return string The mutex name
     */
    public function mutexName(): string
    {
        return 'schedule-' .
            sha1($this->expression . static::normalizeCommand($this->command ?? ''));
    }

    /**
     * Delete the mutex for the event.
     *
     * @return void
     */
    protected function removeMutex(): void
    {
        if ($this->withoutOverlapping) {
            $this->mutex->forget($this);
        }
    }

    /**
     * Ensure the mutex is released if the process receives a termination signal.
     *
     * @return void
     */
    protected function ensureMutexIsReleasedOnSignal(): void
    {
        if (
            !$this->releaseOnTerminationSignals
            || $this->runInBackground
            || !\SignalHandler\Signal\SignalRegistry::isSupported()
        ) {
            return;
        }

        $registry = new \SignalHandler\Signal\SignalRegistry();
        $release = function () use ($registry): void {
            $this->removeMutex();
            $registry->unregister();
            exit(1);
        };

        foreach (
            [
            \SignalHandler\Signal\Signal::SIGTERM,
            \SignalHandler\Signal\Signal::SIGINT,
            \SignalHandler\Signal\Signal::SIGQUIT,
            ] as $signal
        ) {
            $registry->register($signal, $release);
        }
    }

    /**
     * Format the given command string with a normalized PHP binary path.
     *
     * @param string $command The command
     * @return string The normalized command
     */
    public static function normalizeCommand(string $command): string
    {
        $cakePath = CommandBuilder::getCakeCommandPrefix();

        return str_replace([
            'php',
            'bin/cake.php',
            'bin\\cake.php',
        ], [
            PHP_BINARY,
            $cakePath,
            $cakePath,
        ], $command);
    }

    /**
     * Dispatch a scheduler event.
     *
     * @param \Cake\Event\Event $event The event to dispatch
     * @return void
     */
    protected function dispatchSchedulerEvent(CakeEvent $event): void
    {
        EventManager::instance()->dispatch($event);
    }
}
