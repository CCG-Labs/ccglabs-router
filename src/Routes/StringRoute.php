<?php

/**
 * This file contains CCGLabs\Router\Routes\StringRoute
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

namespace CCGLabs\Router\Routes;

use CCGLabs\Router\IRoute;

/**
 * A StringRoute is a single route which matches when the request path is
 * identical to the route string.
 */
class StringRoute implements IRoute
{
    /**
     * Creates a new StringRoute.
     */
    public function __construct(private string $route)
    {
    }

    /**
     * Returns an empty array if the path is identical to the route, otherwise null.
     */
    public function matches(string $path): ?array
    {
        return $path === $this->route ? [] : null;
    }
}
