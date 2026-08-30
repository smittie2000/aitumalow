<?php

declare(strict_types=1);

namespace Aitumalow\DTOs;

final readonly class ExecutionScope
{
    public function __construct(
        public ?string $tenantReference = null,
        public ?string $principalReference = null,
    ) {}

    /** @return array{tenant_reference: string|null, principal_reference: string|null} */
    public function toArray(): array
    {
        return [
            'tenant_reference' => $this->tenantReference,
            'principal_reference' => $this->principalReference,
        ];
    }

    /** @param array<string, mixed>|null $scope */
    public static function fromArray(?array $scope): self
    {
        return new self(
            tenantReference: self::stringOrNull($scope['tenant_reference'] ?? null),
            principalReference: self::stringOrNull($scope['principal_reference'] ?? null),
        );
    }

    private static function stringOrNull(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
