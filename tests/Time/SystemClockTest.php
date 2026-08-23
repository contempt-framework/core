<?php

declare(strict_types=1);

namespace Contempt\Core\Tests\Time;

use Contempt\Core\Time\SystemClock;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;

#[CoversClass(SystemClock::class)]
final class SystemClockTest extends TestCase
{
    /**
     * Message metadata must never carry an ambiguous local timestamp
     * (contempt.md §167), so the clock pins UTC regardless of `date.timezone`.
     */
    public function testNowIsAlwaysUtcRegardlessOfAmbiguousLocalTime(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('America/Santiago');

        try {
            $now = new SystemClock()->now();

            self::assertSame('UTC', $now->getTimezone()->getName());
            self::assertSame(0, $now->getOffset());
        } finally {
            date_default_timezone_set($previous);
        }
    }

    public function testItIsAPsr20Clock(): void
    {
        self::assertContains(ClockInterface::class, class_implements(SystemClock::class) ?: []);
    }

    public function testSuccessiveReadsNeverGoBackwards(): void
    {
        $clock = new SystemClock();
        $first = $clock->now();
        $second = $clock->now();

        self::assertGreaterThanOrEqual($first, $second);
        self::assertNotSame($first, $second, 'each read is a fresh immutable value');
    }

    /**
     * A whole-second clock makes duration measurement and message ordering
     * useless, so sub-second resolution is part of the contract.
     */
    public function testResolutionIsFinerThanOneSecond(): void
    {
        $clock = new SystemClock();
        $fractions = [];

        for ($i = 0; $i < 20; ++$i) {
            $fractions[] = $clock->now()->format('u');
        }

        self::assertNotSame(['000000'], array_values(array_unique($fractions)));
    }
}
