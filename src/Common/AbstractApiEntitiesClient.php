<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

use GuzzleHttp\ClientInterface;

abstract class AbstractApiEntitiesClient extends AbstractApiClient
{
    private ApiEntityManager $entityManager;

    public function __construct(
        string $baseUrl,
        ?string $apiKey = null,
        ?ClientInterface $httpClient = null,
        array $defaultHeaders = [],
    )
    {
        parent::__construct($baseUrl, $apiKey, $httpClient, $defaultHeaders);

        $this->entityManager = new ApiEntityManager($this, $this->getRepositoryClasses());
    }

    /**
     * @return class-string<AbstractApiRepository>[]
     */
    abstract protected function getRepositoryClasses(): array;

    public function getEntityManager(): ApiEntityManager
    {
        return $this->entityManager;
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
