<?php

/**
 * This file contains Tests\CCGLabs\Router\ApplicationTest
 *
 * Copyright 2025 Brian Reich
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this
 * software and associated documentation files (the “Software”), to deal in the
 * Software without restriction, including without limitation the rights to use, copy,
 * modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so, subject to the
 * following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE
 * OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author Brian Reich <brian@brianreich.dev>
 * @copyright Copyright (C) 2025 Brian Reich
 * @license MIT
 */

declare(strict_types=1);

namespace Tests\CCGLabs\Router;

use CCGLabs\Router\Application;
use CCGLabs\Router\HandlerLocators\DefaultHandlerLocator;
use CCGLabs\Router\HandlerLocators\IHandlerLocator;
use CCGLabs\Router\HTTP\Verb;
use CCGLabs\Router\IRoute;
use CCGLabs\Router\Routes\TokenizedRoute;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApplicationTest extends TestCase
{
    public function testConstuctorWithNoArgumentsUsesDefaultHandlerLocator(): void
    {
        $application = new Application();
        $locator = $application->getHandlerLocator();
        $this->assertInstanceOf(DefaultHandlerLocator::class, $locator);
    }

    public function testConstructorAcceptsAnyIHandlerLocator(): void
    {
        $locator = $this->createMock(IHandlerLocator::class);
        $application = new Application($locator);
        $this->assertSame($locator, $application->getHandlerLocator());
    }

    public function testAddRouteCallsHandlerLocatorAddRoute(): void
    {
        $route = $this->createMock(IRoute::class);
        $handler = fn () => true;
        $routeHandler = $this->createMock(IHandlerLocator::class);
        $routeHandler
            ->expects($this->once())
            ->method('addRoute');
        $application = new Application($routeHandler);
        $application->addRoute(Verb::GET, $route, $handler);
    }

    public function testAddRouteAutomaticallyConvertsStringRoutes(): void
    {
        $route = '/user/{id}';
        $handler = fn () => true;
        $routeHandler = $this->createMock(IHandlerLocator::class);
        $routeHandler
            ->expects($this->once())
            ->method('addRoute')
            ->with(
                Verb::POST,
                TokenizedRoute::fromPath($route),
                $handler
            );

        $application = new Application($routeHandler);
        $application->addRoute(Verb::POST, $route, $handler);
    }

    public function testGet(): void
    {
        $verb = Verb::GET;
        $route = '/users/{id}';
        $callable = fn () => true;
        $locator = $this->createMock(IHandlerLocator::class);
        $locator
            ->expects($this->once())
            ->method('addRoute')
            ->with(
                $verb,
                TokenizedRoute::fromPath($route),
                $callable
            );

        $application = new Application($locator);
        $application->get($route, $callable);
    }

    public function testPost(): void
    {
        $verb = Verb::POST;
        $route = '/users/{id}';
        $callable = fn () => true;
        $locator = $this->createMock(IHandlerLocator::class);
        $locator
            ->expects($this->once())
            ->method('addRoute')
            ->with(
                $verb,
                TokenizedRoute::fromPath($route),
                $callable
            );

        $application = new Application($locator);
        $application->post($route, $callable);
    }

    public function testPatch(): void
    {
        $verb = Verb::PATCH;
        $route = '/users/{id}';
        $callable = fn () => true;
        $locator = $this->createMock(IHandlerLocator::class);
        $locator
            ->expects($this->once())
            ->method('addRoute')
            ->with(
                $verb,
                TokenizedRoute::fromPath($route),
                $callable
            );

        $application = new Application($locator);
        $application->patch($route, $callable);
    }

    public function testPut(): void
    {
        $verb = Verb::PUT;
        $route = '/users/{id}';
        $callable = fn () => true;
        $locator = $this->createMock(IHandlerLocator::class);
        $locator
            ->expects($this->once())
            ->method('addRoute')
            ->with(
                $verb,
                TokenizedRoute::fromPath($route),
                $callable
            );

        $application = new Application($locator);
        $application->put($route, $callable);
    }

    public function testDelete(): void
    {
        $verb = Verb::DELETE;
        $route = '/users/{id}';
        $callable = fn () => true;
        $locator = $this->createMock(IHandlerLocator::class);
        $locator
            ->expects($this->once())
            ->method('addRoute')
            ->with(
                $verb,
                TokenizedRoute::fromPath($route),
                $callable
            );

        $application = new Application($locator);
        $application->delete($route, $callable);
    }

    /**
     * Test middleware exception handling
     */
    public function testMiddlewareExceptionHandling(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        // Create a handler that will be called
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())
            ->method('handle');

        // Create a middleware that throws an exception
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())
            ->method('process')
            ->willThrowException(new \RuntimeException('Middleware error'));

        // Setup locator to return the handler
        $locator = $this->createMock(IHandlerLocator::class);
        $locator->expects($this->once())
            ->method('locate')
            ->with($request)
            ->willReturn($handler);

        $application = new Application($locator);
        $application->add($middleware);

        // Exception should propagate
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Middleware error');
        $application->handle($request);
    }

    /**
     * Test multiple middleware execution order
     */
    public function testMultipleMiddlewareExecutionOrder(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);
        $executionOrder = [];

        // Create final handler
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function () use (&$executionOrder, $response) {
                $executionOrder[] = 'handler';
                return $response;
            });

        // Create first middleware
        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware1->expects($this->once())
            ->method('process')
            ->willReturnCallback(function ($req, $handler) use (&$executionOrder) {
                $executionOrder[] = 'middleware1-before';
                $response = $handler->handle($req);
                $executionOrder[] = 'middleware1-after';
                return $response;
            });

        // Create second middleware
        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware2->expects($this->once())
            ->method('process')
            ->willReturnCallback(function ($req, $handler) use (&$executionOrder) {
                $executionOrder[] = 'middleware2-before';
                $response = $handler->handle($req);
                $executionOrder[] = 'middleware2-after';
                return $response;
            });

        // Create third middleware
        $middleware3 = $this->createMock(MiddlewareInterface::class);
        $middleware3->expects($this->once())
            ->method('process')
            ->willReturnCallback(function ($req, $handler) use (&$executionOrder) {
                $executionOrder[] = 'middleware3-before';
                $response = $handler->handle($req);
                $executionOrder[] = 'middleware3-after';
                return $response;
            });

        // Setup locator
        $locator = $this->createMock(IHandlerLocator::class);
        $locator->expects($this->once())
            ->method('locate')
            ->willReturn($handler);

        $application = new Application($locator);
        $application->add($middleware1);
        $application->add($middleware2);
        $application->add($middleware3);

        $result = $application->handle($request);

        // Verify execution order (onion model)
        $expectedOrder = [
            'middleware1-before',
            'middleware2-before',
            'middleware3-before',
            'handler',
            'middleware3-after',
            'middleware2-after',
            'middleware1-after',
        ];

        $this->assertEquals($expectedOrder, $executionOrder);
        $this->assertSame($response, $result);
    }

    /**
     * Test middleware can short-circuit the chain
     */
    public function testMiddlewareCanShortCircuit(): void
    {
        $request = $this->createMock(ServerRequestInterface::class);
        $shortCircuitResponse = $this->createMock(ResponseInterface::class);

        // Handler should never be called
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())
            ->method('handle');

        // Middleware that short-circuits
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())
            ->method('process')
            ->willReturn($shortCircuitResponse);

        $locator = $this->createMock(IHandlerLocator::class);
        $locator->expects($this->once())
            ->method('locate')
            ->willReturn($handler);

        $application = new Application($locator);
        $application->add($middleware);

        $result = $application->handle($request);
        $this->assertSame($shortCircuitResponse, $result);
    }

    /**
     * Test middleware can modify request
     */
    public function testMiddlewareCanModifyRequest(): void
    {
        $originalRequest = $this->createMock(ServerRequestInterface::class);
        $modifiedRequest = $this->createMock(ServerRequestInterface::class);
        $response = $this->createMock(ResponseInterface::class);

        // Handler should receive modified request
        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($modifiedRequest)
            ->willReturn($response);

        // Middleware that modifies request
        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())
            ->method('process')
            ->willReturnCallback(function ($req, $handler) use ($modifiedRequest) {
                return $handler->handle($modifiedRequest);
            });

        $locator = $this->createMock(IHandlerLocator::class);
        $locator->expects($this->once())
            ->method('locate')
            ->willReturn($handler);

        $application = new Application($locator);
        $application->add($middleware);

        $result = $application->handle($originalRequest);
        $this->assertSame($response, $result);
    }
}
