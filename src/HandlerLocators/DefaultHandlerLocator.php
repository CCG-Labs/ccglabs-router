<?php

/**
 * This file contains CCGLabs\Router\HandlerLocators\DefaultHandlerLocator
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

namespace CCGLabs\Router\HandlerLocators;

use CCGLabs\Router\Exceptions\RouteHandlerNotFoundException;
use CCGLabs\Router\HTTP\Verb;
use CCGLabs\Router\IRoute;
use CCGLabs\Router\RequestHandlers\CallableRequestHandler;
use Exception;
use InvalidArgumentException;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use WeakMap;

/**
 * Maps requests to the route the and handler that should process them.
 *
 * The DefaultHandlerLocator is a simple implementation of IHandlerLocator that
 * uses a hybrid WeakMap+array architecture for route storage. The outer layer
 * uses WeakMap keyed by HTTP verb, while inner storage uses arrays to prevent
 * route objects from being garbage collected. It matches incoming requests to
 * routes based on the HTTP verb and path, iterating through registered routes
 * until a match is found. If no match is found, it throws an exception.
 */
class DefaultHandlerLocator implements IHandlerLocator
{
    public const ERROR_BAD_VERB = 'Unrecognized HTTP verb: %s';
    public const ERROR_HANDLER_NOT_FOUND = 'Handler not found for %s';

    /**
     * Stores the routes and their associated handlers using a hybrid WeakMap+array
     * architecture. The outer WeakMap is keyed by HTTP verb (Verb enum objects)
     * with values being arrays of route-handler pairs. Arrays are used for inner
     * storage instead of nested WeakMaps to prevent route objects from being
     * prematurely garbage collected, which would cause "Handler not found" errors.
     * @var WeakMap<Verb, array<array{route: IRoute, handler: RequestHandlerInterface}>>
     */
    protected WeakMap $routes;

    /**
     * Creates a new DefaultHandlerLocator instance with no registered routes.
     */
    public function __construct()
    {
        $this->routes = new WeakMap();
    }

    /**
     * Registers a route and its associated handler for a specific HTTP verb.
     *
     * @param Verb $verb The HTTP verb for which the route is registered.
     * @param IRoute $route The route to be registered.
     * @param callable|RequestHandlerInterface $handler The handler to be invoked when the route is matched.
     */
    public function addRoute(Verb $verb, IRoute $route, callable|RequestHandlerInterface $handler): self
    {
        if (! isset($this->routes[$verb])) {
            $this->routes[$verb] = [];
        }

        // If the caller has provided a naked function or callable, wrap it in
        // a CallableRequestHandler instance.
        if (is_callable($handler)) {
            $handler = new CallableRequestHandler($handler);
        }

        $this->routes[$verb][] = ['route' => $route, 'handler' => $handler];
        return $this;
    }

    /**
     * Locates the appropriate handler for the given request and returns it.
     */
    public function locate(RequestInterface $request): RequestHandlerInterface
    {
        $verb = Verb::tryFrom($request->getMethod());

        // If request method is not a verb we recognize, then there is no match.
        if ($verb === null) {
            throw new InvalidArgumentException(sprintf(
                self::ERROR_BAD_VERB,
                $request->getMethod()
            ));
        }

        $path = $request->getUri()->getPath();

        // If the verb has no registered routes, then there is no match.
        if (! isset($this->routes[$verb])) {
            throw new RouteHandlerNotFoundException(
                $request,
                sprintf(self::ERROR_HANDLER_NOT_FOUND, $path)
            );
        }

        // Iterate through the routes for the given verb and check if any of
        // them match the request path. If a match is found, return the associated
        // handler. If no match is found, throw an exception.
        foreach ($this->routes[$verb] as $routeData) {
            if ($routeData['route']->matches($path)) {
                return $routeData['handler'];
            }
        }

        throw new RouteHandlerNotFoundException(
            $request,
            sprintf(self::ERROR_HANDLER_NOT_FOUND, $path)
        );
    }
}
