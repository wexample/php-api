<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Common;

/**
 * Transport behaviour of a Client, mirroring the fields of the Python
 * wexample_api AbstractGateway. Defaults keep the historical behaviour: no
 * timeout, no retry, no pacing.
 */
final readonly class ClientOptions
{
    /**
     * @param float $timeout Seconds a whole request may take; 0 waits indefinitely.
     * @param float $connectTimeout Seconds to establish the connection; 0 waits indefinitely.
     * @param int $retries Extra attempts after a transient failure, for idempotent methods only:
     *        a retried POST or PATCH could apply twice on the remote.
     * @param float $retryDelay Seconds before the first retry, doubled at each further attempt.
     * @param float $rateLimitDelay Minimum seconds between two requests of the same client.
     */
    public function __construct(
        public float $timeout = 0.0,
        public float $connectTimeout = 0.0,
        public int $retries = 0,
        public float $retryDelay = 1.0,
        public float $rateLimitDelay = 0.0,
    ) {
    }
}
