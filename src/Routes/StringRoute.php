<?php

declare(strict_types=1);

namespace CCGLabs\Router\Routes;

use CCGLabs\Router\IRenderableRoute;

/**
 * A StringRoute is a single route which matches when the request path is
 * identical to the route string.
 */
class StringRoute implements IRenderableRoute
{
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

    /**
     * Returns the route string verbatim. Provided parameters are ignored
     * because StringRoute paths have no substitution slots.
     */
    public function render(array $params = []): string
    {
        return $this->route;
    }
}
