<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

use Cake\Chronos\Chronos;
use DateTimeZone;

/**
 * Cron Expression Timezone Converter
 *
 * Converts an event cron expression to a display timezone.
 * Returns one or more expressions when values straddle a day boundary.
 */
class CronExpressionTimezoneConverter
{
    /**
     * Convert an event cron expression to the display timezone.
     *
     * @param \Crustum\Scheduling\Event $event The scheduled event
     * @param \DateTimeZone $timezone The display timezone
     * @return array<int, string>
     */
    public static function forEvent(Event $event, DateTimeZone $timezone): array
    {
        $eventTimezone = static::resolveEventTimezone($event, $timezone);

        [$totalOffsetMinutes, $hourOffset, $minuteOffset] = static::offsetComponents(
            $eventTimezone,
            $timezone
        );

        if ($totalOffsetMinutes === 0) {
            return [$event->expression];
        }

        $segments = preg_split('/\s+/', $event->expression);
        if ($segments === false || count($segments) < 5) {
            return [$event->expression];
        }

        $minuteGroups = static::shiftAndGroup($segments[0], $minuteOffset, 60);

        $expressions = [];

        foreach ($minuteGroups as $minuteCarry => $minuteValues) {
            $hourGroups = static::shiftAndGroup($segments[1], $hourOffset + $minuteCarry, 24);

            foreach ($hourGroups as $hourCarry => $hourValues) {
                $parts = $segments;
                $parts[0] = $minuteValues;
                $parts[1] = $hourValues;

                foreach (static::expressionsForHourCarry($segments, $parts, (int)$hourCarry) as $expression) {
                    $expressions[] = $expression;
                }
            }
        }

        return $expressions;
    }

    /**
     * Resolve the timezone used by the given event.
     *
     * @param \Crustum\Scheduling\Event $event The scheduled event
     * @param \DateTimeZone $defaultTimezone The default display timezone
     * @return \DateTimeZone
     */
    protected static function resolveEventTimezone(Event $event, DateTimeZone $defaultTimezone): DateTimeZone
    {
        if ($event->timezone instanceof DateTimeZone) {
            return $event->timezone;
        }

        return new DateTimeZone($event->timezone ?? $defaultTimezone->getName());
    }

    /**
     * Get offset components between the event and display timezones.
     *
     * @param \DateTimeZone $eventTimezone The event timezone
     * @param \DateTimeZone $displayTimezone The display timezone
     * @return array{int, int, int}
     */
    protected static function offsetComponents(DateTimeZone $eventTimezone, DateTimeZone $displayTimezone): array
    {
        $now = Chronos::now()->toNative();

        $totalOffsetMinutes = intdiv(
            $displayTimezone->getOffset($now) - $eventTimezone->getOffset($now),
            60
        );

        return [$totalOffsetMinutes, intdiv($totalOffsetMinutes, 60), $totalOffsetMinutes % 60];
    }

    /**
     * Build expressions for the given hour carry direction.
     *
     * @param array<int, string> $segments Original cron segments
     * @param array<int, string> $parts Working cron segments
     * @param int $hourCarry Hour carry direction
     * @return array<int, string>
     */
    protected static function expressionsForHourCarry(array $segments, array $parts, int $hourCarry): array
    {
        if ($hourCarry === 0) {
            return [implode(' ', $parts)];
        }

        $parts[4] = static::shiftField($segments[4], $hourCarry, 7);

        $dayGroups = static::shiftAndGroup($segments[2], $hourCarry, 31, 1);

        $expressions = [];

        foreach ($dayGroups as $dayCarry => $dayValues) {
            $dayParts = $parts;
            $dayParts[2] = $dayValues;

            if ($dayCarry !== 0) {
                $dayParts[3] = static::shiftField($segments[3], (int)$dayCarry, 12, 1);
            }

            $expressions[] = implode(' ', $dayParts);
        }

        return $expressions;
    }

    /**
     * Shift values in a cron field and group them by carry direction.
     *
     * @param string $field Cron field value
     * @param int $offset Offset to apply
     * @param int $mod Modulus for wraparound
     * @param int $min Minimum field value
     * @return array<int, string>
     */
    protected static function shiftAndGroup(string $field, int $offset, int $mod, int $min = 0): array
    {
        if ($offset === 0 || !preg_match('/^[\d,]+$/', $field)) {
            return [0 => $field];
        }

        $groups = [];

        foreach (explode(',', $field) as $value) {
            $new = (int)$value + $offset;
            $carry = 0;

            if ($new >= $mod + $min) {
                $carry = 1;
                $new -= $mod;
            } elseif ($new < $min) {
                $carry = -1;
                $new += $mod;
            }

            $groups[$carry][] = $new;
        }

        $result = [];

        foreach ($groups as $carry => $values) {
            sort($values);
            $result[$carry] = implode(',', $values);
        }

        return $result;
    }

    /**
     * Shift a cron field by the given offset.
     *
     * @param string $field Cron field value
     * @param int $offset Offset to apply
     * @param int $mod Modulus for wraparound
     * @param int $min Minimum field value
     * @return string
     */
    protected static function shiftField(string $field, int $offset, int $mod, int $min = 0): string
    {
        if ($offset === 0 || !preg_match('/^[\d,]+$/', $field)) {
            return $field;
        }

        $shifted = array_map(
            static fn(string $value): int => (((int)$value + $offset - $min) % $mod + $mod) % $mod + $min,
            explode(',', $field)
        );

        sort($shifted);

        return implode(',', $shifted);
    }
}
