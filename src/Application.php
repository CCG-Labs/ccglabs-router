<?php

declare(strict_types=1);

namespace CCGLabs\Router;

use CCGLabs\Router\HandlerLocators\DefaultHandlerLocator;
use CCGLabs\Router\HandlerLocators\IHandlerLocator;
use CCGLabs\Router\HTTP\Verb;
use CCGLabs\Router\Routes\TokenizedRoute;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Application is the application front controller.
 *
 * The Application defines routes and dispatches requests to handlers. After
 * locating the handler for a request, Application attaches the route's
 * extracted parameters to the request as the ROUTE_PARAMS_ATTRIBUTE attribute
 * before invoking the middleware chain. Handlers and middleware can read
 * route parameters via Application::getRouteParams($request).
 */
class Application implements RequestHandlerInterface
{
    /**
     * Name of the request attribute under which the router stores parameters
     * extracted from the matched route path.
     */
    public const ROUTE_PARAMS_ATTRIBUTE = 'route_params';

    /** @var MiddlewareInterface[] */
    protected array $middlewares = [];

    public function __construct(
        private IHandlerLocator $handlerLocator = new DefaultHandlerLocator()
    ) {
    }

    public function getHandlerLocator(): IHandlerLocator
    {
        return $this->handlerLocator;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $match = $this->getHandlerLocator()->locate($request);
        $request = $request->withAttribute(self::ROUTE_PARAMS_ATTRIBUTE, $match->params);

        if (empty($this->middlewares)) {
            return $match->handler->handle($request);
        }

        $handler = $match->handler;
        for ($index = count($this->middlewares) - 1; $index >= 0; $index--) {
            $middleware = $this->middlewares[$index];
            $nextHandler = $handler;
            $handler = new class ($middleware, $nextHandler) implements RequestHandlerInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $handler,
                ) {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    return $this->middleware->process($request, $this->handler);
                }
            };
        }

        return $handler->handle($request);
    }

    /**
     * Convenience accessor for parameters extracted by the router from
     * the matched route path. Returns an empty array if the request was
     * not dispatched through Application::handle().
     *
     * @return array<string, string>
     */
    public static function getRouteParams(ServerRequestInterface $request): array
    {
        return $request->getAttribute(self::ROUTE_PARAMS_ATTRIBUTE) ?? [];
    }

    public function get(string $route, callable|RequestHandlerInterface $handler): self
    {
        return $this->addRoute(Verb::GET, $route, $handler);
    }

    public function post(string $route, callable|RequestHandlerInterface $handler): self
    {
        return $this->addRoute(Verb::POST, $route, $handler);
    }

    public function patch(string $route, callable|RequestHandlerInterface $handler): self
    {
        return $this->addRoute(Verb::PATCH, $route, $handler);
    }

    public function put(string $route, callable|RequestHandlerInterface $handler): self
    {
        return $this->addRoute(Verb::PUT, $route, $handler);
    }

    public function delete(string $route, callable|RequestHandlerInterface $handler): self
    {
        return $this->addRoute(Verb::DELETE, $route, $handler);
    }

    public function addRoute(
        Verb $verb,
        string|IRoute $route,
        callable|RequestHandlerInterface $handler
    ): self {
        if (is_string($route)) {
            $route = TokenizedRoute::fromPath($route);
        }

        $this->getHandlerLocator()->addRoute($verb, $route, $handler);
        return $this;
    }

    public function add(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }
}
