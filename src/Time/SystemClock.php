<?php

declare(strict_types=1);

namespace Contempt\Core\Time;

use Psr\Clock\ClockInterface;

/**
 * The wall clock, pinned to UTC.
 *
 * Application code depends on {@see ClockInterface}, never on this class, so
 * tests can substitute a deterministic clock (contempt.md §165). UTC is not
 * configurable: an ambiguous local timestamp in message metadata cannot be
 * disambiguated after the fact (contempt.md §167).
 */
final readonly class SystemClock implements ClockInterface
{
    #[\Override]
    public function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
    }
}
