<?php

declare(strict_types=1);

namespace Tests\CCGLabs\Router\HandlerLocators;

use CCGLabs\Router\Exceptions\RouteHandlerNotFoundException;
use CCGLabs\Router\HandlerLocators\DefaultHandlerLocator;
use CCGLabs\Router\HTTP\Verb;
use CCGLabs\Router\IRoute;
use CCGLabs\Router\RouteMatch;
use Exception;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;

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
        $route = $this->createStub(IRoute::class);
        $handler = function (RequestInterface $request): RequestHandlerInterface {
            return new class implements RequestHandlerInterface {
                public function handle(RequestInterface $request): \Psr\Http\Message\ResponseInterface
                {
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
        $route = $this->createStub(IRoute::class);
        $handler = $this->createStub(RequestHandlerInterface::class);

        $result = $locator->addRoute($verb, $route, $handler);
        $this->assertInstanceOf(DefaultHandlerLocator::class, $result);
    }

    public function testLocateThrowsInvalidArgumentExceptionForUnsupportedVerb(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $locator = new DefaultHandlerLocator();
        $request = $this->createStub(RequestInterface::class);
        $request->method('getMethod')->willReturn('INVALID_VERB');

        $locator->locate($request);
    }

    public function testLocateThrowsRouteHandlerNotFoundExceptionForUnregisteredRoute(): void
    {
        $this->expectException(RouteHandlerNotFoundException::class);
        $this->expectExceptionMessage('Handler not found for /unregistered');

        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/unregistered');

        $locator = new DefaultHandlerLocator();
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $locator->locate($request);
    }

    public function testLocateThrowsRouteHandlerNotFoundExceptionWhenRoutesAreRegisteredButDoNotMatch(): void
    {
        $this->expectException(RouteHandlerNotFoundException::class);
        $this->expectExceptionMessage('Handler not found for /no-match');

        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/no-match');

        $route = $this->createStub(IRoute::class);
        $route->method('matches')->willReturn(null);

        $locator = new DefaultHandlerLocator();
        $locator->addRoute(Verb::GET, $route, function (RequestInterface $request): RequestHandlerInterface {
            return new class implements RequestHandlerInterface {
                public function handle(RequestInterface $request): \Psr\Http\Message\ResponseInterface
                {
                    throw new Exception("Not implemented");
                }
            };
        });

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $locator->locate($request);
    }

    public function testLocateReturnsRouteMatchWithHandlerAndParams(): void
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/user/42');

        $route = $this->createStub(IRoute::class);
        $route->method('matches')->willReturn(['id' => '42']);

        $handler = $this->createStub(RequestHandlerInterface::class);

        $locator = new DefaultHandlerLocator();
        $locator->addRoute(Verb::GET, $route, $handler);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $result = $locator->locate($request);

        $this->assertInstanceOf(RouteMatch::class, $result);
        $this->assertSame($handler, $result->handler);
        $this->assertSame(['id' => '42'], $result->params);
    }

    public function testLocateReturnsRouteMatchWithEmptyParamsForStaticRoute(): void
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/health');

        $route = $this->createStub(IRoute::class);
        $route->method('matches')->willReturn([]);

        $handler = $this->createStub(RequestHandlerInterface::class);

        $locator = new DefaultHandlerLocator();
        $locator->addRoute(Verb::GET, $route, $handler);

        $request = $this->createStub(RequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $result = $locator->locate($request);

        $this->assertSame($handler, $result->handler);
        $this->assertSame([], $result->params);
    }

    public function testRoutePrecedenceFirstMatchWins(): void
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/users/123');

        $route1 = $this->createStub(IRoute::class);
        $route1->method('matches')->with('/users/123')->willReturn([]);

        $route2 = $this->createStub(IRoute::class);
        $route2->method('matches')->with('/users/123')->willReturn([]);

        $handler1 = $this->createStub(RequestHandlerInterface::class);
        $handler2 = $this->createStub(RequestHandlerInterface::class);

        $locator = new DefaultHandlerLocator();
        $locator->addRoute(Verb::GET, $route1, $handler1);
        $locator->addRoute(Verb::GET, $route2, $handler2);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $result = $locator->locate($request);

        $this->assertSame($handler1, $result->handler);
    }

    public function testSpecificRoutesCanTakePrecedence(): void
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/users/profile');

        $specificRoute = $this->createStub(IRoute::class);
        $specificRoute->method('matches')
            ->willReturnCallback(fn ($path) => $path === '/users/profile' ? [] : null);

        $genericRoute = $this->createStub(IRoute::class);
        $genericRoute->method('matches')
            ->willReturnCallback(function ($path) {
                return preg_match('#^/users/[^/]+$#', $path) === 1
                    ? ['user' => 'profile']
                    : null;
            });

        $specificHandler = $this->createStub(RequestHandlerInterface::class);
        $genericHandler = $this->createStub(RequestHandlerInterface::class);

        $locator = new DefaultHandlerLocator();
        $locator->addRoute(Verb::GET, $specificRoute, $specificHandler);
        $locator->addRoute(Verb::GET, $genericRoute, $genericHandler);

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);

        $result = $locator->locate($request);

        $this->assertSame($specificHandler, $result->handler);
    }

    public function testRoutesAreSegregatedByVerb(): void
    {
        $locator = new DefaultHandlerLocator();

        $getRoute = $this->createStub(IRoute::class);
        $getRoute->method('matches')->willReturn([]);
        $getHandler = $this->createStub(RequestHandlerInterface::class);

        $postRoute = $this->createStub(IRoute::class);
        $postRoute->method('matches')->willReturn([]);
        $postHandler = $this->createStub(RequestHandlerInterface::class);

        $locator->addRoute(Verb::GET, $getRoute, $getHandler);
        $locator->addRoute(Verb::POST, $postRoute, $postHandler);

        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/test');

        $getRequest = $this->createStub(ServerRequestInterface::class);
        $getRequest->method('getMethod')->willReturn('GET');
        $getRequest->method('getUri')->willReturn($uri);

        $this->assertSame($getHandler, $locator->locate($getRequest)->handler);

        $postRequest = $this->createStub(ServerRequestInterface::class);
        $postRequest->method('getMethod')->willReturn('POST');
        $postRequest->method('getUri')->willReturn($uri);

        $this->assertSame($postHandler, $locator->locate($postRequest)->handler);
    }

    public function testMultipleRoutesPerVerb(): void
    {
        $locator = new DefaultHandlerLocator();

        $route1 = $this->createStub(IRoute::class);
        $route1->method('matches')->willReturnCallback(fn ($path) => $path === '/route1' ? [] : null);

        $route2 = $this->createStub(IRoute::class);
        $route2->method('matches')->willReturnCallback(fn ($path) => $path === '/route2' ? [] : null);

        $route3 = $this->createStub(IRoute::class);
        $route3->method('matches')->willReturnCallback(fn ($path) => $path === '/route3' ? [] : null);

        $handler1 = $this->createStub(RequestHandlerInterface::class);
        $handler2 = $this->createStub(RequestHandlerInterface::class);
        $handler3 = $this->createStub(RequestHandlerInterface::class);

        $locator->addRoute(Verb::GET, $route1, $handler1);
        $locator->addRoute(Verb::GET, $route2, $handler2);
        $locator->addRoute(Verb::GET, $route3, $handler3);

        $paths = [
            '/route1' => $handler1,
            '/route2' => $handler2,
            '/route3' => $handler3,
        ];

        foreach ($paths as $path => $expectedHandler) {
            $uri = $this->createStub(UriInterface::class);
            $uri->method('getPath')->willReturn($path);

            $request = $this->createStub(ServerRequestInterface::class);
            $request->method('getMethod')->willReturn('GET');
            $request->method('getUri')->willReturn($uri);

            $result = $locator->locate($request);
            $this->assertSame($expectedHandler, $result->handler, "Failed for path: $path");
        }
    }
}
