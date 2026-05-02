<?php

declare(strict_types=1);

namespace CCGLabs\Router\HandlerLocators;

use CCGLabs\Router\HTTP\Verb;
use CCGLabs\Router\IRoute;
use CCGLabs\Router\RouteMatch;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * IHandlerLocator is responsible for matching a request to a handler.
 */
interface IHandlerLocator
{
    /**
     * Locates the handler that matches the request, along with any
     * parameters extracted from the matched route path.
     *
     * @param RequestInterface $request The request to find a handler for.
     * @return RouteMatch The matched handler and decoded route parameters.
     * @throws \CCGLabs\Router\Exceptions\RouteHandlerNotFoundException When no route matches.
     */
    public function locate(RequestInterface $request): RouteMatch;

    /**
     * Adds a route that can be located by this IHandlerLocator.
     *
     * @param Verb $verb The HTTP verb of the request.
     * @param IRoute $route The route to match.
     * @param callable|RequestHandlerInterface $handler The function or handler
     *     to invoke to handle the request.
     */
    public function addRoute(
        Verb $verb,
        IRoute $route,
        callable|RequestHandlerInterface $handler
    ): self;
}
