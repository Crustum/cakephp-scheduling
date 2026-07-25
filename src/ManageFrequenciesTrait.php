<?php
declare(strict_types=1);

namespace Crustum\Scheduling;

use Cake\Chronos\Chronos;
use InvalidArgumentException;

/**
 * Manage Frequencies Trait
 *
 * Provides scheduling frequency methods for events.
 */
trait ManageFrequenciesTrait
{
    /**
     * The Cron expression representing the event's frequency.
     *
     * @param string $expression The cron expression
     * @return $this
     */
    public function cron(string $expression)
    {
        $this->expression = $expression;

        return $this;
    }

    /**
     * Schedule the event to run between start and end time.
     *
     * @param string $startTime The start time
     * @param string $endTime The end time
     * @return $this
     */
    public function between(string $startTime, string $endTime)
    {
        return $this->when($this->inTimeInterval($startTime, $endTime));
    }

    /**
     * Schedule the event to not run between start and end time.
     *
     * @param string $startTime The start time
     * @param string $endTime The end time
     * @return $this
     */
    public function unlessBetween(string $startTime, string $endTime)
    {
        return $this->skip($this->inTimeInterval($startTime, $endTime));
    }

    /**
     * Schedule the event to run between start and end time.
     *
     * @param string $startTime The start time
     * @param string $endTime The end time
     * @return callable
     */
    private function inTimeInterval(string $startTime, string $endTime): callable
    {
        $now = Chronos::now();

        return function () use ($startTime, $endTime, $now): bool {
            $current = $now;

            if ($this->timezone) {
                $current = $current->setTimezone($this->timezone);
            }

            $start = Chronos::parse($startTime, $this->timezone);
            $end = Chronos::parse($endTime, $this->timezone);

            if ($end->lessThan($start)) {
                if ($start->greaterThan($current)) {
                    $start = $start->subDays(1);
                } else {
                    $end = $end->addDays(1);
                }
            }

            return $current->between($start, $end);
        };
    }

    /**
     * Schedule the event to run every second.
     *
     * @return $this
     */
    public function everySecond()
    {
        return $this->repeatEvery(1);
    }

    /**
     * Schedule the event to run every two seconds.
     *
     * @return $this
     */
    public function everyTwoSeconds()
    {
        return $this->repeatEvery(2);
    }

    /**
     * Schedule the event to run every five seconds.
     *
     * @return $this
     */
    public function everyFiveSeconds()
    {
        return $this->repeatEvery(5);
    }

    /**
     * Schedule the event to run every ten seconds.
     *
     * @return $this
     */
    public function everyTenSeconds()
    {
        return $this->repeatEvery(10);
    }

    /**
     * Schedule the event to run every fifteen seconds.
     *
     * @return $this
     */
    public function everyFifteenSeconds()
    {
        return $this->repeatEvery(15);
    }

    /**
     * Schedule the event to run every twenty seconds.
     *
     * @return $this
     */
    public function everyTwentySeconds()
    {
        return $this->repeatEvery(20);
    }

    /**
     * Schedule the event to run every thirty seconds.
     *
     * @return $this
     */
    public function everyThirtySeconds()
    {
        return $this->repeatEvery(30);
    }

    /**
     * Schedule the event to run multiple times per minute.
     *
     * @param int $seconds The seconds interval (must be 1-59 and divide 60 evenly)
     * @return $this
     * @throws \InvalidArgumentException
     */
    protected function repeatEvery(int $seconds)
    {
        if ($seconds <= 0) {
            throw new InvalidArgumentException("The seconds [{$seconds}] must be greater than zero.");
        }

        if (60 % $seconds !== 0) {
            throw new InvalidArgumentException("The seconds [{$seconds}] are not evenly divisible by 60.");
        }

        $this->repeatSeconds = $seconds;

        return $this->everyMinute();
    }

    /**
     * Schedule the event to run every minute.
     *
     * @return $this
     */
    public function everyMinute()
    {
        return $this->spliceIntoPosition(1, '*');
    }

    /**
     * Schedule the event to run every two minutes.
     *
     * @return $this
     */
    public function everyTwoMinutes()
    {
        return $this->spliceIntoPosition(1, '*/2');
    }

    /**
     * Schedule the event to run every three minutes.
     *
     * @return $this
     */
    public function everyThreeMinutes()
    {
        return $this->spliceIntoPosition(1, '*/3');
    }

    /**
     * Schedule the event to run every four minutes.
     *
     * @return $this
     */
    public function everyFourMinutes()
    {
        return $this->spliceIntoPosition(1, '*/4');
    }

    /**
     * Schedule the event to run every five minutes.
     *
     * @return $this
     */
    public function everyFiveMinutes()
    {
        return $this->spliceIntoPosition(1, '*/5');
    }

    /**
     * Schedule the event to run every ten minutes.
     *
     * @return $this
     */
    public function everyTenMinutes()
    {
        return $this->spliceIntoPosition(1, '*/10');
    }

