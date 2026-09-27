<?php

declare(strict_types=1);

namespace Wexample\PhpApi\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\PhpApi\Enum\HttpMethod;

class HttpMethodTest extends TestCase
{
    public function testToValueAcceptsEnumAndString(): void
    {
        $this->assertSame('POST', HttpMethod::toValue(HttpMethod::POST));
        $this->assertSame('PATCH', HttpMethod::toValue('patch'));
    }
}
