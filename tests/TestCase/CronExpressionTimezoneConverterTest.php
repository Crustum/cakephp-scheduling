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
            'daily LA to UTC' => ['0 8 * * *', 'America/Los_Angeles', null, ['0 16 * * *']],
            'daily Tokyo to UTC' => ['0 14 * * *', 'Asia/Tokyo', null, ['0 5 * * *']],
            'timezone flag' => ['0 0 * * *', 'UTC', 'Asia/Tokyo', ['0 9 * * *']],
            'Kolkata half hour' => ['0 8 * * *', 'Asia/Kolkata', null, ['30 2 * * *']],
            'twice daily LA mixed carry' => ['0 13,17 * * *', 'America/Los_Angeles', null, ['0 21 * * *', '0 1 * * *']],
            'weekly Monday night LA' => ['0 22 * * 1', 'America/Los_Angeles', null, ['0 6 * * 2']],
            'monthly 15th Tokyo day wrap' => ['0 8 15 * *', 'Asia/Tokyo', null, ['0 23 14 * *']],
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
}
