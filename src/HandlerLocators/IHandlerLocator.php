<?php

/**
 * This file contains CCGLabs\Router\HandlerLocators\IHandlerLocator
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

use CCGLabs\Router\HTTP\Verb;
use CCGLabs\Router\IRoute;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * IHandlerLocator is responsible for matching a request to a handler.
 */
interface IHandlerLocator
{
    /**
     * Returns the request handler that matches the request.
     *
     * @param RequestInterface The request to find a handler for.
     * @return RequestHandlerInterface The handler that matches the request.
     *
     */
    public function locate(RequestInterface $request): RequestHandlerInterface;

    /**
     * Adds a route that can be located by this IHandlerLocator.
     *
     * @param Verb $verb The HTTP verb of the request.
     * @param IRoute $route The route to match.
     * @param callable $handler The function to invoke to handle the request.
     * @return self
     */
    public function addRoute(Verb $verb, IRoute $route, callable $handler): self;
}
