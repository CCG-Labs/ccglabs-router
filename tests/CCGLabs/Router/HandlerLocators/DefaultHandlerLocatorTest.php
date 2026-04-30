<?php

/**
 * This file contains Tests\CCGLabs\Router\HandlerLocators\DefaultHandlerLocatorTest
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

namespace Tests\CCGLabs\Router\HandlerLocators;

use CCGLabs\Router\Exceptions\RouteHandlerNotFoundException;
use CCGLabs\Router\HandlerLocators\DefaultHandlerLocator;
use CCGLabs\Router\HTTP\Verb;
use CCGLabs\Router\IRoute;
use Exception;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WeakMap;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;

class DefaultHandlerLocatorTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(DefaultHandlerLocator::class));
    }

    public function testConstructor(): void
    {
        $locator = new DefaultHandlerLocator();
        $this->assertInstanceOf(DefaultHandlerLocator::class, $locator);
    }

    public function testAddRouteSupportsCallable(): void
    {
        $locator = new DefaultHandlerLocator();

        $verb = Verb::GET;
        $route = $this->createMock(IRoute::class);
        $handler = function (RequestInterface $request): RequestHandlerInterface {
            // Dummy handler
            return new class implements RequestHandlerInterface {
                public function handle(RequestInterface $request): \Psr\Http\Message\ResponseInterface
                {
                    // Dummy response
                    throw new Exception("Not implemented");
                }
            };
        };

        $result = $locator->addRoute($verb, $route, $handler);
        $this->assertInstanceOf(DefaultHandlerLocator::class, $result);
    }

    public function testAddRouteSupportsRequestHandlerInterface(): void
    {
        $locator = new DefaultHandlerLocator();

        $verb = Verb::POST;
        $route = $this->createMock(IRoute::class);
        $handler = $this->createMock(RequestHandlerInterface::class);

        $result = $locator->addRoute($verb, $route, $handler);
        $this->assertInstanceOf(DefaultHandlerLocator::class, $result);
    }

    public function testLocateThrowsInvalidArgumentExceptionForUnsupportedVerb(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $locator = new DefaultHandlerLocator();
        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('INVALID_VERB');

        $locator->locate($request);
    }

    public function testLocateThrowsRouteHandlerNotFoundExceptionForUnregisteredRoute(): void
    {
        $this->expectException(RouteHandlerNotFoundException::class);
        $this->expectExceptionMessage('Handler not found for /unregistered');

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/unregistered');

        $locator = new DefaultHandlerLocator();
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $locator->locate($request);
    }

    public function testLocateThrowsRouteHandlerNotFoundExceptionWhenRoutesAreRegisteredButDoNotMatch(): void
    {
        $this->expectException(RouteHandlerNotFoundException::class);
        $this->expectExceptionMessage('Handler not found for /no-match');

        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/no-match');

        $route = $this->createMock(IRoute::class);
        $route->method('matches')->willReturn(false);

        $locator = new DefaultHandlerLocator();
        $locator->addRoute(Verb::GET, $route, function (RequestInterface $request): RequestHandlerInterface {
            return new class implements RequestHandlerInterface {
                public function handle(RequestInterface $request): \Psr\Http\Message\ResponseInterface
                {
                    throw new Exception("Not implemented");
                }
            };
        });

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $locator->locate($request);
    }

    public function testLocateReturnsMatchingRoute(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/match');

        $route = $this->createMock(IRoute::class);
        $route->method('matches')->willReturn(true);

        $handler = $this->createMock(RequestHandlerInterface::class);

        $locator = new DefaultHandlerLocator();
        $locator->addRoute(Verb::GET, $route, $handler);

        $request = $this->createMock(RequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $result = $locator->locate($request);
        $this->assertSame($handler, $result);
    }

    /**
     * Test route precedence - first matching route wins
     */
    public function testRoutePrecedenceFirstMatchWins(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/users/123');

        // Create two routes that both match
        $route1 = $this->createMock(IRoute::class);
        $route1->method('matches')->with('/users/123')->willReturn(true);

        $route2 = $this->createMock(IRoute::class);
        $route2->method('matches')->with('/users/123')->willReturn(true);

        // Create different handlers
        $handler1 = $this->createMock(RequestHandlerInterface::class);
        $handler2 = $this->createMock(RequestHandlerInterface::class);

        $locator = new DefaultHandlerLocator();
        // Add routes in order
        $locator->addRoute(Verb::GET, $route1, $handler1);
        $locator->addRoute(Verb::GET, $route2, $handler2);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $result = $locator->locate($request);

        // First route should win
        $this->assertSame($handler1, $result);
    }

    /**
     * Test that more specific routes can be registered first to take precedence
     */
    public function testSpecificRoutesCanTakePrecedence(): void
    {
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/users/profile');

        // Specific route (static)
        $specificRoute = $this->createMock(IRoute::class);
        $specificRoute->method('matches')
            ->willReturnCallback(function ($path) {
                return $path === '/users/profile';
            });

        // Generic route (with parameter)
        $genericRoute = $this->createMock(IRoute::class);
        $genericRoute->method('matches')
            ->willReturnCallback(function ($path) {
                return preg_match('#^/users/[^/]+$#', $path) === 1;
            });

        $specificHandler = $this->createMock(RequestHandlerInterface::class);
        $genericHandler = $this->createMock(RequestHandlerInterface::class);

        $locator = new DefaultHandlerLocator();
        // Register specific route first
        $locator->addRoute(Verb::GET, $specificRoute, $specificHandler);
        $locator->addRoute(Verb::GET, $genericRoute, $genericHandler);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $result = $locator->locate($request);

        // Specific route should win
        $this->assertSame($specificHandler, $result);
    }

    /**
     * Test WeakMap behavior with Verb enum objects
     */
    public function testWeakMapBehaviorWithVerbEnum(): void
    {
        $locator = new DefaultHandlerLocator();

        // Add routes for different verbs
        $getRoute = $this->createMock(IRoute::class);
        $getRoute->method('matches')->willReturn(true);
        $getHandler = $this->createMock(RequestHandlerInterface::class);

        $postRoute = $this->createMock(IRoute::class);
        $postRoute->method('matches')->willReturn(true);
        $postHandler = $this->createMock(RequestHandlerInterface::class);

        $locator->addRoute(Verb::GET, $getRoute, $getHandler);
        $locator->addRoute(Verb::POST, $postRoute, $postHandler);

        // Test GET request
        $getUri = $this->createMock(UriInterface::class);
        $getUri->method('getPath')->willReturn('/test');

        $getRequest = $this->createMock(ServerRequestInterface::class);
        $getRequest->method('getMethod')->willReturn('GET');
        $getRequest->method('getUri')->willReturn($getUri);

        $result = $locator->locate($getRequest);
        $this->assertSame($getHandler, $result);

        // Test POST request
        $postRequest = $this->createMock(ServerRequestInterface::class);
        $postRequest->method('getMethod')->willReturn('POST');
        $postRequest->method('getUri')->willReturn($getUri);

        $result = $locator->locate($postRequest);
        $this->assertSame($postHandler, $result);
    }

    /**
     * Test that routes are preserved in array and not garbage collected
     */
    public function testRoutesNotGarbageCollected(): void
    {
        $locator = new DefaultHandlerLocator();

        // Create route and handler
        $uri = $this->createMock(UriInterface::class);
        $uri->method('getPath')->willReturn('/test');

        // Add route in a scope that will end
        (function () use ($locator) {
            $route = $this->createMock(IRoute::class);
            $route->method('matches')->with('/test')->willReturn(true);

            $handler = $this->createMock(RequestHandlerInterface::class);

            $locator->addRoute(Verb::GET, $route, $handler);
        })();

        // Force garbage collection
        gc_collect_cycles();

        // Route should still be found
        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        // This should not throw an exception
        $result = $locator->locate($request);
        $this->assertInstanceOf(RequestHandlerInterface::class, $result);
    }

    /**
     * Test multiple routes per verb are maintained
     */
    public function testMultipleRoutesPerVerb(): void
    {
        $locator = new DefaultHandlerLocator();

        // Add multiple routes for GET
        $route1 = $this->createMock(IRoute::class);
        $route1->method('matches')->willReturnCallback(function ($path) {
            return $path === '/route1';
        });

        $route2 = $this->createMock(IRoute::class);
        $route2->method('matches')->willReturnCallback(function ($path) {
            return $path === '/route2';
        });

        $route3 = $this->createMock(IRoute::class);
        $route3->method('matches')->willReturnCallback(function ($path) {
            return $path === '/route3';
        });

        $handler1 = $this->createMock(RequestHandlerInterface::class);
        $handler2 = $this->createMock(RequestHandlerInterface::class);
        $handler3 = $this->createMock(RequestHandlerInterface::class);

        $locator->addRoute(Verb::GET, $route1, $handler1);
        $locator->addRoute(Verb::GET, $route2, $handler2);
        $locator->addRoute(Verb::GET, $route3, $handler3);

        // Test each route
        $paths = [
            '/route1' => $handler1,
            '/route2' => $handler2,
            '/route3' => $handler3,
        ];

        foreach ($paths as $path => $expectedHandler) {
            $uri = $this->createMock(UriInterface::class);
            $uri->method('getPath')->willReturn($path);

            $request = $this->createMock(ServerRequestInterface::class);
            $request->method('getMethod')->willReturn('GET');
            $request->method('getUri')->willReturn($uri);

            $result = $locator->locate($request);
            $this->assertSame($expectedHandler, $result, "Failed for path: $path");
        }
    }
}
