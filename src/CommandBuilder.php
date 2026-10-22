<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

/**
 * Command Builder
 *
 * Builds executable command strings for scheduled events.
 */
class CommandBuilder
{
    /**
     * Build the command for the given event.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @return string The built command
     */
    public function buildCommand(Event $event): string
    {
        if ($event->runInBackground) {
            return $this->buildBackgroundCommand($event);
        }

        return $this->buildForegroundCommand($event);
    }

    /**
     * Build the command for running the event in the foreground.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @return string The built command
     */
    protected function buildForegroundCommand(Event $event): string
    {
        $command = $this->normalizeCommandForOS($event->getCommand());

        if ($event->output !== $event->getDefaultOutput()) {
            $output = $this->escapeArgument($event->output);
            $command .= ($event->shouldAppendOutput ? ' >> ' : ' > ') . $output . ' 2>&1';
        }

        return $this->ensureCorrectUser($event, $command);
    }

    /**
     * Build the command for running the event in the background.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @return string The built command
     */
    protected function buildBackgroundCommand(Event $event): string
    {
        $command = $this->normalizeCommandForOS($event->getCommand());
        $output = $this->escapeArgument($event->output);
        $redirect = $event->shouldAppendOutput ? ' >> ' : ' > ';

        $finished = $this->normalizeCommandForOS(self::getCakeCommandPrefix() . 'schedule finish "' . $event->mutexName() . '"');

        if (self::isWindows()) {
            return 'start /b cmd /v:on /c "(' . $command . ' & ' . $finished . ' ^!ERRORLEVEL^!)' . $redirect . $output . ' 2>&1"';
        }

        return $this->ensureCorrectUser(
            $event,
            '(' . $command . $redirect . $output . ' 2>&1 ; ' . $finished . ' "$?") > '
            . $this->escapeArgument($event->getDefaultOutput()) . ' 2>&1 &'
        );
    }

    /**
     * Finalize the event's command syntax with the correct user.
     *
     * @param \Crustum\Scheduling\Event $event The event
     * @param string $command The command
     * @return string The final command
     */
    protected function ensureCorrectUser(Event $event, string $command): string
    {
        if (!$event->user || self::isWindows()) {
            return $command;
        }

        return 'sudo -u ' . $event->user . ' -- sh -c ' . $this->escapeArgument($command);
    }

    /**
     * Escape an argument for shell execution.
     *
     * @param string $argument The argument to escape
     * @return string The escaped argument
     */
    protected function escapeArgument(string $argument): string
    {
        if (self::isWindows()) {
            return '"' . str_replace('"', '""', $argument) . '"';
        }

        return "'" . str_replace("'", "'\"'\"'", $argument) . "'";
    }

    /**
     * Check if the current OS is Windows.
     *
     * @return bool True if Windows
     */
    public static function isWindows(): bool
    {
        return DIRECTORY_SEPARATOR === '\\';
    }

    /**
     * Get the PHP binary escaped for shell usage.
     *
     * Quotes only when the path contains spaces or quotes so the common
     * case (`/usr/local/bin/php`) stays unquoted and stable for mutex
     * names and monitor detection.
     *
     * @return string The PHP binary
     */
    public static function getPhpBinary(): string
    {
        if (!preg_match('/[\s"\']/', PHP_BINARY)) {
            return PHP_BINARY;
        }

        return escapeshellarg(PHP_BINARY);
    }

    /**
     * Get the CakePHP script path for the current OS, without interpreter.
     *
     * @return string The script path
     */
    public static function getCakeScriptPath(): string
    {
        return self::isWindows() ? 'bin\cake.php' : 'bin/cake.php';
    }

    /**
     * Get the CakePHP command prefix for the current OS.
     *
     * Always invokes the interpreter explicitly so scheduled commands do not
     * rely on the executable bit or the shebang of `bin/cake.php`.
     *
     * @return string The command prefix
     */
    public static function getCakeCommandPrefix(): string
    {
        return self::getPhpBinary() . ' ' . self::getCakeScriptPath() . ' ';
    }

    /**
     * Check if the given command invokes the CakePHP scheduler script.
     *
     * Matches current (`PHP_BINARY bin/cake.php`) and legacy
     * (`bin/cake.php`, `php bin/cake.php`) forms on either slash style.
     *
     * @param string $command The command to check
     * @return bool True if it is a CakePHP command
     */
    public static function isCakeCommand(string $command): bool
    {
        return (bool)preg_match('/^(?:\'[^\']+\'|"[^"]+"|\S+)\s+bin[\\\\\/]cake\.php(?=\s|$)|^bin[\\\\\/]cake\.php(?=\s|$)/', $command);
    }

    /**
     * Strip the CakePHP invocation prefix, returning the remainder.
     *
     * @param string $command The command string
     * @return string|null The remainder, or null when not a Cake command
     */
    public static function stripCakeCommandPrefix(string $command): ?string
    {
        if (!self::isCakeCommand($command)) {
            return null;
        }

        return (string)preg_replace('/^(?:\'[^\']+\'|"[^"]+"|\S+)\s+bin[\\\\\/]cake\.php\s?|^bin[\\\\\/]cake\.php\s?/', '', $command, 1);
    }

    /**
     * Normalize a command for stable comparison (mutex names, monitoring).
     *
     * Maps every legacy cake invocation to the canonical prefix so mutex
     * hashes stay stable across the fix.
     *
     * @param string $command The command to normalize
     * @return string The normalized command
     */
    public static function normalizeCommandForComparison(string $command): string
    {
        $remainder = self::stripCakeCommandPrefix($command);
        if ($remainder === null) {
            return str_replace(PHP_BINARY, self::getPhpBinary(), $command);
        }

        return self::getCakeCommandPrefix() . $remainder;
    }

    /**
     * Normalize command for the current operating system.
     *
     * Ensures the CakePHP script is always invoked through the PHP
     * interpreter and normalizes directory separators on Windows.
     *
     * @param string $command The command to normalize
     * @return string The normalized command
     */
    protected function normalizeCommandForOS(string $command): string
    {
        if (self::isWindows()) {
            $command = str_replace('/', '\\', $command);
        }

        $remainder = self::stripCakeCommandPrefix($command);
        if ($remainder !== null) {
            return self::getCakeCommandPrefix() . $remainder;
        }

        return $command;
    }
}
