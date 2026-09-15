<?php
declare(strict_types=1);

namespace Crustum\Scheduling\Test\TestCase;

use Cake\Chronos\Chronos;
use Cake\TestSuite\TestCase;
use Crustum\Scheduling\CacheEventMutex;
use Crustum\Scheduling\CronExpressionTimezoneConverter;
use Crustum\Scheduling\Event;
use DateTimeZone;
use PHPUnit\Framework\Attributes\DataProvider;

class CronExpressionTimezoneConverterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Chronos::setTestNow('2026-01-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Chronos::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array<string, array{0: string, 1: string, 2: string|null, 3: array<int, string>}>
     */
    public static function expressionTimezoneConversionProvider(): array
    {
        return [
            'same timezone' => ['0 8 * * *', 'UTC', null, ['0 8 * * *']],
            'every minute' => ['* * * * *', 'America/New_York', null, ['* * * * *']],
            'every five minutes' => ['*/5 * * * *', 'Asia/Tokyo', null, ['*/5 * * * *']],
            'every two hours' => ['0 */2 * * *', 'Asia/Tokyo', null, ['0 1-23/2 * * *']],
            'every odd hour' => ['0 1-23/2 * * *', 'Asia/Tokyo', null, ['0 */2 * * *']],
            'quarterly step month' => [
                '0 0 1 1-12/3 *',
                'Asia/Tokyo',
                null,
                ['0 15 31 3,12 *', '0 15 30 6,9 *'],
            ],
            'weekdays range' => ['0 8 * * 1-5', 'Asia/Tokyo', null, ['0 23 * * 0-4']],
            'daily LA to UTC' => ['0 8 * * *', 'America/Los_Angeles', null, ['0 16 * * *']],
            'daily Tokyo to UTC' => ['0 14 * * *', 'Asia/Tokyo', null, ['0 5 * * *']],
            'timezone flag' => ['0 0 * * *', 'UTC', 'Asia/Tokyo', ['0 9 * * *']],
            'hour wraps forward' => ['0 23 * * *', 'Asia/Tokyo', null, ['0 14 * * *']],
            'hour wraps backward' => ['0 2 * * *', 'America/Los_Angeles', null, ['0 10 * * *']],
            'Kolkata half hour' => ['0 8 * * *', 'Asia/Kolkata', null, ['30 2 * * *']],
            'Kathmandu +5:45' => ['0 8 * * *', 'Asia/Kathmandu', null, ['15 2 * * *']],
            'twice daily Tokyo' => ['0 9,17 * * *', 'Asia/Tokyo', null, ['0 0,8 * * *']],
            'twice daily Tokyo wrapping' => ['0 13,22 * * *', 'Asia/Tokyo', null, ['0 4,13 * * *']],
            'twice daily LA mixed carry' => [
                '0 13,17 * * *',
                'America/Los_Angeles',
                null,
                ['0 1,21 * * *'],
            ],
            'twice daily Tokyo mixed carry' => [
                '0 3,20 * * *',
                'Asia/Tokyo',
                null,
                ['0 11,18 * * *'],
            ],
            'weekly Monday night LA' => ['0 22 * * 1', 'America/Los_Angeles', null, ['0 6 * * 2']],
            'weekly Wednesday morning Tokyo' => ['0 3 * * 3', 'Asia/Tokyo', null, ['0 18 * * 2']],
            'weekly Sunday night LA' => ['0 22 * * 0', 'America/Los_Angeles', null, ['0 6 * * 1']],
            'weekly Saturday morning Tokyo' => ['0 3 * * 6', 'Asia/Tokyo', null, ['0 18 * * 5']],
            'monthly 15th Tokyo (no day wrap)' => ['0 12 15 * *', 'Asia/Tokyo', null, ['0 3 15 * *']],
            'monthly 15th Tokyo (day wraps back)' => ['0 8 15 * *', 'Asia/Tokyo', null, ['0 23 14 * *']],
            'monthly 1st Tokyo (day wraps back)' => ['0 1 1 * *', 'Asia/Tokyo', null, ['0 1 1 * *']],
            'monthly 1st LA (day wraps forward)' => [
                '0 22 1 * *',
                'America/Los_Angeles',
                null,
                ['0 6 2 * *'],
            ],
            'yearly Jan 1 Tokyo' => ['0 1 1 1 *', 'Asia/Tokyo', null, ['0 16 31 12 *']],
            'yearly Jul 1 Tokyo' => ['0 1 1 7 *', 'Asia/Tokyo', null, ['0 16 30 6 *']],
            'yearly Dec 31 LA' => ['0 22 31 12 *', 'America/Los_Angeles', null, ['0 6 1 1 *']],
            'dom 31 across months' => [
                '0 1 31 * *',
                'Asia/Tokyo',
                null,
                ['0 16 30 1,3,5,7,8,10,12 *'],
            ],
            'twice monthly Tokyo (no wrap)' => [
                '0 12 1,16 * *',
                'Asia/Tokyo',
                null,
                ['0 3 1,16 * *'],
            ],
            'twice monthly Tokyo (wraps)' => ['0 1 1,16 * *', 'Asia/Tokyo', null, ['0 1 1,16 * *']],
            'weekends LA night' => ['0 22 * * 0,6', 'America/Los_Angeles', null, ['0 6 * * 0,1']],
            'hourly at 30 Kolkata' => ['30 * * * *', 'Asia/Kolkata', null, ['0 * * * *']],
            'hourly at 0 Kolkata' => ['0 * * * *', 'Asia/Kolkata', null, ['30 * * * *']],
            'comma minutes Kolkata mixed carry' => [
                '15,45 8 * * *',
                'Asia/Kolkata',
                null,
                ['45 2 * * *', '15 3 * * *'],
            ],
            'hour range NY' => ['0 0-4 * * *', 'America/New_York', null, ['0 5-9 * * *']],
            'hour range with minute step Amsterdam' => [
                '*/10 8-23 * * *',
                'Europe/Amsterdam',
                null,
                ['*/10 7-22 * * *'],
            ],
            'hour range with minute step Johannesburg' => [
                '*/10 8-23 * * *',
                'Africa/Johannesburg',
                null,
                ['*/10 6-21 * * *'],
            ],
            'hour range LA' => ['0 8-11 * * *', 'America/Los_Angeles', null, ['0 16-19 * * *']],
            'minute step Kolkata' => ['*/10 * * * *', 'Asia/Kolkata', null, ['*/10 * * * *']],
            'hour range Kolkata' => ['0 9-17 * * *', 'Asia/Kolkata', null, ['30 3-11 * * *']],
            'hour range Kathmandu' => ['0 9-11 * * *', 'Asia/Kathmandu', null, ['15 3-5 * * *']],
            'weeknights range LA' => ['0 22 * * 1-5', 'America/Los_Angeles', null, ['0 6 * * 2-6']],
            'dow range wraps through Sunday' => ['0 3 * * 0-2', 'Asia/Tokyo', null, ['0 18 * * 0,1,6']],
            'hour step with fixed day' => [
                '0 */2 15 * *',
                'Asia/Tokyo',
                null,
                ['0 15-23/2 14 * *', '0 1-13/2 15 * *'],
            ],
            'wildcard hour with fixed day' => [
                '30 * 15 * *',
                'Asia/Tokyo',
                null,
                ['30 15-23 14 * *', '30 0-14 15 * *'],
            ],
            'wildcard minute with half-hour offset' => [
                '* 8 * * *',
                'Asia/Kolkata',
                null,
                ['30-59 2 * * *', '0-29 3 * * *'],
            ],
            'named dow range needing shift' => [
                '0 8 * * MON-FRI',
                'Asia/Tokyo',
                null,
                ['0 8 * * MON-FRI'],
            ],
            'last day of month needing shift' => ['0 8 L * *', 'Asia/Tokyo', null, ['0 8 L * *']],
            'named dow without day carry' => ['0 14 * * MON', 'Asia/Tokyo', null, ['0 5 * * MON']],
            'wildcard dom restricted month' => [
                '0 1 * 1 *',
                'Asia/Tokyo',
                null,
                ['0 16 31 12 *', '0 16 1-30 1 *'],
            ],
            'dom and dow both restricted' => ['0 1 1 3 1', 'Asia/Tokyo', null, ['0 1 1 3 1']],
            'dow with restricted month' => ['0 1 * 1 1', 'Asia/Tokyo', null, ['0 1 * 1 1']],
            'into February' => ['0 1 1 3 *', 'Asia/Tokyo', null, ['0 1 1 3 *']],
            'out of February' => ['0 23 28 2 *', 'America/Los_Angeles', null, ['0 23 28 2 *']],
            'February 29th' => ['0 1 29 2 *', 'Asia/Tokyo', null, ['0 1 29 2 *']],
            'wildcard dom in February' => ['0 1 * 2 *', 'Asia/Tokyo', null, ['0 1 * 2 *']],
            'within February' => ['0 1 15 2 *', 'Asia/Tokyo', null, ['0 16 14 2 *']],
            'into February 1st' => ['0 22 31 1 *', 'America/Los_Angeles', null, ['0 6 1 2 *']],
            'two-day carry forward' => [
                '0 23 31 1 *',
                'Etc/GMT+12',
                'Pacific/Kiritimati',
                ['0 1 2 2 *'],
            ],
            'two-day carry backward' => [
                '0 0 1 4 *',
                'Pacific/Kiritimati',
                'Etc/GMT+12',
                ['0 22 30 3 *'],
            ],
        ];
    }

    /**
     * @param string $expression
     * @param string $eventTimezone
     * @param string|null $displayTimezone
     * @param array<int, string> $expectedExpressions
     */
    #[DataProvider('expressionTimezoneConversionProvider')]
    public function testExpressionTimezoneConversion(
        string $expression,
        string $eventTimezone,
        ?string $displayTimezone,
        array $expectedExpressions
    ): void {
        $event = new Event(new CacheEventMutex(), 'php foo');
        $event->cron($expression)->timezone($eventTimezone);

        $display = new DateTimeZone($displayTimezone ?? 'UTC');
        $converted = CronExpressionTimezoneConverter::forEvent($event, $display);

        $this->assertSame($expectedExpressions, $converted);
    }

    public function testExpressionTimezoneConversionUsesOffsetAtNextRunDate(): void
    {
        Chronos::setTestNow('2023-10-29 UTC');

        try {
            $first = new Event(new CacheEventMutex(), 'php foo');
            $first->cron('0 0 30 10 *')->timezone('Europe/London');

            $second = new Event(new CacheEventMutex(), 'php foo');
            $second->cron('0 0 30 6 *')->timezone('Europe/London');

            $display = new DateTimeZone('UTC');

            $this->assertSame(
                ['0 0 30 10 *'],
                CronExpressionTimezoneConverter::forEvent($first, $display)
            );
            $this->assertSame(
                ['0 23 29 6 *'],
                CronExpressionTimezoneConverter::forEvent($second, $display)
            );
        } finally {
            Chronos::setTestNow('2026-01-15 12:00:00');
        }
    }

    public function testExpressionTimezoneConversionFallsBackAcrossDstTransition(): void
    {
        Chronos::setTestNow('2024-10-26 22:30:00 UTC');

        try {
            $event = new Event(new CacheEventMutex(), 'php foo');
            $event->cron('0 0,2 * * *')->timezone('Europe/London');

            $converted = CronExpressionTimezoneConverter::forEvent($event, new DateTimeZone('UTC'));

            $this->assertSame(['0 0,2 * * *'], $converted);
        } finally {
            Chronos::setTestNow('2026-01-15 12:00:00');
        }
    }
}
