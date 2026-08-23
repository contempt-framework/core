<?php

declare(strict_types=1);

namespace Contempt\Core\Secret;

use Contempt\Core\Exception\SecurityException;

/**
 * A value that must not appear in logs, dumps or exception contexts.
 *
 * This prevents *accidental* disclosure. It is not in-process memory secrecy:
 * a compromised PHP process, a debugger, or `var_export()` — which ignores
 * magic methods by design — can still read the value. Claiming otherwise would
 * be a false guarantee (architecture.md §32).
 */
final readonly class Secret implements \JsonSerializable, \Stringable
{
    public const string PLACEHOLDER = '[REDACTED]';

    private string $value;

    /** @var list<string> empty means unrestricted */
    private array $allowedComponents;

    /**
     * @param string $value
     * @param string ...$allowedComponents components permitted to reveal this
     *                                  value; omit to allow any component
     */
    public function __construct(string $value, string ...$allowedComponents)
    {
        $this->value = $value;
        $this->allowedComponents = array_values($allowedComponents);
    }

    #[\Override]
    public function __toString(): string
    {
        return self::PLACEHOLDER;
    }

    #[\Override]
    public function jsonSerialize(): string
    {
        return self::PLACEHOLDER;
    }

    /**
     * @return array<string, string>
     */
    public function __debugInfo(): array
    {
        return ['value' => self::PLACEHOLDER];
    }

    /**
     * @return never
     *
     * @throws SecurityException always
     */
    public function __serialize(): array
    {
        throw new SecurityException(
            'Secret must not be serialized: it would write plaintext into a session, cache entry or queue payload.',
        );
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return never
     *
     * @throws SecurityException always
     */
    public function __unserialize(array $data): void
    {
        throw new SecurityException('Secret must not be unserialized.');
    }

    /**
     * @throws SecurityException when the requesting component is outside this
     *                           secret's allow-list
     */
    #[\NoDiscard('Revealing a secret without using it defeats the purpose of the capability.')]
    public function reveal(SecretAccess $access): string
    {
        if ($this->allowedComponents !== [] && !\in_array($access->component, $this->allowedComponents, true)) {
            // Neither the value nor the allow-list may appear in the message.
            throw new SecurityException(\sprintf(
                "Component '%s' may not reveal this secret (purpose: %s).",
                $access->component,
                $access->purpose,
            ));
        }

        return $this->value;
    }

    /**
     * Timing-safe comparison. Length is still observable, as it is for every
     * constant-time string comparison in PHP.
     */
    public function equals(self $other): bool
    {
        return hash_equals($this->value, $other->value);
    }

    /**
     * Lets configuration validation reject a blank secret without revealing it.
     */
    public function isEmpty(): bool
    {
        return $this->value === '';
    }
}
