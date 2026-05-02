<?php

declare(strict_types=1);

namespace Tests\CCGLabs\Router\Routes;

use CCGLabs\Router\Routes\StringRoute;
use PHPUnit\Framework\TestCase;

class StringRouteTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(StringRoute::class));
    }

    public function testMatchesReturnsEmptyArrayWhenStringsAreEqual(): void
    {
        $path = '/users/12';
        $route = new StringRoute($path);
        $this->assertSame([], $route->matches($path));
    }

    public function testMatchesReturnsNullWhenStringsAreNotEqual(): void
    {
        $route = new StringRoute('/users/me');
        $this->assertNull($route->matches('/users/somebodyelse'));
    }
}
