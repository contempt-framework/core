<?php

declare(strict_types=1);

namespace Contempt\Core;

/**
 * Where a declaration was found.
 *
 * Every diagnostic carries one: an error about a route collision is only
 * actionable if it names both declarations. Paths are stored relative to the
 * project root so build artifacts stay reproducible across machines
 * (architecture.md §45).
 */
final readonly class SourceLocation implements \Stringable
{
    /**
     * @param string $file project-relative path
     * @param int<0, max> $line 1-based; `0` when the position is unknown
     *
     * @throws \InvalidArgumentException on an absent file or a negative line
     */
    public function __construct(
        public string $file,
        public int $line = 0,
    ) {
        if (trim($file) === '') {
            throw new \InvalidArgumentException('A source location requires a file.');
        }

        if ($line < 0) {
            throw new \InvalidArgumentException(\sprintf('A source line must not be negative, got %d.', $line));
        }
    }

    /**
     * @param int<0, max> $line
     *
     * @throws \InvalidArgumentException when the file lies outside the root
     */
    #[\NoDiscard]
    public static function relativeTo(string $projectRoot, string $file, int $line = 0): self
    {
        $root = rtrim(str_replace('\\', '/', $projectRoot), '/') . '/';
        $normalised = str_replace('\\', '/', $file);

        if (!str_starts_with($normalised, $root)) {
            throw new \InvalidArgumentException(\sprintf(
                'The file "%s" is outside the project root "%s".',
                $file,
                $projectRoot,
            ));
        }

        return new self(substr($normalised, \strlen($root)), $line);
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->line > 0 ? $this->file . ':' . $this->line : $this->file;
    }

    public function equals(self $other): bool
    {
        return $this->file === $other->file && $this->line === $other->line;
    }
}
