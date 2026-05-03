<?php

declare(strict_types=1);

namespace CCGLabs\Router\Exceptions;

use Exception;

/**
 * Thrown when a route name is referenced that has not been registered.
 */
class UnknownRouteException extends Exception
{
}
