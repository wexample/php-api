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
use Psr\Http\Message\RequestInterface;
use Wexample\PhpApi\Common\Client;
use Wexample\PhpApi\Enum\HttpMethod;
use Wexample\PhpApi\Exceptions\ApiException;
use Wexample\PhpApi\Tests\Fixtures\CustomUserAgentClient;

class ClientTest extends TestCase
{
    /**
     * @var array<int, array{request: RequestInterface}>
     */
    private array $history = [];

    public function testClientWithoutApiKeySendsNoAuthorization(): void
    {
        $client = $this->createClient([new Response(200)]);

        $client->get('things');

        $this->assertFalse($this->lastRequest()->hasHeader('Authorization'));
    }

    public function testApiKeyIsSentAsBearerToken(): void
    {
        $client = $this->createClient([new Response(200)], 'secret');

        $client->get('things');

        $this->assertSame('Bearer secret', $this->lastRequest()->getHeaderLine('Authorization'));
    }

    public function testUserAgentComesFromTheClassConstant(): void
    {
        $client = $this->createClient([new Response(200), new Response(200)]);
        $client->get('things');
        $this->assertSame(Client::USER_AGENT, $this->lastRequest()->getHeaderLine('User-Agent'));

        $client = $this->createClient([new Response(200)], clientClass: CustomUserAgentClient::class);
        $client->get('things');
        $this->assertSame('custom-agent', $this->lastRequest()->getHeaderLine('User-Agent'));
    }

    public function testMethodAcceptsEnumAndLegacyString(): void
    {
        $client = $this->createClient([new Response(200), new Response(200)]);

        $client->request(HttpMethod::PATCH, 'things');
        $this->assertSame('PATCH', $this->lastRequest()->getMethod());

        $client->request('delete', 'things');
        $this->assertSame('DELETE', $this->lastRequest()->getMethod());
    }

    public function testClientErrorIsNotTransient(): void
    {
        $exception = $this->captureException([new Response(404, [], '{"error":"missing"}')]);

        $this->assertSame(404, $exception->getCode());
        $this->assertSame(['error' => 'missing'], $exception->getResponseData());
        $this->assertFalse($exception->isTransient());
    }

    public function testServerErrorAndRateLimitAreTransient(): void
    {
        $this->assertTrue($this->captureException([new Response(503)])->isTransient());
        $this->assertTrue($this->captureException([new Response(429)])->isTransient());
    }

    public function testConnectionFailureIsTransient(): void
    {
        $exception = $this->captureException([
            new ConnectException('Connection refused', new Request('GET', 'things')),
        ]);

        $this->assertSame(0, $exception->getCode());
        $this->assertTrue($exception->isTransient());
    }

    /**
     * @param array<int, mixed> $queue
     * @param class-string<Client> $clientClass
     */
    private function createClient(
        array $queue,
        ?string $apiKey = null,
        string $clientClass = Client::class,
    ): Client {
        $this->history = [];
        $stack = HandlerStack::create(new MockHandler($queue));
        $stack->push(Middleware::history($this->history));

        return new $clientClass(
            'https://remote.test',
            $apiKey,
            new GuzzleClient(['base_uri' => 'https://remote.test/', 'handler' => $stack]),
        );
    }

    /**
     * @param array<int, mixed> $queue
     */
    private function captureException(array $queue): ApiException
    {
        try {
            $this->createClient($queue)->get('things');
        } catch (ApiException $exception) {
            return $exception;
        }

        $this->fail('ApiException expected.');
    }

    private function lastRequest(): RequestInterface
    {
        return end($this->history)['request'];
    }
}
