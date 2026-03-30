<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use WeakReference;

class ApiEntityRegistry
{
    /**
     * @var array<string, array<string, AbstractApiEntity>>
     */
    private array $entities = [];

    /**
     * @var array<string, array<string, array<int, array{owner: WeakReference, stub: ApiEntityStub}>>>
     */
    private array $stubs = [];

    public function registerEntity(AbstractApiEntity $entity): void
    {
        $secureId = $entity->getSecureId();
        if (! is_string($secureId) || $secureId === '') {
            return;
        }

        $entityName = $this->normalizeName($entity::getEntityName());
        $this->entities[$entityName][$secureId] = $entity;

        $waiting = $this->stubs[$entityName][$secureId] ?? null;
        if (! is_array($waiting)) {
            return;
        }

        foreach ($waiting as $entry) {
            $owner = $entry['owner']->get();
            if (! $owner instanceof AbstractApiEntity) {
                continue;
            }

            $owner->replaceRelationship($entry['stub'], $entity);
        }

        unset($this->stubs[$entityName][$secureId]);
    }

    public function registerStub(AbstractApiEntity $owner, ApiEntityStub $stub): void
    {
        $secureId = $stub->getSecureId();
        if (! is_string($secureId) || $secureId === '') {
            return;
        }

        $entityName = $this->normalizeName($stub->getTargetName());

        $existing = $this->entities[$entityName][$secureId] ?? null;
        if ($existing instanceof AbstractApiEntity) {
            $owner->replaceRelationship($stub, $existing);

            return;
        }

        $this->stubs[$entityName][$secureId][] = [
            'owner' => WeakReference::create($owner),
            'stub' => $stub,
        ];
    }

    public function resolve(string $entityName, string $secureId): ?AbstractApiEntity
    {
        $entityName = $this->normalizeName($entityName);

        return $this->entities[$entityName][$secureId] ?? null;
    }

    private function normalizeName(string $name): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name;

        return strtolower($snake);
    }
}
