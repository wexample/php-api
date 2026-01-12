<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use InvalidArgumentException;

abstract class AbstractApiRepository
{
    public function __construct(
        protected AbstractApiEntitiesClient $client,
    ) {
    }

    /**
     * @return class-string<AbstractApiEntity>
     */
    abstract public static function getEntityType(): string;

    public static function getEntityName(): string
    {
        $entityType = static::getEntityType();

        if (! is_a($entityType, AbstractApiEntity::class, true)) {
            throw new InvalidArgumentException('Repository entity type must extend AbstractApiEntity.');
        }

        return $entityType::getEntityName();
    }

    /**
     * @return AbstractApiEntity
     */
    protected function createFromApiItem(array $data): AbstractApiEntity
    {
        $entityType = static::getEntityType();

        return $entityType::fromArray($data);
    }

    /**
     * @return AbstractApiEntity[]
     */
    protected function createFromApiCollection(array $collection): array
    {
        $output = [];

        foreach ($collection as $data) {
            $output[] = $this->createFromApiItem($data);
        }

        return $output;
    }

    protected function buildPath(string $pathSuffix): string
    {
        return static::getEntityName() . '/' . ltrim($pathSuffix, '/');
    }
}
