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
use CCGLabs\Router\RouteMatch;
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
        // If a user registers two routes with the same name, the most
        // recent one wins. This matches user expectation: re-registering
        // a name overrides.
        $app = new Application();
        $app->get('/old/{id}', fn () => null, name: 'show');
        $app->get('/new/{id}', fn () => null, name: 'show');

        $this->assertSame('/new/42', $app->urlFor('show', ['id' => '42']));
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
