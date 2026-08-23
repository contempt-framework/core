<?php

declare(strict_types=1);

namespace Contempt\Core\Tests;

use Contempt\Core\Environment;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Environment::class)]
final class EnvironmentTest extends TestCase
{
    /**
     * `APP_ENV=Production` and `APP_ENV=prod ` are what deployments actually
     * contain. Failing to recognise them would silently enable debug output in
     * production, so recognition is case- and whitespace-insensitive — while an
     * unknown value is refused rather than defaulted.
     */
    #[DataProvider('recognisedSpellings')]
    public function testDeploymentSpellingsAreRecognised(string $raw, Environment $expected): void
    {
        self::assertSame($expected, Environment::fromString($raw));
    }

    /**
     * @return iterable<string, array{string, Environment}>
     */
    public static function recognisedSpellings(): iterable
    {
        yield 'prod' => ['prod', Environment::Production];
        yield 'production' => ['production', Environment::Production];
        yield 'PRODUCTION' => ['PRODUCTION', Environment::Production];
        yield 'padded' => ["  prod\t", Environment::Production];
        yield 'dev' => ['dev', Environment::Development];
        yield 'development' => ['development', Environment::Development];
        yield 'test' => ['test', Environment::Test];
        yield 'testing' => ['testing', Environment::Test];
    }

    #[DataProvider('unrecognisedSpellings')]
    public function testUnknownEnvironmentsAreRefusedRatherThanDefaulted(string $raw): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown environment');

        Environment::fromString($raw);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function unrecognisedSpellings(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => ['   '];
        yield 'staging' => ['staging'];
        yield 'typo' => ['produciton'];
        yield 'prod with suffix' => ['prod-eu'];
    }

    /**
     * Debug output and .env loading are the two settings whose accidental
     * activation in production is a security incident, so both are derived from
     * the enum rather than from separate flags.
     */
    public function testProductionForbidsDebugAndImplicitDotEnv(): void
    {
        self::assertFalse(Environment::Production->allowsDebug());
        self::assertFalse(Environment::Production->allowsImplicitDotEnv());
        self::assertTrue(Environment::Production->isProduction());
    }

    #[DataProvider('nonProductionEnvironments')]
    public function testNonProductionEnvironmentsPermitDeveloperConveniences(Environment $environment): void
    {
        self::assertTrue($environment->allowsDebug());
        self::assertTrue($environment->allowsImplicitDotEnv());
        self::assertFalse($environment->isProduction());
    }

    /**
     * @return iterable<string, array{Environment}>
     */
    public static function nonProductionEnvironments(): iterable
    {
        yield 'development' => [Environment::Development];
        yield 'test' => [Environment::Test];
    }

    public function testCanonicalValuesAreFrozen(): void
    {
        self::assertSame(
            ['dev', 'test', 'prod'],
            array_map(static fn(Environment $e): string => $e->value, Environment::cases()),
        );
    }
}
