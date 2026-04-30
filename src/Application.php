<?php

/**
 * This file contains CCGLabs\Router\Application
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
 * The Application defines routes and makes routes to handlers.
 */
class Application implements RequestHandlerInterface
{
    /**
     * Collection of added middlewares.
     *
     * @var MiddlewareInterface[]
     */
    protected array $middlewares = [];

    /**
     * Creates a new Application.
     *
     * The constructor allows the caller to specify an IHandlerLocator, which
     * is a component responsible for mapping routes to handlers. If one is
     * not specified, the DefaultHandlerLocator is used.
     */
    public function __construct(
        private IHandlerLocator $handlerLocator = new DefaultHandlerLocator()
    ) {
    }

    public function getHandlerLocator(): IHandlerLocator
    {
        return $this->handlerLocator;
    }

    /**
     * Handles a request and produces a response.
     *
     * May call other collaborating code to generate the response.
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $requestHandler = $this->getHandlerLocator()->locate($request);

        // If there are no middlewares, then just handle the request.
        if (empty($this->middlewares)) {
            return $requestHandler->handle($request);
        }

        // Build the middleware chain by wrapping handlers from the inside out
        $handler = $requestHandler;
        for ($index = count($this->middlewares) - 1; $index >= 0; $index--) {
            $middleware = $this->middlewares[$index];
            $nextHandler = $handler;
            $handler = new class ($middleware, $nextHandler) implements RequestHandlerInterface {
                public function __construct(
                    private MiddlewareInterface $middleware,
                    private RequestHandlerInterface $handler
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

    public function get(string $route, callable $handler): self
    {
        return $this->addRoute(Verb::GET, $route, $handler);
    }

    public function post(string $route, callable $handler): self
    {
        return $this->addRoute(Verb::POST, $route, $handler);
    }

    public function patch(string $route, callable $handler): self
    {
        return $this->addRoute(Verb::PATCH, $route, $handler);
    }

    public function put(string $route, callable $handler): self
    {
        return $this->addRoute(Verb::PUT, $route, $handler);
    }

    public function delete(string $route, callable $handler): self
    {
        return $this->addRoute(Verb::DELETE, $route, $handler);
    }

    public function addRoute(Verb $verb, string|IRoute $route, callable $handler): self
    {
        $routeObject = $route;

        // If the route is specified as a string, automatically convert it to
        // a tokenized route.
        if (is_string($route)) {
            $routeObject = TokenizedRoute::fromPath($route);
        }

        $this->getHandlerLocator()->addRoute($verb, $routeObject, $handler);
        return $this;
    }

    public function add(MiddlewareInterface $middleware): self
    {
        $this->middlewares[] = $middleware;
        return $this;
    }
}
