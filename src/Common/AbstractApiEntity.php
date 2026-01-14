<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

abstract class AbstractApiEntity
{
    public function __construct(
        protected ?string $secureId = null,
        protected array $metadata = [],
    ) {
    }

    abstract public static function fromArray(array $data): static;

    /**
     * @return static[]
     */
    public static function fromArrayCollection(array $collection): array
    {
        $output = [];

        foreach ($collection as $data) {
            $output[] = static::fromArray($data);
        }

        return $output;
    }

    public static function getEntityName(): string
    {
        return static::class;
    }

    public function getSecureId(): ?string
    {
        return $this->secureId;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setMetadata(array $metadata): void
    {
        $this->metadata = $metadata;
    }
}
