<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Tests\Fixtures;

use Wexample\PhpApi\Common\Client;

class CustomUserAgentClient extends Client
{
    public const string USER_AGENT = 'custom-agent';
}
