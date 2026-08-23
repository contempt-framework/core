<?php

declare(strict_types=1);

namespace Contempt\Core\Time;

/**
 * A non-negative span of time with millisecond resolution.
 *
 * Arithmetic saturates at `PHP_INT_MAX` rather than wrapping: an overflowing
 * exponential backoff must become "very long", never "negative", which would
 * turn a retry policy into a busy loop.
 */
final readonly class Duration
{
    private function __construct(private int $milliseconds) {}

    /**
     * @throws \InvalidArgumentException when negative
     */
    #[\NoDiscard]
    public static function milliseconds(int $milliseconds): self
    {
        if ($milliseconds < 0) {
            throw new \InvalidArgumentException(\sprintf(
                'A duration must not be negative, got %d ms.',
                $milliseconds,
            ));
        }

        return new self($milliseconds);
    }

    /**
     * @throws \InvalidArgumentException when negative or when the millisecond
     *                                   conversion would overflow
     */
    #[\NoDiscard]
    public static function seconds(int $seconds): self
    {
        if ($seconds > intdiv(PHP_INT_MAX, 1000)) {
            throw new \InvalidArgumentException(\sprintf(
                'Duration overflow: %d seconds cannot be represented in milliseconds.',
                $seconds,
            ));
        }

        return self::milliseconds($seconds * 1000);
    }

    #[\NoDiscard]
    public static function zero(): self
    {
        return new self(0);
    }

    public function toMilliseconds(): int
    {
        return $this->milliseconds;
    }

    /**
     * Saturates, because `usleep()` and timeout options take microseconds and
     * a wrapped negative value would be interpreted as "no wait".
     */
    public function toMicroseconds(): int
    {
        if ($this->milliseconds > intdiv(PHP_INT_MAX, 1000)) {
            return PHP_INT_MAX;
        }

        return $this->milliseconds * 1000;
    }

    public function toSeconds(): float
    {
        return $this->milliseconds / 1000;
    }

    public function isZero(): bool
    {
        return $this->milliseconds === 0;
    }

    /**
     * @throws \InvalidArgumentException when the multiplier is negative, NAN or
     *                                   infinite
     */
    #[\NoDiscard('Durations are immutable; the scaled value is the result.')]
    public function multipliedBy(float $multiplier): self
    {
        if (!is_finite($multiplier) || $multiplier < 0.0) {
            throw new \InvalidArgumentException(\sprintf(
                'A duration multiplier must be finite and non-negative, got %s.',
                var_export($multiplier, true),
            ));
        }

        $product = $this->milliseconds * $multiplier;

        if ($product >= (float) PHP_INT_MAX) {
            return new self(PHP_INT_MAX);
        }

        return new self((int) $product);
    }

    #[\NoDiscard('Durations are immutable; the sum is the result.')]
    public function plus(self $other): self
    {
        if ($other->milliseconds > PHP_INT_MAX - $this->milliseconds) {
            return new self(PHP_INT_MAX);
        }

        return new self($this->milliseconds + $other->milliseconds);
    }

    /**
     * @return -1|0|1
     */
    public function compareTo(self $other): int
    {
        return $this->milliseconds <=> $other->milliseconds;
    }

    public function equals(self $other): bool
    {
        return $this->milliseconds === $other->milliseconds;
    }
}
