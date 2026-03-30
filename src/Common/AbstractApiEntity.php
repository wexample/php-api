<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use BadMethodCallException;
use ReflectionClass;
use Wexample\Helpers\Class\Traits\HasSnakeShortClassNameClassTrait;
use Wexample\Helpers\Helper\ClassHelper;

abstract class AbstractApiEntity
{
    use HasSnakeShortClassNameClassTrait;

    public function __construct(
        protected ?string $secureId = null,
        protected array $metadata = [],
        protected array $relationships = [],
        protected array $values = [],
        protected array $relationshipMap = [],
    ) {
    }

    public static function fromArray(array $data): static
    {
        return new static();
    }

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

    public function setRelationshipMap(array $relationshipMap): void
    {
        $this->relationshipMap = $relationshipMap;
    }

    public function getRelationshipMap(): array
    {
        return $this->relationshipMap;
    }

    public function isStub(): bool
    {
        return false;
    }

    public function replaceRelationship(ApiEntityStub $stub, AbstractApiEntity $entity): void
    {
        foreach ($this->relationships as $index => $relationship) {
            if (! $relationship instanceof AbstractApiEntity) {
                continue;
            }

            if ($relationship === $stub) {
                $this->relationships[$index] = $entity;

                continue;
            }

            if ($relationship instanceof ApiEntityStub) {
                if (
                    $relationship->getSecureId() === $stub->getSecureId()
                    && $this->normalizeRelationshipName($relationship->getTargetName())
                        === $this->normalizeRelationshipName($stub->getTargetName())
                ) {
                    $this->relationships[$index] = $entity;
                }
            }
        }
    }

    public function __call(
        string $name,
        array $arguments
    ): mixed {
        if (preg_match('/^get(.+)$/', $name, $matches) === 1) {
            $property = lcfirst($matches[1]);
            if (array_key_exists($property, $this->values)) {
                return $this->values[$property];
            }

            if (array_key_exists($property, $this->relationshipMap)) {
                return $this->resolveRelationshipMapValue($this->relationshipMap[$property] ?? null);
            }

            $relationships = $this->findRelationshipsByName($matches[1]);
            if ($relationships !== []) {
                return count($relationships) === 1 ? $relationships[0] : $relationships;
            }

            $relationship = $this->findRelationshipByName($matches[1]);
            if ($relationship !== null) {
                return $relationship;
            }
        }

        if (preg_match('/^set(.+)$/', $name, $matches) === 1) {
            $property = lcfirst($matches[1]);
            $this->values[$property] = $arguments[0] ?? null;

            return $this;
        }

        if (array_key_exists($name, $this->values)) {
            return $this->values[$name];
        }

        throw new BadMethodCallException(sprintf('Method %s::%s does not exist.', static::class, $name));
    }

    public function __get(string $name): mixed
    {
        if (array_key_exists($name, $this->values)) {
            return $this->values[$name];
        }

        if (array_key_exists($name, $this->relationshipMap)) {
            return $this->resolveRelationshipMapValue($this->relationshipMap[$name] ?? null);
        }

        $relationships = $this->findRelationshipsByName($name);
        if ($relationships !== []) {
            return count($relationships) === 1 ? $relationships[0] : $relationships;
        }

        $relationship = $this->findRelationshipByName($name);
        if ($relationship !== null) {
            return $relationship;
        }

        if (property_exists($this, $name)) {
            return $this->$name;
        }

        $getter = ClassHelper::buildFieldGetterName($name);
        if (method_exists($this, $getter)) {
            return $this->$getter();
        }

        return null;
    }

    public function retrieveValue(string $name): mixed
    {
        return $this->values[$name] ?? null;
    }

    public function retrieveMetadata(string $name): mixed
    {
        return $this->metadata[$name] ?? null;
    }

    public function assignValue(string $name, mixed $value): self
    {
        $this->values[$name] = $value;

        return $this;
    }

    public function getValues(): array
    {
        return $this->values;
    }

    protected function findRelationshipByName(string $name): ?AbstractApiEntity
    {
        $normalizedTarget = $this->normalizeRelationshipName($name);

        foreach ($this->relationships as $relationship) {
            if (! $relationship instanceof AbstractApiEntity) {
                continue;
            }

            if ($relationship instanceof ApiEntityStub) {
                if ($this->normalizeRelationshipName($relationship->getTargetName()) === $normalizedTarget) {
                    return $relationship;
                }

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

    /**
     * @return AbstractApiEntity[]
     */
    protected function findRelationshipsByName(string $name): array
    {
        $normalizedTarget = $this->normalizeRelationshipName($name);
        $matches = [];

        foreach ($this->relationships as $relationship) {
            if (! $relationship instanceof AbstractApiEntity) {
                continue;
            }

            if ($relationship instanceof ApiEntityStub) {
                if ($this->normalizeRelationshipName($relationship->getTargetName()) === $normalizedTarget) {
                    $matches[] = $relationship;
                }

                continue;
            }

            $shortName = (new ReflectionClass($relationship))->getShortName();
            if (strcasecmp($shortName, $name) === 0) {
                $matches[] = $relationship;

                continue;
            }

            $entityName = $relationship::getEntityName();
            if ($this->normalizeRelationshipName($entityName) === $normalizedTarget) {
                $matches[] = $relationship;
            }
        }

        return $matches;
    }

    protected function normalizeRelationshipName(string $name): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name;

        return strtolower($snake);
    }

    protected function resolveRelationshipMapValue(mixed $entry): mixed
    {
        if (! is_array($entry)) {
            return null;
        }

        $type = $entry['type'] ?? null;
        $items = is_array($entry['items'] ?? null) ? $entry['items'] : [];

        if ($type === 'relation') {
            return $items[0] ?? null;
        }

        if ($type === 'collection') {
            return $items;
        }

        return null;
    }
}