    /**
     * Schedule the event to run every fifteen minutes.
     *
     * @return $this
     */
    public function everyFifteenMinutes()
    {
        return $this->spliceIntoPosition(1, '*/15');
    }

    /**
     * Schedule the event to run every thirty minutes.
     *
     * @return $this
     */
    public function everyThirtyMinutes()
    {
        return $this->spliceIntoPosition(1, '*/30');
    }

    /**
     * Schedule the event to run hourly.
     *
     * @return $this
     */
    public function hourly()
    {
        return $this->spliceIntoPosition(1, 0);
    }

    /**
     * Schedule the event to run hourly at a given offset in the hour.
     *
     * @param array<int>|string|int $offset The offset
     * @return $this
     */
    public function hourlyAt($offset)
    {
        return $this->hourBasedSchedule($offset, '*');
    }

    /**
     * Schedule the event to run every odd hour.
     *
     * @param array<int>|string|int $offset The offset
     * @return $this
     */
    public function everyOddHour($offset = 0)
    {
        return $this->hourBasedSchedule($offset, '1-23/2');
    }

    /**
     * Schedule the event to run every two hours.
     *
     * @param array<int>|string|int $offset The offset
     * @return $this
     */
    public function everyTwoHours($offset = 0)
    {
        return $this->hourBasedSchedule($offset, '*/2');
    }

    /**
     * Schedule the event to run every three hours.
     *
     * @param array<int>|string|int $offset The offset
     * @return $this
     */
    public function everyThreeHours($offset = 0)
    {
        return $this->hourBasedSchedule($offset, '*/3');
    }

    /**
     * Schedule the event to run every four hours.
     *
     * @param array<int>|string|int $offset The offset
     * @return $this
     */
    public function everyFourHours($offset = 0)
    {
        return $this->hourBasedSchedule($offset, '*/4');
    }

    /**
     * Schedule the event to run every six hours.
     *
     * @param array<int>|string|int $offset The offset
     * @return $this
     */
    public function everySixHours($offset = 0)
    {
        return $this->hourBasedSchedule($offset, '*/6');
    }

    /**
     * Schedule the event to run daily.
     *
     * @return $this
     */
    public function daily()
    {
        return $this->hourBasedSchedule(0, 0);
    }

    /**
     * Schedule the command at a given time.
     *
     * @param string $time The time
     * @return $this
     */
    public function at(string $time)
    {
        return $this->dailyAt($time);
    }

    /**
     * Schedule the event to run daily at a given time (10:00, 19:30, etc).
     *
     * @param string $time The time
     * @return $this
     */
    public function dailyAt(string $time)
    {
        $segments = explode(':', $time);

        return $this->hourBasedSchedule(
            count($segments) >= 2 ? (int)$segments[1] : '0',
            (int)$segments[0]
        );
    }

    /**
     * Schedule the event to run twice daily.
     *
     * @param int $first The first hour
     * @param int $second The second hour
     * @return $this
     */
    public function twiceDaily(int $first = 1, int $second = 13)
    {
        return $this->twiceDailyAt($first, $second, 0);
    }

    /**
     * Schedule the event to run twice daily at a given offset.
     *
     * @param int $first The first hour
     * @param int $second The second hour
     * @param int $offset The offset
     * @return $this
     */
    public function twiceDailyAt(int $first = 1, int $second = 13, int $offset = 0)
    {
        $hours = $first . ',' . $second;

        return $this->hourBasedSchedule($offset, $hours);
    }

    /**
     * Schedule the event to run at the given minutes and hours.
     *
     * @param array<int>|string|int $minutes The minutes
     * @param array<int>|string|int $hours The hours
     * @return $this
     */
    protected function hourBasedSchedule($minutes, $hours)
    {
        $minutes = is_array($minutes) ? implode(',', $minutes) : (string)$minutes;
        $hours = is_array($hours) ? implode(',', $hours) : (string)$hours;

        return $this->spliceIntoPosition(1, $minutes)
            ->spliceIntoPosition(2, $hours);
    }

    /**
     * Schedule the event to run only on weekdays.
     *
     * @return $this
     */
    public function weekdays()
    {
        return $this->days('1-5');
    }

    /**
     * Schedule the event to run only on weekends.
     *
     * @return $this
     */
    public function weekends()
    {
        return $this->days('6,0');
    }

    /**
     * Schedule the event to run only on Mondays.
     *
     * @return $this
     */
    public function mondays()
    {
        return $this->days('1');
    }

    /**
     * Schedule the event to run only on Tuesdays.
     *
     * @return $this
     */
    public function tuesdays()
    {
        return $this->days('2');
    }

    /**
     * Schedule the event to run only on Wednesdays.
     *
     * @return $this
     */
    public function wednesdays()
    {
        return $this->days('3');
    }

    /**
     * Schedule the event to run only on Thursdays.
     *
     * @return $this
     */
    public function thursdays()
    {
        return $this->days('4');
    }

