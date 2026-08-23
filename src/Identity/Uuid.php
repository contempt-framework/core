<?php

declare(strict_types=1);

namespace Contempt\Core\Identity;

/**
 * An RFC 9562 UUID, generated as version 7 (Unix-time ordered).
 *
 * Version 7 is used so identifiers sort by creation time, which keeps database
 * index locality and message ordering sane. Ordering is millisecond-granular:
 * two ids minted in the same millisecond have no defined order, because the
 * alternative — a monotonic counter — requires global mutable state, which the
 * framework forbids (contempt.md §177).
 *
 * The value is held as 16 raw bytes; the canonical string is derived on demand.
 */
final readonly class Uuid implements \Stringable
{
    private const int TIMESTAMP_BYTES = 6;
    private const int MAX_TIMESTAMP_MS = (1 << 48) - 1;

    private function __construct(private string $bytes) {}

    /**
     * @param ?\DateTimeImmutable $at the instant to embed; `null` uses the
     *                                system clock. Pass an explicit instant in
     *                                tests to keep the timestamp deterministic.
     *
     * @throws \InvalidArgumentException when the instant precedes the Unix
     *                                   epoch or exceeds the 48-bit field
     */
    #[\NoDiscard]
    public static function v7(?\DateTimeImmutable $at = null): self
    {
        $at ??= new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $milliseconds = (int) $at->format('Uv');

        if ($milliseconds < 0) {
            throw new \InvalidArgumentException(
                'UUIDv7 cannot encode an instant before the Unix epoch: its timestamp field is unsigned.',
            );
        }

        if ($milliseconds > self::MAX_TIMESTAMP_MS) {
            throw new \InvalidArgumentException(
                'UUIDv7 cannot encode an instant beyond the 48-bit millisecond field (year 10889).',
            );
        }

        $bytes = substr(pack('J', $milliseconds), -self::TIMESTAMP_BYTES) . random_bytes(10);

        // version 7 in the high nibble of byte 6, variant b10 in the top bits of byte 8
        $bytes[6] = \chr((\ord($bytes[6]) & 0x0F) | 0x70);
        $bytes[8] = \chr((\ord($bytes[8]) & 0x3F) | 0x80);

        return new self($bytes);
    }

    /**
     * Accepts either case (RFC 9562 §4) but only the canonical dashed form:
     * braced and `urn:` variants are rejected so one value has one spelling.
     *
     * @throws \InvalidArgumentException on any other input
     */
    #[\NoDiscard]
    public static function fromString(string $value): self
    {
        if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$/D', $value) !== 1) {
            throw new \InvalidArgumentException(\sprintf(
                'Malformed UUID: %s',
                var_export($value, true),
            ));
        }

        return new self(pack('H*', str_replace('-', '', strtolower($value))));
    }

    /**
     * @throws \InvalidArgumentException unless exactly 16 bytes
     */
    #[\NoDiscard]
    public static function fromBytes(string $bytes): self
    {
        if (\strlen($bytes) !== 16) {
            throw new \InvalidArgumentException(\sprintf(
                'A UUID is exactly 16 bytes, got %d.',
                \strlen($bytes),
            ));
        }

        return new self($bytes);
    }

    public function toString(): string
    {
        $hex = bin2hex($this->bytes);

        return substr($hex, 0, 8) . '-'
            . substr($hex, 8, 4) . '-'
            . substr($hex, 12, 4) . '-'
            . substr($hex, 16, 4) . '-'
            . substr($hex, 20, 12);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->toString();
    }

    public function toBytes(): string
    {
        return $this->bytes;
    }

    /**
     * @return int<0, 15>
     */
    public function version(): int
    {
        /** @var int<0, 15> */
        return (\ord($this->bytes[6]) & 0xF0) >> 4;
    }

    /**
     * The variant field's leading bits: `2` is the RFC 9562 variant (`b10x`).
     *
     * @return int<0, 3>
     */
    public function variant(): int
    {
        /** @var int<0, 3> */
        return (\ord($this->bytes[8]) & 0xC0) >> 6;
    }

    /**
     * The embedded creation instant, or `null` when this is not a version 7
     * identifier and therefore carries no timestamp.
     */
    public function createdAt(): ?\DateTimeImmutable
    {
        if ($this->version() !== 7) {
            return null;
        }

        $milliseconds = 0;
        for ($byte = 0; $byte < self::TIMESTAMP_BYTES; ++$byte) {
            $milliseconds = ($milliseconds << 8) | \ord($this->bytes[$byte]);
        }

        return intdiv($milliseconds, 1000)
            |> (fn($x) => \sprintf('%d.%03d', $x, $milliseconds % 1000))
            |> (fn($x) => \DateTimeImmutable::createFromFormat('U.v', $x, new \DateTimeZone('UTC'), )) ?: null;
    }

    public function equals(self $other): bool
    {
        return $this->bytes === $other->bytes;
    }

    /**
     * Byte order, which for version 7 is also creation order.
     *
     * @param Uuid $other
     * @return int -1|0|1
     */
    public function compareTo(self $other): int
    {
        return strcmp($this->bytes, $other->bytes) <=> 0;
    }
}
