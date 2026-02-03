<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

class ApiEntityStub extends AbstractApiEntity
{
    public function __construct(
        protected string $targetName,
        ?string $secureId,
    ) {
        parent::__construct(
            secureId: $secureId
        );
    }

    public static function fromArray(array $data): static
    {
        return new self(
            targetName: (string) ($data['entityName'] ?? $data['target'] ?? ''),
            secureId: isset($data['secureId']) ? (string) $data['secureId'] : null
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