    /**
     * Schedule the event to run only on Fridays.
     *
     * @return $this
     */
    public function fridays()
    {
        return $this->days('5');
    }

    /**
     * Schedule the event to run only on Saturdays.
     *
     * @return $this
     */
    public function saturdays()
    {
        return $this->days('6');
    }

    /**
     * Schedule the event to run only on Sundays.
     *
     * @return $this
     */
    public function sundays()
    {
        return $this->days('0');
    }

    /**
     * Schedule the event to run weekly.
     *
     * @return $this
     */
    public function weekly()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(5, 0);
    }

    /**
     * Schedule the event to run weekly on a given day and time.
     *
     * @param array<int>|int $dayOfWeek The day of week
     * @param string $time The time
     * @return $this
     */
    public function weeklyOn($dayOfWeek, string $time = '0:0')
    {
        $this->dailyAt($time);

        return $this->days($dayOfWeek);
    }

    /**
     * Schedule the event to run monthly.
     *
     * @return $this
     */
    public function monthly()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1);
    }

    /**
     * Schedule the event to run monthly on a given day and time.
     *
     * @param int $dayOfMonth The day of month
     * @param string $time The time
     * @return $this
     */
    public function monthlyOn(int $dayOfMonth = 1, string $time = '0:0')
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(3, $dayOfMonth);
    }

    /**
     * Schedule the event to run twice monthly at a given time.
     *
     * @param int $first The first day
     * @param int $second The second day
     * @param string $time The time
     * @return $this
     */
    public function twiceMonthly(int $first = 1, int $second = 16, string $time = '0:0')
    {
        $daysOfMonth = $first . ',' . $second;

        $this->dailyAt($time);

        return $this->spliceIntoPosition(3, $daysOfMonth);
    }

    /**
     * Schedule the event to run on the last day of the month.
     *
     * @param string $time The time
     * @return $this
     */
    public function lastDayOfMonth(string $time = '0:0')
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(3, Chronos::now()->endOfMonth()->day);
    }

    /**
     * Schedule the event to run on specific days of the month.
     *
     * @param array<int, int>|int ...$days Days of the month (1-31), or a single array of days
     * @return $this
     */
    public function daysOfMonth(array|int ...$days)
    {
        if (count($days) === 1 && is_array($days[0])) {
            $normalizedDays = $days[0];
        } else {
            $normalizedDays = [];
            foreach ($days as $day) {
                if (!is_int($day)) {
                    throw new InvalidArgumentException('daysOfMonth() expects int days or a single int array.');
                }

                $normalizedDays[] = $day;
            }
        }

        $this->dailyAt('0:0');

        return $this->spliceIntoPosition(3, implode(',', $normalizedDays));
    }

    /**
     * Schedule the event to run quarterly.
     *
     * @return $this
     */
    public function quarterly()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1)
            ->spliceIntoPosition(4, '1-12/3');
    }

    /**
     * Schedule the event to run quarterly on a given day and time.
     *
     * @param int $dayOfQuarter The day of quarter
     * @param string $time The time
     * @return $this
     */
    public function quarterlyOn(int $dayOfQuarter = 1, string $time = '0:0')
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(3, $dayOfQuarter)
            ->spliceIntoPosition(4, '1-12/3');
    }

    /**
     * Schedule the event to run yearly.
     *
     * @return $this
     */
    public function yearly()
    {
        return $this->spliceIntoPosition(1, 0)
            ->spliceIntoPosition(2, 0)
            ->spliceIntoPosition(3, 1)
            ->spliceIntoPosition(4, 1);
    }

    /**
     * Schedule the event to run yearly on a given month, day, and time.
     *
     * @param int $month The month
     * @param int|string $dayOfMonth The day of month
     * @param string $time The time
     * @return $this
     */
    public function yearlyOn(int $month = 1, $dayOfMonth = 1, string $time = '0:0')
    {
        $this->dailyAt($time);

        return $this->spliceIntoPosition(3, $dayOfMonth)
            ->spliceIntoPosition(4, $month);
    }

    /**
     * Set the days of the week the command should run on.
     *
     * @param array<int>|int|string $days The days
     * @return $this
     */
    public function days($days)
    {
        $days = is_array($days) ? $days : func_get_args();

        return $this->spliceIntoPosition(5, implode(',', $days));
    }

    /**
     * Set the timezone the date should be evaluated on.
     *
     * @param \DateTimeZone|string $timezone The timezone
     * @return $this
     */
    public function timezone($timezone)
    {
        $this->timezone = $timezone;

        return $this;
    }

    /**
     * Splice the given value into the given position of the expression.
     *
     * @param int $position The position
     * @param string|int $value The value
     * @return $this
     */
    protected function spliceIntoPosition(int $position, $value)
    {
        $segments = preg_split("/\s+/", $this->expression);

        if ($segments === false) {
            return $this;
        }

        $segments[$position - 1] = (string)$value;

        return $this->cron(implode(' ', $segments));
    }
}
