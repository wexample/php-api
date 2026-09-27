<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Tests\Unit;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Wexample\PhpApi\Common\ClientOptions;
use Wexample\PhpApi\Enum\HttpMethod;
use Wexample\PhpApi\Exceptions\ApiException;
use Wexample\PhpApi\Tests\Fixtures\PausingClient;

class ClientOptionsTest extends TestCase
{
    /**
     * @var array<int, array{request: Request, options: array}>
     */
    private array $history = [];

    public function testTimeoutsAreSentWithEveryRequest(): void
    {
        $client = $this->createClient([new Response(200)], new ClientOptions(timeout: 30, connectTimeout: 5));

        $client->get('things');

        $this->assertSame(30.0, $this->history[0]['options']['timeout']);
        $this->assertSame(5.0, $this->history[0]['options']['connect_timeout']);
    }

    public function testRequestTimeoutOverridesTheClientOne(): void
    {
        $client = $this->createClient([new Response(200)], new ClientOptions(timeout: 30));

        $client->get('things', ['timeout' => 120]);

        $this->assertSame(120, $this->history[0]['options']['timeout']);
    }

    public function testTransientFailuresAreRetriedWithDoublingDelay(): void
    {
        $client = $this->createClient(
            [new Response(503), $this->connectionRefused(), new Response(200)],
            new ClientOptions(retries: 2, retryDelay: 0.5),
        );

        $this->assertSame(200, $client->get('things')->getStatusCode());
        $this->assertCount(3, $this->history);
        $this->assertSame([0.5, 1.0], $client->pauses);
    }

    public function testRetriesStopAtTheLimit(): void
    {
        $client = $this->createClient(
            [new Response(503), new Response(503)],
            new ClientOptions(retries: 1, retryDelay: 0.1),
        );

        try {
            $client->get('things');
            $this->fail('ApiException expected.');
        } catch (ApiException $exception) {
            $this->assertSame(503, $exception->getCode());
        }

        $this->assertCount(2, $this->history);
    }

    public function testClientErrorsAreNeverRetried(): void
    {
        $client = $this->createClient([new Response(404)], new ClientOptions(retries: 3));

        $this->expectException(ApiException::class);

        try {
            $client->get('things');
        } finally {
            $this->assertCount(1, $this->history);
        }
    }

    public function testNonIdempotentMethodsAreNeverRetried(): void
    {
        $client = $this->createClient([new Response(503)], new ClientOptions(retries: 3));

        $this->expectException(ApiException::class);

        try {
            $client->request(HttpMethod::POST, 'things');
        } finally {
            $this->assertCount(1, $this->history);
        }
    }

    public function testRateLimitSpacesConsecutiveRequests(): void
    {
        $client = $this->createClient([new Response(200), new Response(200)], new ClientOptions(rateLimitDelay: 10));

        $client->get('first');
        $client->get('second');

        $this->assertCount(1, $client->pauses);
        $this->assertGreaterThan(9.0, $client->pauses[0]);
    }

    public function testCheckConnectionRequestsThePingPath(): void
    {
        $client = $this->createClient([new Response(200), new Response(500)]);

        $this->assertTrue($client->checkConnection());
        $this->assertSame('/health', $this->history[0]['request']->getUri()->getPath());
        $this->assertFalse($client->checkConnection());
    }

    public function testCheckConnectionReportsAnUnreachableRemote(): void
    {
        $client = $this->createClient([$this->connectionRefused()]);

        $this->assertFalse($client->checkConnection());
    }

    /**
     * @param array<int, mixed> $queue
     */
    private function createClient(array $queue, ?ClientOptions $options = null): PausingClient
    {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        return new PausingClient(
            'https://remote.test',
            httpClient: new GuzzleClient(['base_uri' => 'https://remote.test/', 'handler' => $stack]),
            options: $options,
        );
    }

    private function connectionRefused(): ConnectException
    {
        return new ConnectException('Connection refused', new Request('GET', 'things'));
    }
}
