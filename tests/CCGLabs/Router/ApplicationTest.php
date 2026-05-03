<?php

declare(strict_types=1);

namespace Tests\CCGLabs\Router;

use CCGLabs\Router\Application;
use CCGLabs\Router\Exceptions\MissingRouteParameterException;
use CCGLabs\Router\Exceptions\RouteNotRenderableException;
use CCGLabs\Router\Exceptions\UnknownRouteException;
use CCGLabs\Router\HandlerLocators\DefaultHandlerLocator;
use CCGLabs\Router\HandlerLocators\IHandlerLocator;
use CCGLabs\Router\HTTP\Verb;
use CCGLabs\Router\IRoute;
use CCGLabs\Router\RouteCache;
use CCGLabs\Router\RouteMatch;
use CCGLabs\Router\Routes\TokenizedRoute;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class ApplicationTest extends TestCase
{
    private string $cacheFile = '';

    protected function setUp(): void
    {
        $this->cacheFile = (tempnam(sys_get_temp_dir(), 'ccglabs-router-app-test-') ?: '');
        @unlink($this->cacheFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->cacheFile);
    }

    /**
     * Builds a stub request that locate() will accept, plus a mock locator
     * that returns a RouteMatch with a no-op handler. Used by tests that
     * need to call handle() to trigger cache persistence.
     */
    private function makeHandledRequest(): ServerRequestInterface
    {
        $uri = $this->createStub(UriInterface::class);
        $uri->method('getPath')->willReturn('/__ccglabs_test_cache_trigger__');

        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getMethod')->willReturn('GET');
        $request->method('getUri')->willReturn($uri);
        $request->method('withAttribute')->willReturnSelf();

        return $request;
    }

    public function testConstuctorWithNoArgumentsUsesDefaultHandlerLocator(): void
    {
        $application = new Application();
        $locator = $application->getHandlerLocator();
        $this->assertInstanceOf(DefaultHandlerLocator::class, $locator);
    }

    public function testConstructorAcceptsAnyIHandlerLocator(): void
    {
        $locator = $this->createStub(IHandlerLocator::class);
        $application = new Application($locator);
        $this->assertSame($locator, $application->getHandlerLocator());
    }

    public function testAddRouteCallsHandlerLocatorAddRoute(): void
    {
        $route = $this->createStub(IRoute::class);
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

    public function testAddRouteAcceptsRequestHandlerInterface(): void
    {
        $route = $this->createStub(IRoute::class);
        $handler = $this->createStub(RequestHandlerInterface::class);
        $locator = $this->createMock(IHandlerLocator::class);
        $locator
            ->expects($this->once())
            ->method('addRoute')
            ->with(Verb::GET, $route, $handler);

        $application = new Application($locator);
        $application->addRoute(Verb::GET, $route, $handler);
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
            ->with($verb, TokenizedRoute::fromPath($route), $callable);

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
            ->with($verb, TokenizedRoute::fromPath($route), $callable);

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
            ->with($verb, TokenizedRoute::fromPath($route), $callable);

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
            ->with($verb, TokenizedRoute::fromPath($route), $callable);

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
            ->with($verb, TokenizedRoute::fromPath($route), $callable);

        $application = new Application($locator);
        $application->delete($route, $callable);
    }

    public function testHandleAttachesRouteParamsAttributeToRequest(): void
    {
        $params = ['id' => '42'];
        $response = $this->createStub(ResponseInterface::class);

        $request = $this->createMock(ServerRequestInterface::class);
        $requestWithAttr = $this->createStub(ServerRequestInterface::class);

        $request->expects($this->once())
            ->method('withAttribute')
            ->with(Application::ROUTE_PARAMS_ATTRIBUTE, $params)
            ->willReturn($requestWithAttr);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($requestWithAttr)
            ->willReturn($response);

        $locator = $this->createMock(IHandlerLocator::class);
        $locator->expects($this->once())
            ->method('locate')
            ->with($request)
            ->willReturn(new RouteMatch($handler, $params));

        $application = new Application($locator);
        $result = $application->handle($request);
        $this->assertSame($response, $result);
    }

    public function testHandleAttachesEmptyParamsForStaticRoute(): void
    {
        $response = $this->createStub(ResponseInterface::class);

        $request = $this->createMock(ServerRequestInterface::class);
        $requestWithAttr = $this->createStub(ServerRequestInterface::class);

        $request->expects($this->once())
            ->method('withAttribute')
            ->with(Application::ROUTE_PARAMS_ATTRIBUTE, [])
            ->willReturn($requestWithAttr);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($requestWithAttr)
            ->willReturn($response);

        $locator = $this->createStub(IHandlerLocator::class);
        $locator->method('locate')->willReturn(new RouteMatch($handler));

        $application = new Application($locator);
        $application->handle($request);
    }

    public function testMiddlewareReceivesRequestWithRouteParamsAttribute(): void
    {
        $params = ['id' => '42'];
        $response = $this->createStub(ResponseInterface::class);

        $request = $this->createStub(ServerRequestInterface::class);
        $requestWithAttr = $this->createStub(ServerRequestInterface::class);

        $request->method('withAttribute')
            ->with(Application::ROUTE_PARAMS_ATTRIBUTE, $params)
            ->willReturn($requestWithAttr);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())
            ->method('process')
            ->with($requestWithAttr, $this->isInstanceOf(RequestHandlerInterface::class))
            ->willReturn($response);

        $handler = $this->createStub(RequestHandlerInterface::class);
        $locator = $this->createStub(IHandlerLocator::class);
        $locator->method('locate')->willReturn(new RouteMatch($handler, $params));

        $application = new Application($locator);
        $application->add($middleware);

        $application->handle($request);
    }

    public function testGetRouteParamsHelperReturnsAttribute(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')
            ->with(Application::ROUTE_PARAMS_ATTRIBUTE)
            ->willReturn(['id' => '42']);

        $this->assertSame(['id' => '42'], Application::getRouteParams($request));
    }

    public function testGetRouteParamsHelperReturnsEmptyArrayWhenAttributeMissing(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')
            ->with(Application::ROUTE_PARAMS_ATTRIBUTE)
            ->willReturn(null);

        $this->assertSame([], Application::getRouteParams($request));
    }

    public function testMiddlewareExceptionHandling(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('withAttribute')->willReturnSelf();

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())
            ->method('process')
            ->willThrowException(new \RuntimeException('Middleware error'));

        $locator = $this->createMock(IHandlerLocator::class);
        $locator->expects($this->once())
            ->method('locate')
            ->with($request)
            ->willReturn(new RouteMatch($handler));

        $application = new Application($locator);
        $application->add($middleware);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Middleware error');
        $application->handle($request);
    }

    public function testMultipleMiddlewareExecutionOrder(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('withAttribute')->willReturnSelf();
        $response = $this->createStub(ResponseInterface::class);
        $executionOrder = [];

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->willReturnCallback(function () use (&$executionOrder, $response) {
                $executionOrder[] = 'handler';
                return $response;
            });

        $middleware1 = $this->createMock(MiddlewareInterface::class);
        $middleware1->expects($this->once())
            ->method('process')
            ->willReturnCallback(function ($req, $handler) use (&$executionOrder) {
                $executionOrder[] = 'middleware1-before';
                $response = $handler->handle($req);
                $executionOrder[] = 'middleware1-after';
                return $response;
            });

        $middleware2 = $this->createMock(MiddlewareInterface::class);
        $middleware2->expects($this->once())
            ->method('process')
            ->willReturnCallback(function ($req, $handler) use (&$executionOrder) {
                $executionOrder[] = 'middleware2-before';
                $response = $handler->handle($req);
                $executionOrder[] = 'middleware2-after';
                return $response;
            });

        $middleware3 = $this->createMock(MiddlewareInterface::class);
        $middleware3->expects($this->once())
            ->method('process')
            ->willReturnCallback(function ($req, $handler) use (&$executionOrder) {
                $executionOrder[] = 'middleware3-before';
                $response = $handler->handle($req);
                $executionOrder[] = 'middleware3-after';
                return $response;
            });

        $locator = $this->createMock(IHandlerLocator::class);
        $locator->expects($this->once())
            ->method('locate')
            ->willReturn(new RouteMatch($handler));

        $application = new Application($locator);
        $application->add($middleware1);
        $application->add($middleware2);
        $application->add($middleware3);

        $result = $application->handle($request);

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

    public function testMiddlewareCanShortCircuit(): void
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('withAttribute')->willReturnSelf();
        $shortCircuitResponse = $this->createStub(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->never())->method('handle');

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())
            ->method('process')
            ->willReturn($shortCircuitResponse);

        $locator = $this->createMock(IHandlerLocator::class);
        $locator->expects($this->once())
            ->method('locate')
            ->willReturn(new RouteMatch($handler));

        $application = new Application($locator);
        $application->add($middleware);

        $result = $application->handle($request);
        $this->assertSame($shortCircuitResponse, $result);
    }

    // Named routes / urlFor

    public function testUrlForRendersStaticNamedRoute(): void
    {
        $app = new Application();
        $app->get('/users/profile', fn () => null, name: 'user.profile');

        $this->assertSame('/users/profile', $app->urlFor('user.profile'));
    }

    public function testUrlForRendersParameterizedNamedRoute(): void
    {
        $app = new Application();
        $app->get('/users/{id}', fn () => null, name: 'user.show');

        $this->assertSame('/users/42', $app->urlFor('user.show', ['id' => '42']));
    }

    public function testUrlForUrlEncodesParameters(): void
    {
        $app = new Application();
        $app->get('/search/{q}', fn () => null, name: 'search');

        $this->assertSame('/search/hello%20world', $app->urlFor('search', ['q' => 'hello world']));
    }

    public function testUrlForThrowsUnknownRouteExceptionForUnregisteredName(): void
    {
        $app = new Application();

        $this->expectException(UnknownRouteException::class);
        $this->expectExceptionMessage('user.show');
        $app->urlFor('user.show');
    }

    public function testUrlForPropagatesMissingParameterException(): void
    {
        $app = new Application();
        $app->get('/users/{id}', fn () => null, name: 'user.show');

        $this->expectException(MissingRouteParameterException::class);
        $app->urlFor('user.show');
    }

    public function testUrlForThrowsForNonRenderableNamedRoute(): void
    {
        $route = $this->createStub(IRoute::class);

        $app = new Application();
        $app->addRoute(Verb::GET, $route, fn () => null, name: 'custom');

        $this->expectException(RouteNotRenderableException::class);
        $this->expectExceptionMessage('custom');
        $app->urlFor('custom');
    }

    public function testNamedRoutesWorkAcrossAllVerbs(): void
    {
        $app = new Application();
        $app->get('/g/{id}', fn () => null, name: 'g');
        $app->post('/p/{id}', fn () => null, name: 'p');
        $app->put('/u/{id}', fn () => null, name: 'u');
        $app->delete('/d/{id}', fn () => null, name: 'd');
        $app->patch('/pa/{id}', fn () => null, name: 'pa');

        $this->assertSame('/g/1', $app->urlFor('g', ['id' => '1']));
        $this->assertSame('/p/2', $app->urlFor('p', ['id' => '2']));
        $this->assertSame('/u/3', $app->urlFor('u', ['id' => '3']));
        $this->assertSame('/d/4', $app->urlFor('d', ['id' => '4']));
        $this->assertSame('/pa/5', $app->urlFor('pa', ['id' => '5']));
    }

    public function testRoutesWithoutNameAreNotInTheUrlForRegistry(): void
    {
        $app = new Application();
        $app->get('/users/{id}', fn () => null);  // no name

        $this->expectException(UnknownRouteException::class);
        $app->urlFor('whatever');
    }

    public function testNameCanBeReusedForLastRegistration(): void
    {
        $app = new Application();
        $app->get('/old/{id}', fn () => null, name: 'show');
        $app->get('/new/{id}', fn () => null, name: 'show');

        $this->assertSame('/new/42', $app->urlFor('show', ['id' => '42']));
    }

    // Route caching

    public function testCacheFileIsCreatedAfterHandlingFirstRequest(): void
    {
        $app = new Application(cacheFile: $this->cacheFile);
        $app->get('/users/{id}', fn () => null);
        $app->get('/posts/{year}/{slug}', fn () => null);

        // Trigger persist via handle(). The locator will throw because no
        // route matches '/__ccglabs_test_cache_trigger__'; that's fine —
        // persist runs before locate.
        try {
            $app->handle($this->makeHandledRequest());
        } catch (\Throwable) {
            // Expected — no route matches the trigger path.
        }

        $this->assertFileExists($this->cacheFile);
        $loaded = include $this->cacheFile;
        $this->assertArrayHasKey('/users/{id}', $loaded);
        $this->assertArrayHasKey('/posts/{year}/{slug}', $loaded);
        $this->assertSame(['', 'users', '{id}'], $loaded['/users/{id}']);
    }

    public function testCacheFalseDisablesFileCreation(): void
    {
        $app = new Application(cacheFile: false);
        $app->get('/users/{id}', fn () => null);

        try {
            $app->handle($this->makeHandledRequest());
        } catch (\Throwable) {
        }

        $this->assertFileDoesNotExist($this->cacheFile);
    }

    public function testCacheFailureIsNonFatal(): void
    {
        $app = new Application(cacheFile: '/proc/self/nonexistent/cache.php');
        $app->get('/users/{id}', fn () => null);

        try {
            $app->handle($this->makeHandledRequest());
        } catch (\CCGLabs\Router\Exceptions\RouteHandlerNotFoundException) {
        }

        $this->expectNotToPerformAssertions();
    }

    public function testIRouteObjectsAreNotCached(): void
    {
        $route = $this->createStub(IRoute::class);
        $route->method('matches')->willReturn(null);

        $app = new Application(cacheFile: $this->cacheFile);
        $app->addRoute(Verb::GET, $route, fn () => null);

        try {
            $app->handle($this->makeHandledRequest());
        } catch (\Throwable) {
        }

        $loaded = file_exists($this->cacheFile) ? include $this->cacheFile : [];
        $this->assertSame([], $loaded);
    }

    public function testApplicationConsumesPrePopulatedCache(): void
    {
        $cache = new RouteCache($this->cacheFile);
        $cache->record('/users/{id}', ['', 'users', '{id}']);
        $cache->persist();
        $contentBefore = file_get_contents($this->cacheFile);

        $app = new Application(cacheFile: $this->cacheFile);
        $app->get('/users/{id}', fn () => null);
        try {
            $app->handle($this->makeHandledRequest());
        } catch (\Throwable) {
        }

        $this->assertSame($contentBefore, file_get_contents($this->cacheFile));
    }

    public function testRemovedRoutesArePrunedFromCacheOnReregistration(): void
    {
        $app1 = new Application(cacheFile: $this->cacheFile);
        $app1->get('/a', fn () => null);
        $app1->get('/b', fn () => null);
        try {
            $app1->handle($this->makeHandledRequest());
        } catch (\Throwable) {
        }

        $this->assertArrayHasKey('/a', include $this->cacheFile);
        $this->assertArrayHasKey('/b', include $this->cacheFile);

        $app2 = new Application(cacheFile: $this->cacheFile);
        $app2->get('/a', fn () => null);
        try {
            $app2->handle($this->makeHandledRequest());
        } catch (\Throwable) {
        }

        $loaded = include $this->cacheFile;
        $this->assertArrayHasKey('/a', $loaded);
        $this->assertArrayNotHasKey('/b', $loaded);
    }

    public function testMiddlewareCanModifyRequest(): void
    {
        $originalRequest = $this->createStub(ServerRequestInterface::class);
        $originalRequest->method('withAttribute')->willReturnSelf();
        $modifiedRequest = $this->createStub(ServerRequestInterface::class);
        $response = $this->createStub(ResponseInterface::class);

        $handler = $this->createMock(RequestHandlerInterface::class);
        $handler->expects($this->once())
            ->method('handle')
            ->with($modifiedRequest)
            ->willReturn($response);

        $middleware = $this->createMock(MiddlewareInterface::class);
        $middleware->expects($this->once())
            ->method('process')
            ->willReturnCallback(fn ($req, $handler) => $handler->handle($modifiedRequest));

        $locator = $this->createMock(IHandlerLocator::class);
        $locator->expects($this->once())
            ->method('locate')
            ->willReturn(new RouteMatch($handler));

        $application = new Application($locator);
        $application->add($middleware);

        $result = $application->handle($originalRequest);
        $this->assertSame($response, $result);
    }
}
