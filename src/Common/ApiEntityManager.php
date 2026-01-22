<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use InvalidArgumentException;

final class ApiEntityManager
{
    /**
     * @var array<string, array{entity: class-string<AbstractApiEntity>, repository: class-string<AbstractApiRepository>, instance: AbstractApiRepository|null}>
     */
    private array $registry = [];

    /**
     * @param class-string<AbstractApiRepository>[] $repositories
     */
    public function __construct(
        private AbstractApiEntitiesClient $client,
        array $repositories,
    ) {
        $this->buildRegistry($repositories);
    }

    /**
     * @param string|class-string<AbstractApiEntity> $entity
     */
    public function get(string $entity): AbstractApiRepository
    {
        $entityName = $this->resolveEntityName($entity);

        if (! isset($this->registry[$entityName])) {
            throw new InvalidArgumentException(sprintf(
                'Entity %s is not registered. Available repositories: %s',
                $entityName,
                implode(', ', array_keys($this->registry))
            ));
        }

        $entry = $this->registry[$entityName];

        if (! $entry['instance']) {
            $repositoryClass = $entry['repository'];
            $entry['instance'] = new $repositoryClass($this->client);
            $this->registry[$entityName] = $entry;
        }

        return $entry['instance'];
    }

    /**
     * @return array<string, AbstractApiRepository>
     */
    public function all(): array
    {
        $instances = [];

        foreach (array_keys($this->registry) as $entityName) {
            $instances[$entityName] = $this->get($entityName);
        }

        return $instances;
    }

    /**
     * @param class-string<AbstractApiRepository>[] $repositories
     */
    private function buildRegistry(array $repositories): void
    {
        $this->registry = [];

        foreach ($repositories as $repositoryClass) {
            $entityType = $repositoryClass::getEntityType();

            if (! is_a($entityType, AbstractApiEntity::class, true)) {
                throw new InvalidArgumentException('Repository entity type must extend AbstractApiEntity.');
            }

            $entityName = $entityType::getEntityName();

            $this->registry[$entityName] = [
                'repository' => $repositoryClass,
                'entity' => $entityType,
                'instance' => null,
            ];
        }
    }

    /**
     * @param string|class-string<AbstractApiEntity> $entity
     */
    private function resolveEntityName(string $entity): string
    {
        if (is_a($entity, AbstractApiEntity::class, true)) {
            return $entity::getEntityName();
        }

        return $entity;
    }
}
