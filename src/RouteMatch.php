<?php

declare(strict_types=1);

namespace CCGLabs\Router;

use Psr\Http\Server\RequestHandlerInterface;

/**
 * The result of locating a handler for a request.
 *
 * Created by IHandlerLocator::locate() and consumed by Application::handle().
 * Immutable: parameters are extracted at match time and never mutated thereafter.
 */
final readonly class RouteMatch
{
    /**
     * @param RequestHandlerInterface $handler The handler that should process the request.
     * @param array<string, string>   $params  Decoded parameters extracted from the matched
     *     route path, keyed by parameter name. Empty for routes with no parameters.
     */
    public function __construct(
        public RequestHandlerInterface $handler,
        public array $params = [],
    ) {
    }
}
