<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use GuzzleHttp\ClientInterface;

abstract class AbstractApiEntitiesClient extends AbstractApiClient
{
    private ApiEntityManager $entityManager;
    private ApiEntityRegistry $entityRegistry;

    public function __construct(
        string $baseUrl,
        ?string $apiKey = null,
        ?ClientInterface $httpClient = null,
        array $defaultHeaders = [],
    ) {
        parent::__construct($baseUrl, $apiKey, $httpClient, $defaultHeaders);

        $this->entityManager = new ApiEntityManager($this, $this->getRepositoryClasses());
        $this->entityRegistry = new ApiEntityRegistry();
    }

    /**
     * @return class-string<AbstractApiRepository>[]
     */
    abstract protected function getRepositoryClasses(): array;

    public function getEntityManager(): ApiEntityManager
    {
        return $this->entityManager;
    }

    public function getEntityRegistry(): ApiEntityRegistry
    {
        return $this->entityRegistry;
    }

    /**
     * @param string|class-string<AbstractApiEntity> $entity
     */
    public function getRepository(string $entity): AbstractApiRepository
    {
        return $this->entityManager->get($entity);
    }

    public function buildEntityEntrypoint(AbstractApiEntity|string $abstractApiEntity, string $path): string
    {
        return $abstractApiEntity::getSnakeShortClassName() . '/' . $path;
    }
}
