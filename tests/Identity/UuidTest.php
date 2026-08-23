<?php

declare(strict_types=1);

namespace Contempt\Core\Tests\Identity;

use Contempt\Core\Identity\Uuid;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Uuid::class)]
final class UuidTest extends TestCase
{
    #[DataProvider('malformedStrings')]
    public function testMalformedStringsAreRejected(string $value): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::fail('expected a refusal, parsed ' . Uuid::fromString($value)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function malformedStrings(): iterable
    {
        yield 'empty' => [''];
        yield 'too short' => ['0192f7a1-1b2c-7d3e-8f40-1a2b3c4d5e6'];
        yield 'too long' => ['0192f7a1-1b2c-7d3e-8f40-1a2b3c4d5e6f0'];
        yield 'missing dashes' => ['0192f7a11b2c7d3e8f401a2b3c4d5e6f'];
        yield 'dashes in wrong places' => ['0192f7a11-b2c-7d3e-8f40-1a2b3c4d5e6f'];
        yield 'non-hex character' => ['0192f7a1-1b2c-7d3e-8f40-1a2b3c4d5e6g'];
        yield 'braced form' => ['{0192f7a1-1b2c-7d3e-8f40-1a2b3c4d5e6f}'];
        yield 'urn form' => ['urn:uuid:0192f7a1-1b2c-7d3e-8f40-1a2b3c4d5e6f'];
        yield 'leading space' => [' 0192f7a1-1b2c-7d3e-8f40-1a2b3c4d5e6f'];
        yield 'trailing newline' => ["0192f7a1-1b2c-7d3e-8f40-1a2b3c4d5e6f\n"];
        yield 'null byte' => ["0192f7a1-1b2c-7d3e-8f40-1a2b3c4d5e6f\0"];
    }

    /**
     * RFC 9562 §4 requires accepting either case on input and emitting lower
     * case, so a value that round-trips through a database cannot change
     * identity.
     */
    public function testUppercaseInputIsAcceptedAndNormalisedToLowercase(): void
    {
        $uuid = Uuid::fromString('0192F7A1-1B2C-7D3E-8F40-1A2B3C4D5E6F');

        self::assertSame('0192f7a1-1b2c-7d3e-8f40-1a2b3c4d5e6f', $uuid->toString());
        self::assertTrue($uuid->equals(Uuid::fromString('0192f7a1-1b2c-7d3e-8f40-1a2b3c4d5e6f')));
    }

    #[DataProvider('invalidByteStrings')]
    public function testByteConstructorRequiresExactlySixteenBytes(string $bytes): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::fail('expected a refusal, parsed ' . Uuid::fromBytes($bytes)->toString());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidByteStrings(): iterable
    {
        yield 'empty' => [''];
        yield 'fifteen' => [str_repeat("\x00", 15)];
        yield 'seventeen' => [str_repeat("\x00", 17)];
    }

    /**
     * The nil and max UUIDs are structurally valid but carry no version. They
     * must parse without being mistaken for a v7 identifier.
     */
    public function testNilAndMaxParseButAreNotVersionSeven(): void
    {
        $nil = Uuid::fromString('00000000-0000-0000-0000-000000000000');
        $max = Uuid::fromString('ffffffff-ffff-ffff-ffff-ffffffffffff');

        self::assertSame(0, $nil->version());
        self::assertSame(15, $max->version());
        self::assertNotSame($nil->toString(), $max->toString());
    }

    public function testGeneratedIdentifiersCarryVersionSevenAndVariantTen(): void
    {
        $uuid = Uuid::v7();

        self::assertSame(7, $uuid->version());
        self::assertSame(2, $uuid->variant(), 'RFC 9562 variant b10x');
        self::assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-7[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $uuid->toString(),
        );
    }

    public function testGeneratedIdentifiersAreUnique(): void
    {
        $seen = [];
        for ($i = 0; $i < 1000; ++$i) {
            $seen[Uuid::v7()->toString()] = true;
        }

        self::assertCount(1000, $seen);
    }

    /**
     * The point of v7 is that lexical order equals creation order across
     * milliseconds. Byte comparison must reproduce that.
     */
    public function testOrderingFollowsTheEmbeddedTimestamp(): void
    {
        $earlier = Uuid::v7(new \DateTimeImmutable('@1700000000'));
        $later = Uuid::v7(new \DateTimeImmutable('@1700000001'));

        self::assertSame(-1, $earlier->compareTo($later));
        self::assertSame(1, $later->compareTo($earlier));
        self::assertTrue($earlier->toString() < $later->toString(), 'lexical order matches time order');
    }

    public function testTimestampIsRecoverableAtMillisecondPrecision(): void
    {
        $at = new \DateTimeImmutable('2026-08-22T10:11:12.345000+00:00');

        $recovered = Uuid::v7($at)->createdAt();

        self::assertNotNull($recovered);
        self::assertSame($at->format('Y-m-d\TH:i:s.v'), $recovered->format('Y-m-d\TH:i:s.v'));
    }

    /**
     * A timestamp before the Unix epoch cannot be encoded in the unsigned
     * 48-bit field, and silently wrapping it would produce a far-future id.
     */
    public function testTimestampsBeforeTheEpochAreRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::fail('expected a refusal, produced ' . Uuid::v7(new \DateTimeImmutable('@-1'))->toString());
    }

    public function testTimestampIsUnavailableForNonVersionSeven(): void
    {
        self::assertNull(Uuid::fromString('00000000-0000-4000-8000-000000000000')->createdAt());
    }

    public function testBytesAndStringAreTwoViewsOfTheSameValue(): void
    {
        $uuid = Uuid::v7();

        self::assertSame(16, \strlen($uuid->toBytes()));
        self::assertTrue(Uuid::fromBytes($uuid->toBytes())->equals($uuid));
        self::assertSame($uuid->toString(), (string) $uuid);
    }
}
