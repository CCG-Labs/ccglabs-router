<?php

declare(strict_types=1);

namespace Tests\CCGLabs\Router;

use CCGLabs\Router\RouteMatch;
use PHPUnit\Framework\TestCase;
use Psr\Http\Server\RequestHandlerInterface;

class RouteMatchTest extends TestCase
{
    public function testExposesHandlerAndParams(): void
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $match = new RouteMatch($handler, ['id' => '42']);

        $this->assertSame($handler, $match->handler);
        $this->assertSame(['id' => '42'], $match->params);
    }

    public function testParamsDefaultToEmptyArray(): void
    {
        $handler = $this->createStub(RequestHandlerInterface::class);
        $match = new RouteMatch($handler);

        $this->assertSame([], $match->params);
    }
}
