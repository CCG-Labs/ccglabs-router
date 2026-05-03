<?php

declare(strict_types=1);

namespace CCGLabs\Router;

use CCGLabs\Router\Exceptions\RouteNotRenderableException;
use CCGLabs\Router\Exceptions\UnknownRouteException;
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
 *
 * Routes registered with a $name argument can be referenced by that name
 * via Application::urlFor() to build URL paths from parameter values.
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

    /**
     * Routes that have been given a name at registration time, keyed by name.
     * Used by urlFor() to look up a route for URL generation.
     *
     * @var array<string, IRoute>
     */
    protected array $namedRoutes = [];

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

    /**
     * Builds a URL path from a previously named route and a set of parameter values.
     *
     * @param string $name The route name supplied at registration.
     * @param array<string, string|int|float|\Stringable> $params
     * @throws UnknownRouteException If $name was never registered.
     * @throws RouteNotRenderableException If the named route's IRoute
     *     implementation does not implement IRenderableRoute.
     * @throws \CCGLabs\Router\Exceptions\MissingRouteParameterException If
     *     the route declares a parameter not present in $params.
     */
    public function urlFor(string $name, array $params = []): string
    {
        if (! isset($this->namedRoutes[$name])) {
            throw new UnknownRouteException(sprintf(
                'No route registered with name "%s"',
                $name
            ));
        }

        $route = $this->namedRoutes[$name];

        if (! $route instanceof IRenderableRoute) {
            throw new RouteNotRenderableException(sprintf(
                'Route "%s" does not support URL generation; its IRoute '
                . 'implementation must also implement IRenderableRoute',
                $name
            ));
        }

        return $route->render($params);
    }

    public function get(string $route, callable|RequestHandlerInterface $handler, ?string $name = null): self
    {
        return $this->addRoute(Verb::GET, $route, $handler, $name);
    }

    public function post(string $route, callable|RequestHandlerInterface $handler, ?string $name = null): self
    {
        return $this->addRoute(Verb::POST, $route, $handler, $name);
    }

    public function patch(string $route, callable|RequestHandlerInterface $handler, ?string $name = null): self
    {
        return $this->addRoute(Verb::PATCH, $route, $handler, $name);
    }

    public function put(string $route, callable|RequestHandlerInterface $handler, ?string $name = null): self
    {
        return $this->addRoute(Verb::PUT, $route, $handler, $name);
    }

    public function delete(string $route, callable|RequestHandlerInterface $handler, ?string $name = null): self
    {
        return $this->addRoute(Verb::DELETE, $route, $handler, $name);
    }

    public function addRoute(
        Verb $verb,
        string|IRoute $route,
        callable|RequestHandlerInterface $handler,
        ?string $name = null,
    ): self {
        if (is_string($route)) {
            $route = TokenizedRoute::fromPath($route);
        }

        if ($name !== null) {
            $this->namedRoutes[$name] = $route;
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
