<?php

declare(strict_types=1);

namespace Contempt\Core\Tests\Secret;

use Contempt\Core\Exception\SecurityException;
use Contempt\Core\Secret\Secret;
use Contempt\Core\Secret\SecretAccess;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(Secret::class)]
#[CoversClass(SecretAccess::class)]
final class SecretTest extends TestCase
{
    private const string VALUE = 'pa$$w0rd-with-\'quotes\'-and-"doubles"';

    /**
     * Every route by which a value normally escapes into a log, a debug dump or
     * an exception context must yield the placeholder instead.
     */
    public function testStringConversionIsRedacted(): void
    {
        $secret = new Secret(self::VALUE);

        self::assertSame('[REDACTED]', (string) $secret);
        self::assertSame('[REDACTED]', \sprintf('%s', $secret));
        self::assertStringNotContainsString('pa$$w0rd', "{$secret}");
    }

    public function testJsonEncodingIsRedacted(): void
    {
        $encoded = json_encode(['password' => new Secret(self::VALUE)], JSON_THROW_ON_ERROR);

        self::assertSame('{"password":"[REDACTED]"}', $encoded);
    }

    public function testVarDumpIsRedacted(): void
    {
        ob_start();
        var_dump(new Secret(self::VALUE));
        $dump = (string) ob_get_clean();

        self::assertStringNotContainsString('pa$$w0rd', $dump);
        self::assertStringContainsString('[REDACTED]', $dump);
    }

    public function testPrintRIsRedacted(): void
    {
        self::assertStringNotContainsString('pa$$w0rd', print_r(new Secret(self::VALUE), true));
    }

    /**
     * An exception context is the most common accidental disclosure path: the
     * message is rendered by the logger, not inspected by a human first.
     */
    public function testExceptionContextIsRedacted(): void
    {
        $error = new \RuntimeException(\sprintf('connect failed for %s', new Secret(self::VALUE)));

        self::assertStringNotContainsString('pa$$w0rd', $error->getMessage());
        self::assertStringNotContainsString('pa$$w0rd', $error->getTraceAsString());
    }

    /**
     * Serialization would write the plaintext into a session, a cache entry or
     * a queue payload. There is no safe redacted form either, because the value
     * would silently become the placeholder on unserialize.
     */
    public function testSerializationIsRefused(): void
    {
        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage('Secret must not be serialized');

        serialize(new Secret(self::VALUE));
    }

    public function testRevealReturnsTheExactBytes(): void
    {
        $secret = new Secret(self::VALUE);

        self::assertSame(self::VALUE, $secret->reveal(SecretAccess::for('test', 'assert round-trip')));
    }

    /**
     * Binary and empty values must survive intact: DSNs and keys are not
     * guaranteed to be printable.
     */
    #[DataProvider('awkwardValues')]
    public function testAwkwardValuesRoundTrip(string $value): void
    {
        $secret = new Secret($value);

        self::assertSame($value, $secret->reveal(SecretAccess::for('test', 'round-trip')));
        self::assertSame('[REDACTED]', (string) $secret);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function awkwardValues(): iterable
    {
        yield 'empty' => [''];
        yield 'null byte' => ["a\0b"];
        yield 'newlines' => ["line1\nline2"];
        yield 'utf-8' => ['hasło-żółć-🔐'];
        yield 'invalid utf-8' => ["\xC3\x28"];
        yield 'the placeholder itself' => ['[REDACTED]'];
        yield 'long' => [str_repeat('x', 100_000)];
    }

    /**
     * Comparison must not leak length or content through timing, and must never
     * be reachable by comparing the redacted string forms — which would report
     * every secret as equal to every other.
     */
    public function testEqualityComparesRevealedValuesNotPlaceholders(): void
    {
        $a = new Secret('alpha');
        $b = new Secret('beta');

        self::assertFalse($a->equals($b));
        self::assertTrue($a->equals(new Secret('alpha')));
        self::assertSame((string) $a, (string) $b, 'placeholders are indistinguishable');
    }

    public function testEmptinessIsObservableWithoutRevealing(): void
    {
        self::assertTrue(new Secret('')->isEmpty());
        self::assertFalse(new Secret('0')->isEmpty(), '"0" is a valid secret, not emptiness');
        self::assertFalse(new Secret(' ')->isEmpty());
    }

    #[DataProvider('invalidAccessArguments')]
    public function testAccessRequiresANamedComponentAndPurpose(string $component, string $purpose): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::fail('expected a refusal, produced access for ' . SecretAccess::for($component, $purpose)->component);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidAccessArguments(): iterable
    {
        yield 'empty component' => ['', 'purpose'];
        yield 'blank component' => ["  \t", 'purpose'];
        yield 'empty purpose' => ['component', ''];
        yield 'blank purpose' => ['component', "\n"];
    }
    /**
     * A secret hydrated for one configuration prefix must not be readable by an
     * unrelated component. This is what makes {@see SecretAccess} a capability
     * rather than ceremony.
     */
    public function testRestrictedSecretRefusesForeignComponents(): void
    {
        $secret = new Secret(self::VALUE, 'database');

        $this->expectException(SecurityException::class);
        $this->expectExceptionMessage("Component 'mailer' may not reveal this secret");

        self::assertNotSame('', $secret->reveal(SecretAccess::for('mailer', 'send credentials')));
    }

    public function testRestrictedSecretAllowsItsOwnComponents(): void
    {
        $secret = new Secret(self::VALUE, 'database', 'migrations');

        self::assertSame(self::VALUE, $secret->reveal(SecretAccess::for('database', 'connect')));
        self::assertSame(self::VALUE, $secret->reveal(SecretAccess::for('migrations', 'connect')));
    }

    public function testComponentMatchingIsExactAndCaseSensitive(): void
    {
        $secret = new Secret(self::VALUE, 'database');

        $this->expectException(SecurityException::class);

        self::assertNotSame('', $secret->reveal(SecretAccess::for('Database', 'connect')));
    }

    public function testUnrestrictedSecretAcceptsAnyComponent(): void
    {
        $secret = new Secret(self::VALUE);

        self::assertSame(self::VALUE, $secret->reveal(SecretAccess::for('anything', 'any purpose')));
    }

    public function testRefusalDoesNotDiscloseTheValueOrTheAllowedComponents(): void
    {
        try {
            self::assertNotSame('', new Secret(self::VALUE, 'database')->reveal(SecretAccess::for('mailer', 'x')));
            self::fail('expected refusal');
        } catch (SecurityException $error) {
            self::assertStringNotContainsString('pa$$w0rd', $error->getMessage());
            self::assertStringNotContainsString('database', $error->getMessage());
        }
    }
}
