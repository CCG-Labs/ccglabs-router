<?php

declare(strict_types=1);

namespace CCGLabs\Router\HandlerLocators;

use CCGLabs\Router\Exceptions\RouteHandlerNotFoundException;
use CCGLabs\Router\HTTP\Verb;
use CCGLabs\Router\IRoute;
use CCGLabs\Router\RequestHandlers\CallableRequestHandler;
use CCGLabs\Router\RouteMatch;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WeakMap;

/**
 * Maps requests to the route and handler that should process them.
 *
 * The DefaultHandlerLocator iterates registered routes for the request's
 * HTTP verb and returns a RouteMatch for the first matching route. If no
 * route matches, it throws a RouteHandlerNotFoundException.
 */
class DefaultHandlerLocator implements IHandlerLocator
{
    public const ERROR_BAD_VERB = 'Unrecognized HTTP verb: %s';
    public const ERROR_HANDLER_NOT_FOUND = 'Handler not found for %s';

    /**
     * Routes keyed by HTTP verb. Each value is a list of route/handler pairs
     * to be checked in registration order.
     *
     * @var WeakMap<Verb, array<array{route: IRoute, handler: RequestHandlerInterface}>>
     */
    protected WeakMap $routes;

    public function __construct()
    {
        $this->routes = new WeakMap();
    }

    public function addRoute(
        Verb $verb,
        IRoute $route,
        callable|RequestHandlerInterface $handler
    ): self {
        if (! isset($this->routes[$verb])) {
            $this->routes[$verb] = [];
        }

        if (is_callable($handler)) {
            $handler = new CallableRequestHandler($handler);
        }

        $this->routes[$verb][] = ['route' => $route, 'handler' => $handler];
        return $this;
    }

    public function locate(RequestInterface $request): RouteMatch
    {
        $verb = Verb::tryFrom($request->getMethod());

        if ($verb === null) {
            throw new InvalidArgumentException(sprintf(
                self::ERROR_BAD_VERB,
                $request->getMethod()
            ));
        }

        $path = $request->getUri()->getPath();

        if (! isset($this->routes[$verb])) {
            throw new RouteHandlerNotFoundException(
                $request,
                sprintf(self::ERROR_HANDLER_NOT_FOUND, $path)
            );
        }

        foreach ($this->routes[$verb] as $routeData) {
            $params = $routeData['route']->matches($path);
            if ($params !== null) {
                return new RouteMatch($routeData['handler'], $params);
            }
        }

        throw new RouteHandlerNotFoundException(
            $request,
            sprintf(self::ERROR_HANDLER_NOT_FOUND, $path)
        );
    }
}
