<?php

declare(strict_types=1);

namespace Contempt\Core;

/**
 * The deployment environment.
 *
 * Debug output and implicit `.env` loading are derived from this enum rather
 * than from independent flags, so they cannot be enabled in production by a
 * forgotten switch.
 */
enum Environment: string
{
    case Development = 'dev';
    case Test = 'test';
    case Production = 'prod';

    /**
     * Accepts the spellings deployments actually contain. Recognition is
     * case- and whitespace-insensitive because `APP_ENV=Production` must not
     * silently fall through to a debug environment; anything unrecognised is
     * refused rather than defaulted.
     *
     * @throws \InvalidArgumentException on any other value
     */
    public static function fromString(string $raw): self
    {
        return match (strtolower(trim($raw))) {
            'dev', 'develop', 'development', 'local' => self::Development,
            'test', 'testing' => self::Test,
            'prod', 'production' => self::Production,
            default => throw new \InvalidArgumentException(\sprintf(
                'Unknown environment "%s". Expected one of: dev, test, prod.',
                $raw,
            )),
        };
    }

    public function isProduction(): bool
    {
        return $this === self::Production;
    }

    /**
     * Whether detailed error output, stack traces and debug services are
     * permitted (contempt.md §110).
     */
    public function allowsDebug(): bool
    {
        return $this !== self::Production;
    }

    /**
     * Whether a `.env` file may be loaded without being explicitly requested
     * (architecture.md §31).
     */
    public function allowsImplicitDotEnv(): bool
    {
        return $this !== self::Production;
    }
}
