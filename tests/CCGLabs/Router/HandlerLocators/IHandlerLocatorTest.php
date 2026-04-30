<?php

declare(strict_types=1);

namespace Tests\CCGLabs\Router\HandlerLocators;

use CCGLabs\Router\HandlerLocators\IHandlerLocator;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use ReflectionClass;

class IHandlerLocatorTest extends TestCase
{
    public function testInterface(): void
    {
        $this->assertTrue(interface_exists(IHandlerLocator::class));

        $handlerLocatorInterfaceReflector = new ReflectionClass(IHandlerLocator::class);

        // Tests for locate()

        // Exists
        $this->assertTrue($handlerLocatorInterfaceReflector->hasMethod('locate'));
        $locateMethodReflector = $handlerLocatorInterfaceReflector->getMethod('locate');

        // Has "request" parameter of type RequestInterface
        $locateParametersReflector = $locateMethodReflector->getParameters();
        $this->assertEquals('request', $locateParametersReflector[0]->getName());
        $this->assertEquals(RequestInterface::class, $locateParametersReflector[0]->getType());

        // Returns ResponseHandlerInterface
        $this->assertEquals(RequestHandlerInterface::class, $locateMethodReflector->getReturnType());
    }
}
