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
        $id = $entity->getId();
        if (! is_string($id) || $id === '') {
            return;
        }

        $entityName = $this->normalizeName($entity::getEntityName());
        $this->entities[$entityName][$id] = $entity;

        $waiting = $this->stubs[$entityName][$id] ?? null;
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

        unset($this->stubs[$entityName][$id]);
    }

    public function registerStub(AbstractApiEntity $owner, ApiEntityStub $stub): void
    {
        $id = $stub->getId();
        if (! is_string($id) || $id === '') {
            return;
        }

        $entityName = $this->normalizeName($stub->getTargetName());

        $existing = $this->entities[$entityName][$id] ?? null;
        if ($existing instanceof AbstractApiEntity) {
            $owner->replaceRelationship($stub, $existing);

            return;
        }

        $this->stubs[$entityName][$id][] = [
            'owner' => WeakReference::create($owner),
            'stub' => $stub,
        ];
    }

    public function resolve(string $entityName, string $id): ?AbstractApiEntity
    {
        $entityName = $this->normalizeName($entityName);

        return $this->entities[$entityName][$id] ?? null;
    }

    private function normalizeName(string $name): string
    {
        $snake = preg_replace('/(?<!^)[A-Z]/', '_$0', $name) ?? $name;

        return strtolower($snake);
    }
}
