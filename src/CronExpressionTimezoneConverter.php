<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

use Cake\Chronos\Chronos;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

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

        $offsetComponents = static::offsetComponents(
            $event,
            $eventTimezone,
            $timezone
        );

        if ($offsetComponents === null) {
            return [$event->expression];
        }

        [$totalOffsetMinutes, $hourOffset, $minuteOffset] = $offsetComponents;

        if ($totalOffsetMinutes === 0) {
            return [$event->expression];
        }

        $segments = preg_split('/\s+/', trim($event->expression));
        if ($segments === false || count($segments) !== 5) {
            return [$event->expression];
        }

        return static::convert($segments, $hourOffset, $minuteOffset) ?? [$event->expression];
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
     * Uses the offset in effect at the next run date so listings during
     * daylight saving transitions convert with the offset that applies
     * when the event actually runs. Returns null when the offset differs
     * between the next two runs (event straddles a DST transition).
     *
     * @param \Crustum\Scheduling\Event $event The scheduled event
     * @param \DateTimeZone $eventTimezone The event timezone
     * @param \DateTimeZone $displayTimezone The display timezone
     * @return array{int, int, int}|null
     */
    protected static function offsetComponents(
        Event $event,
        DateTimeZone $eventTimezone,
        DateTimeZone $displayTimezone
    ): ?array {
        try {
            $at = $event->nextRunDate(Chronos::now())->toNative();
            $nextAt = $event->nextRunDate(Chronos::now(), 1)->toNative();
        } catch (Throwable) {
            $at = Chronos::now()->toNative();
            $nextAt = null;
        }

        $totalOffsetMinutes = intdiv(
            $displayTimezone->getOffset($at) - $eventTimezone->getOffset($at),
            60
        );

        if (
            $nextAt instanceof DateTimeImmutable && $totalOffsetMinutes !== intdiv(
                $displayTimezone->getOffset($nextAt) - $eventTimezone->getOffset($nextAt),
                60
            )
        ) {
            return null;
        }

        return [$totalOffsetMinutes, intdiv($totalOffsetMinutes, 60), $totalOffsetMinutes % 60];
    }

    /**
     * Convert the expression segments by the given offsets.
     *
     * @param array<int, string> $segments Cron segments
     * @param int $hourOffset Hour offset
     * @param int $minuteOffset Minute offset
     * @return array<int, string>|null
     */
    protected static function convert(array $segments, int $hourOffset, int $minuteOffset): ?array
    {
        $daysAreWildcard = $segments[2] === '*' && $segments[3] === '*' && $segments[4] === '*';

        $minuteGroups = static::shiftedGroups(
            $segments[0],
            $minuteOffset,
            0,
            59,
            mergeCarries: $daysAreWildcard && static::expand($segments[1], 0, 23) === range(0, 23)
        );

        if ($minuteGroups === null) {
            return null;
        }

        $expressions = [];

        foreach ($minuteGroups as $minuteCarry => $minuteValues) {
            $hourGroups = static::shiftedGroups(
                $segments[1],
                $hourOffset + (int)$minuteCarry,
                0,
                23,
                mergeCarries: $daysAreWildcard
            );

            if ($hourGroups === null) {
                return null;
            }

            foreach ($hourGroups as $hourCarry => $hourValues) {
                $parts = $segments;
                $parts[0] = $minuteValues;
                $parts[1] = $hourValues;

                $carried = static::expressionsForHourCarry($segments, $parts, (int)$hourCarry);
                if ($carried === null) {
                    return null;
                }

                $expressions = array_merge($expressions, $carried);
            }
        }

        return $expressions;
    }

    /**
     * Build expressions for the given hour carry direction.
     *
     * @param array<int, string> $segments Original cron segments
     * @param array<int, string> $parts Working cron segments
     * @param int $hourCarry Hour carry direction
     * @return array<int, string>|null
     */
    protected static function expressionsForHourCarry(array $segments, array $parts, int $hourCarry): ?array
    {
        if ($hourCarry === 0) {
            return [implode(' ', $parts)];
        }

        if ($segments[4] !== '*') {
            // Cron treats restricted day-of-month and day-of-week fields as an "or",
            // so they cannot shift together...
            if ($segments[2] !== '*' || $segments[3] !== '*') {
                return null;
            }

            $days = static::expand($segments[4], 0, 7);
            if ($days === null) {
                return null;
            }

            $parts[4] = static::collapse(static::shiftWrapped($days, $hourCarry, 7), 0, 6);

            return [implode(' ', $parts)];
        }

        if ($segments[2] === '*' && $segments[3] === '*') {
            return [implode(' ', $parts)];
        }

        $dayGroups = static::shiftDaysOfMonth($segments[2], $segments[3], $hourCarry);
        if ($dayGroups === null) {
            return null;
        }

        return array_map(static function (array $group) use ($parts): string {
            [$parts[2], $parts[3]] = $group;

            return implode(' ', $parts);
        }, $dayGroups);
    }

    /**
     * Shift the day-of-month field by the given carry, respecting month lengths.
     *
     * @param string $domField Day-of-month field
     * @param string $monthField Month field
     * @param int $carry Day carry direction
     * @return array<int, array{string, string}>|null
     */
    protected static function shiftDaysOfMonth(string $domField, string $monthField, int $carry): ?array
    {
        $months = $monthField === '*' ? range(1, 12) : static::expand($monthField, 1, 12);
        $days = $domField === '*' ? range(1, 31) : static::expand($domField, 1, 31);

        if ($months === null || $days === null) {
            return null;
        }

        $shifted = [];

        foreach ($months as $month) {
            foreach ($days as $day) {
                if ($month === 2 && $day === 29) {
                    return null;
                }

                if ($day > static::daysInMonth($month)) {
                    continue;
                }

                [$targetMonth, $targetDay] = [$month, $day + $carry];

                while ($targetDay < 1) {
                    $targetMonth = $targetMonth === 1 ? 12 : $targetMonth - 1;

                    if ($targetMonth === 2) {
                        return null;
                    }

                    $targetDay += static::daysInMonth($targetMonth);
                }

                while ($targetDay > static::daysInMonth($targetMonth)) {
                    if ($targetMonth === 2) {
                        return null;
                    }

                    $targetDay -= static::daysInMonth($targetMonth);
                    $targetMonth = $targetMonth === 12 ? 1 : $targetMonth + 1;
                }

                $shifted[$targetMonth][$targetDay] = true;
            }
        }

        if ($shifted === []) {
            return null;
        }

        // Group months by their shifted day list. Entries are used instead of
        // implode(',', $days) array keys because PHP casts numeric string keys
        // to int, which would break explode() on single-day groups.
        $entries = [];

        foreach ($shifted as $month => $monthDays) {
            $days = array_keys($monthDays);
            sort($days);

            foreach ($entries as &$entry) {
                if ($entry['days'] === $days) {
                    $entry['months'][] = $month;
                    continue 2;
                }
            }

            unset($entry);

            $entries[] = ['days' => $days, 'months' => [$month]];
        }

        unset($entry);

        $result = [];
        foreach ($entries as $entry) {
            $months = $entry['months'];
            sort($months);
            $result[] = [
                static::collapse($entry['days'], 1, 31),
                static::collapse($months, 1, 12),
            ];
        }

        return $result;
    }

    /**
     * Get the number of days in the given month of a non-leap year.
     *
     * @param int $month Month number
     * @return int
     */
    protected static function daysInMonth(int $month): int
    {
        return Chronos::create(2023, $month, 1)->daysInMonth;
    }

    /**
     * Shift a cron field by the given offset and group it by carry direction.
     *
     * @param string $field Cron field value
     * @param int $offset Offset to apply
     * @param int $min Minimum field value
     * @param int $max Maximum field value
     * @param bool $mergeCarries Whether to merge carries into a single group
     * @return array<int, string>|null
     */
    protected static function shiftedGroups(
        string $field,
        int $offset,
        int $min,
        int $max,
        bool $mergeCarries
    ): ?array {
        if ($offset === 0) {
            return [0 => $field];
        }

        $values = static::expand($field, $min, $max);
        if ($values === null) {
            return null;
        }

        $groups = static::shiftAndGroupValues($values, $offset, $max - $min + 1, $min);

        if ($mergeCarries && count($groups) > 1) {
            $groups = [0 => static::mergeGroups($groups)];
        }

        $result = [];
        foreach ($groups as $carry => $group) {
            $result[$carry] = static::collapse($group, $min, $max);
        }

        return $result;
    }

    /**
     * Expand a cron field into the sorted list of values it matches.
     *
     * @param string $field Cron field value
     * @param int $min Minimum field value
     * @param int $max Maximum field value
     * @return array<int, int>|null
     */
    protected static function expand(string $field, int $min, int $max): ?array
    {
        if ($field === '*') {
            return range($min, $max);
        }

        $values = [];

        foreach (explode(',', $field) as $part) {
            if (!preg_match('/^(\*|\d+(?:-\d+)?)(?:\/(\d+))?$/', $part, $matches)) {
                return null;
            }

            $step = ($matches[2] ?? '') !== '' ? (int)$matches[2] : null;

            if ($step === 0) {
                return null;
            }

            if ($matches[1] === '*') {
                [$start, $end] = [$min, $max];
            } elseif (str_contains($matches[1], '-')) {
                [$start, $end] = array_map(intval(...), explode('-', $matches[1]));
            } else {
                [$start, $end] = [(int)$matches[1], $step === null ? (int)$matches[1] : $max];
            }

            if ($start < $min || $end > $max || $start > $end) {
                return null;
            }

            for ($value = $start; $value <= $end; $value += $step ?? 1) {
                $values[] = $value;
            }
        }

        $values = array_values(array_unique($values));
        sort($values);

        return $values;
    }

    /**
     * Shift values in a cron field and group them by carry direction.
     *
     * @param array<int, int> $values Field values
     * @param int $offset Offset to apply
     * @param int $mod Modulus for wraparound
     * @param int $min Minimum field value
     * @return array<int, array<int, int>>
     */
    protected static function shiftAndGroupValues(array $values, int $offset, int $mod, int $min = 0): array
    {
        $groups = [];

        foreach ($values as $value) {
            $carry = (int)floor(($value + $offset - $min) / $mod);
            $groups[$carry][] = $value + $offset - $carry * $mod;
        }

        foreach ($groups as &$group) {
            sort($group);
        }

        unset($group);

        return $groups;
    }

    /**
     * Shift values within a cron field, wrapping around its domain.
     *
     * @param array<int, int> $values Field values
     * @param int $offset Offset to apply
     * @param int $mod Modulus for wraparound
     * @param int $min Minimum field value
     * @return array<int, int>
     */
    protected static function shiftWrapped(array $values, int $offset, int $mod, int $min = 0): array
    {
        $shifted = array_values(array_unique(array_map(
            static fn(int $value): int => (($value + $offset - $min) % $mod + $mod) % $mod + $min,
            $values
        )));
        sort($shifted);

        return $shifted;
    }

    /**
     * Merge grouped values into a single sorted group.
     *
     * @param array<int, array<int, int>> $groups Grouped values
     * @return array<int, int>
     */
    protected static function mergeGroups(array $groups): array
    {
        $merged = array_merge(...array_values($groups));
        sort($merged);

        return $merged;
    }

    /**
     * Collapse a sorted list of values into the most compact cron field syntax.
     *
     * @param array<int, int> $values Sorted values
     * @param int $min Minimum field value
     * @param int $max Maximum field value
     * @return string
     */
    protected static function collapse(array $values, int $min, int $max): string
    {
        if ($values === range($min, $max)) {
            return '*';
        }

        if (count($values) === 1) {
            return (string)$values[0];
        }

        $steps = [];
        $valueCount = count($values);
        for ($i = 1; $i < $valueCount; $i++) {
            $steps[] = $values[$i] - $values[$i - 1];
        }

        $steps = array_values(array_unique($steps));

        if (count($values) < 3 || count($steps) > 1) {
            return static::collapseRuns($values);
        }

        [$first, $last, $step] = [$values[0], $values[count($values) - 1], $steps[0]];

        if ($step === 1) {
            return "{$first}-{$last}";
        }

        return $first === $min && $last + $step > $max
            ? "*/{$step}"
            : "{$first}-{$last}/{$step}";
    }

    /**
     * Collapse consecutive runs of three or more values into ranges.
     *
     * @param array<int, int> $values Sorted values
     * @return string
     */
    protected static function collapseRuns(array $values): string
    {
        $runs = [];
        $current = [];

        foreach ($values as $value) {
            if ($current !== [] && $value !== $current[count($current) - 1] + 1) {
                $runs[] = $current;
                $current = [];
            }

            $current[] = $value;
        }

        if ($current !== []) {
            $runs[] = $current;
        }

        $parts = [];
        foreach ($runs as $run) {
            if (count($run) >= 3) {
                $parts[] = $run[0] . '-' . $run[count($run) - 1];
            } else {
                foreach ($run as $runValue) {
                    $parts[] = (string)$runValue;
                }
            }
        }

        return implode(',', $parts);
    }
}
