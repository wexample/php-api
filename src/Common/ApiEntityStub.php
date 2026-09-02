<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

class ApiEntityStub extends AbstractApiEntity
{
    public function __construct(
        protected string $targetName,
        ?string $id,
    ) {
        parent::__construct(
            id: $id
        );
    }

    public static function fromArray(array $data): static
    {
        return new self(
            targetName: (string) ($data['entityName'] ?? $data['target'] ?? ''),
            id: isset($data['id']) ? (string) $data['id'] : null
        );
    }

    public function isStub(): bool
    {
        return true;
    }

    public function getTargetName(): string
    {
        return $this->targetName;
    }
}
