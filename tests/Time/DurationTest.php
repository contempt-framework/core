<?php

declare(strict_types=1);

namespace Contempt\Core\Tests\Time;

use Contempt\Core\Time\Duration;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Duration::class)]
final class DurationTest extends TestCase
{
    /**
     * A negative backoff would make a retry loop spin. Rejecting it at
     * construction keeps the check out of every consumer.
     */
    #[DataProvider('negativeAmounts')]
    public function testNegativeDurationsAreRejected(int $milliseconds): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('must not be negative');

        self::fail('expected a refusal, produced ' . Duration::milliseconds($milliseconds)->toMilliseconds() . ' ms');
    }

    /**
     * @return iterable<string, array{int}>
     */
    public static function negativeAmounts(): iterable
    {
        yield 'minus one' => [-1];
        yield 'minimum int' => [PHP_INT_MIN];
    }

    public function testZeroIsValidAndIdentifiable(): void
    {
        $zero = Duration::milliseconds(0);

        self::assertTrue($zero->isZero());
        self::assertSame(0, $zero->toMilliseconds());
        self::assertSame(0, $zero->toMicroseconds());
    }

    /**
     * Exponential backoff multiplies repeatedly; the growth must saturate
     * instead of wrapping into a negative delay.
     */
    public function testMultiplicationSaturatesInsteadOfOverflowing(): void
    {
        $huge = Duration::milliseconds(PHP_INT_MAX);

        self::assertSame(PHP_INT_MAX, $huge->multipliedBy(2.0)->toMilliseconds());
        self::assertSame(PHP_INT_MAX, $huge->plus($huge)->toMilliseconds());
    }

    public function testMicrosecondConversionSaturatesRatherThanWrapping(): void
    {
        self::assertSame(PHP_INT_MAX, Duration::milliseconds(PHP_INT_MAX)->toMicroseconds());
    }

    #[DataProvider('invalidMultipliers')]
    public function testMultiplierMustBeFiniteAndNonNegative(float $multiplier): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::fail('expected a refusal, produced ' . Duration::milliseconds(100)->multipliedBy($multiplier)->toMilliseconds() . ' ms');
    }

    /**
     * @return iterable<string, array{float}>
     */
    public static function invalidMultipliers(): iterable
    {
        yield 'negative' => [-0.5];
        yield 'nan' => [NAN];
        yield 'infinite' => [INF];
        yield 'negative infinite' => [-INF];
    }

    public function testMultiplicationTruncatesTowardsZeroAndNeverBelowZero(): void
    {
        self::assertSame(0, Duration::milliseconds(1)->multipliedBy(0.4)->toMilliseconds());
        self::assertSame(0, Duration::milliseconds(3)->multipliedBy(0.0)->toMilliseconds());
        self::assertSame(1, Duration::milliseconds(3)->multipliedBy(0.5)->toMilliseconds());
    }

    public function testSecondsFactoryRejectsOverflowingInput(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('overflow');

        self::fail('expected a refusal, produced ' . Duration::seconds(PHP_INT_MAX)->toMilliseconds() . ' ms');
    }

    public function testComparisonIsATotalOrder(): void
    {
        $small = Duration::milliseconds(1);
        $large = Duration::seconds(1);

        self::assertSame(-1, $small->compareTo($large));
        self::assertSame(1, $large->compareTo($small));
        self::assertSame(0, $small->compareTo(Duration::milliseconds(1)));
        self::assertTrue($large->equals(Duration::milliseconds(1000)));
    }

    public function testDurationsAreImmutable(): void
    {
        $original = Duration::milliseconds(100);
        $derived = $original->multipliedBy(3.0)->plus(Duration::milliseconds(1));

        self::assertSame(100, $original->toMilliseconds());
        self::assertSame(301, $derived->toMilliseconds());
    }
}
