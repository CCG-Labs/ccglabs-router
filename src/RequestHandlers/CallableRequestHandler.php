<?php

/**
 * This file contains CCGLabs\Router\RequestHandlers\CallableRequestHandler.
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

namespace CCGLabs\Router\RequestHandlers;

use Closure;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * CallableRequestHandler turns a simple PHP callable into a
 * RequestHandlerInterface.
 *
 * The CallableRequestHandler class is a wrapper that allows PHP callables
 * to easily function as request handlers.
 *
 * Example:
 *
 * $router->get(
 *     '/user',
 *     fn (ServerRequestInterface $request) => $model->getUsers($request->getParameter('id')
 * );
 */
class CallableRequestHandler implements RequestHandlerInterface
{
    /**
     * The Closure wrapping the handler function.
     */
    protected Closure $handlerFunction;

    /**
     * Creates a new CallableRequesthandler which calls the given
     * function to handle the request.
     */
    public function __construct(
        callable $handlerFunction
    ) {
        $this->handlerFunction = Closure::fromCallable($handlerFunction);
    }

    /**
     * Handles the request by passing the request to the callable.
     *
     * @inheritdoc
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return ($this->handlerFunction)($request);
    }
}
