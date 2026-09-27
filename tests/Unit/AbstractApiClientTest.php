<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Wexample\PhpApi\Enum\HttpMethod;
use Wexample\PhpApi\Exceptions\ApiException;
use Wexample\PhpApi\Tests\Fixtures\JsonClient;

class AbstractApiClientTest extends TestCase
{
    public function testRequestJsonDecodesTheBody(): void
    {
        $client = $this->createClient([new Response(200, [], '{"items":[1,2]}')]);

        $this->assertSame(['items' => [1, 2]], $client->requestJson(HttpMethod::GET, 'things'));
    }

    public function testInvalidJsonBecomesAnApiException(): void
    {
        $client = $this->createClient([new Response(200, [], 'not json')]);

        try {
            $client->requestJson(HttpMethod::GET, 'things');
            $this->fail('ApiException expected.');
        } catch (ApiException $exception) {
            $this->assertStringStartsWith('Invalid JSON response', $exception->getMessage());
            $this->assertFalse($exception->isTransient());
        }
    }

    public function testDebugDumpDoesNotStopTheProcess(): void
    {
        $client = $this->createClient([new Response(500, [], '{"error":"boom"}')]);
        $client->setDebugEnabled(true);

        $this->expectException(ApiException::class);
        $this->expectOutputRegex('/curl -i -X GET/');

        $client->requestJson(HttpMethod::GET, 'things');
    }

    /**
     * @param array<int, mixed> $queue
     */
    private function createClient(array $queue): JsonClient
    {
        return new JsonClient(
            'https://remote.test',
            httpClient: new GuzzleClient([
                'base_uri' => 'https://remote.test/',
                'handler' => HandlerStack::create(new MockHandler($queue)),
            ]),
        );
    }
}
