<?php

declare(strict_types=1);

namespace CCGLabs\Router;

/**
 * An IRoute is a URI path into an application.
 */
interface IRoute
{
    /**
     * Tests whether the given path matches this route.
     *
     * On match, returns an associative array of extracted parameters keyed
     * by parameter name. Empty array for routes with no parameters.
     * On miss, returns null.
     *
     * Implementations that extract parameters from URL path segments must
     * apply rawurldecode() to parameter values before returning them.
     *
     * @param string $path The path to test.
     * @return array<string, string>|null Decoded parameters on match, null on miss.
     */
    public function matches(string $path): ?array;
}
