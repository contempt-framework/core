<?php

declare(strict_types=1);

namespace Contempt\Core\Secret;

/**
 * A capability naming who is revealing a secret and why.
 *
 * Two purposes: it makes every reveal greppable and reviewable at the call
 * site, and it lets a {@see Secret} that was hydrated for one component refuse
 * to be read by another.
 */
final readonly class SecretAccess
{
    private function __construct(
        public string $component,
        public string $purpose,
    ) {}

    /**
     * @param string $component the infrastructure component revealing the value,
     *                          matched exactly against the secret's allow-list
     * @param string $purpose   why it is being revealed, for review and audit
     *
     * @throws \InvalidArgumentException when either argument is blank
     */
    #[\NoDiscard]
    public static function for(string $component, string $purpose): self
    {
        if (trim($component) === '') {
            throw new \InvalidArgumentException('Secret access requires a non-blank component name.');
        }

        if (trim($purpose) === '') {
            throw new \InvalidArgumentException('Secret access requires a non-blank purpose.');
        }

        return new self($component, $purpose);
    }
}
