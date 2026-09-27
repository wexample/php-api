<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Tests\Fixtures;

use Wexample\PhpApi\Common\Client;

/**
 * Records the delays the client asks for instead of sleeping.
 */
class PausingClient extends Client
{
    public const string PING_PATH = 'health';

    /**
     * @var float[]
     */
    public array $pauses = [];

    protected function pause(float $seconds): void
    {
        $this->pauses[] = $seconds;
    }
}
