<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Platform;

use PHPUnit\Framework\TestCase;
use WorkEddy\Platform\Http\Response;

final class ResponseTest extends TestCase
{
    public function test_can_instantiate_response_directly(): void
    {
        $response = new Response('test body', 200, ['X-Test' => 'value']);
        self::assertInstanceOf(Response::class, $response);
        self::assertSame('test body', $response->getBody());
        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['X-Test' => 'value'], $response->getHeaders());
    }
}
