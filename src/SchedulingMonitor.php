<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

/**
 * Scheduling Monitor
 *
 * Static utility class for scheduling monitoring functionality.
 */
class SchedulingMonitor
{
    /**
     * Event type constants
     */
    public const EVENT_TYPE_CALLBACK = 'callback';

    public const EVENT_TYPE_CAKE_COMMAND = 'cake_command';

    public const EVENT_TYPE_EXEC = 'exec';

    public const EVENT_TYPE_UNKNOWN = 'unknown-event';

    /**
     * Maximum length for monitored task names (matches DB column / validation).
     */
    public const MAX_NAME_LENGTH = 255;

    /**
     * Get the CakePHP command prefix for the current OS.
     *
     * @return string The command prefix
     */
    public static function getCakeCommandPrefix(): string
    {
        return CommandBuilder::getCakeCommandPrefix();
    }

    /**
     * Generate a name from command string.
     *
     * @param string $command The command string
     * @return string
     */
    public static function generateNameFromCommand(string $command): string
    {
        $cakeCommand = self::getCakeCommandPrefix();
        if (str_starts_with($command, $cakeCommand)) {
            $commandName = substr($command, strlen($cakeCommand));

            return 'cake-' . str_replace(' ', '-', $commandName);
        }

        $parts = explode(' ', $command);
        $baseName = basename($parts[0]);

        return 'exec-' . $baseName;
    }

    /**
     * Check if a command is a CakePHP command.
     *
     * @param string $command The command string
     * @return bool True if it's a CakePHP command
     */
    public static function isCakeCommand(string $command): bool
    {
        $cakeCommand = CommandBuilder::getCakeCommandPrefix();

        return str_starts_with($command, $cakeCommand);
    }

    /**
     * Get the event type based on event and command.
     *
     * @param mixed $event The schedule event
     * @param string|null $command The command string
     * @return string
     */
    public static function getEventType($event, ?string $command): string
    {
        if ($event instanceof \Crustum\Scheduling\CallbackEvent) {
            return self::EVENT_TYPE_CALLBACK;
        }

        if (!empty($command) && self::isCakeCommand($command)) {
            return self::EVENT_TYPE_CAKE_COMMAND;
        }

        return self::EVENT_TYPE_EXEC;
    }

    /**
     * Generate a name for the event.
     *
     * Names are truncated to fit the monitored_scheduled_tasks.name column.
     *
     * @param \Crustum\Scheduling\Event $event The schedule event
     * @param string|null $command The command string
     * @return string
     */
    public static function generateEventName($event, ?string $command): string
    {
        if (!empty($event->getMonitorName())) {
            return self::limitMonitorName($event->getMonitorName());
        }

        if (!empty($event->getDescription())) {
            return self::limitMonitorName($event->getDescription());
        }

        if (empty($command)) {
            if ($event instanceof \Crustum\Scheduling\CallbackEvent) {
                return self::limitMonitorName(self::EVENT_TYPE_CALLBACK . '-' . uniqid());
            }

            return self::EVENT_TYPE_UNKNOWN;
        }

        return self::limitMonitorName(self::generateNameFromCommand($command));
    }

    /**
     * Truncate a monitor name to the database column width without ellipsis.
     *
     * @param string $name The monitor name
     * @return string
     */
    public static function limitMonitorName(string $name): string
    {
        if (mb_strlen($name) <= self::MAX_NAME_LENGTH) {
            return $name;
        }

        return mb_substr($name, 0, self::MAX_NAME_LENGTH);
    }
}
