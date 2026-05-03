<?php

declare(strict_types=1);

namespace CCGLabs\Router;

use CCGLabs\Router\Exceptions\MissingRouteParameterException;

/**
 * An IRoute that can render itself back to a URL path given a set of
 * parameter values.
 *
 * IRoute implementations that opt into URL generation (used by
 * Application::urlFor()) implement this sub-interface. Implementations
 * that do not are still usable for routing but cannot be used as named
 * routes for URL generation.
 */
interface IRenderableRoute extends IRoute
{
    /**
     * Renders this route to a URL path with the given parameters substituted in.
     *
     * Implementations that extract path parameters via rawurldecode() in
     * matches() should apply rawurlencode() to substituted values here so
     * that the encode/decode pair round-trips cleanly.
     *
     * @param array<string, string|int|float|\Stringable> $params Parameter values
     *     keyed by parameter name. Extra keys (not declared in the route pattern)
     *     are ignored.
     * @return string The rendered URL path.
     * @throws MissingRouteParameterException If a parameter declared in the
     *     route pattern is not present in $params.
     */
    public function render(array $params = []): string;
}
