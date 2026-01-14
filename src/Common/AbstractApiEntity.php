<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use BadMethodCallException;
use ReflectionClass;

abstract class AbstractApiEntity
{
    public function __construct(
        protected ?string $secureId = null,
        protected array $metadata = [],
        protected array $relationships = [],
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

    public function getRelationships(): array
    {
        return $this->relationships;
    }

    public function setRelationships(array $relationships): void
    {
        $this->relationships = $relationships;
    }

    public function __call(string $name, array $arguments): mixed
    {
        if (preg_match('/^get(.+)SecureId$/', $name, $matches) === 1) {
            $property = lcfirst($matches[1]) . 'SecureId';

            if (property_exists($this, $property)) {
                return $this->$property;
            }

            return null;
        }

        if (preg_match('/^get(.+)Relationship$/', $name, $matches) === 1) {
            return $this->findRelationshipByName($matches[1]);
        }

        throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $name));
    }

    protected function findRelationshipByName(string $name): ?AbstractApiEntity
    {
        $normalizedTarget = $this->normalizeRelationshipName($name);

        foreach ($this->relationships as $relationship) {
            if (! $relationship instanceof AbstractApiEntity) {
                continue;
            }

            $shortName = (new ReflectionClass($relationship))->getShortName();

            if (strcasecmp($shortName, $name) === 0) {
                return $relationship;
            }

            $entityName = $relationship::getEntityName();

            if ($this->normalizeRelationshipName($entityName) === $normalizedTarget) {
                return $relationship;
            }
        }

        return null;
    }

    protected function normalizeRelationshipName(string $name): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name;

        return strtolower($snake);
    }
}
