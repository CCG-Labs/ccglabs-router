<?php

declare(strict_types=1);

namespace CCGLabs\Router\Exceptions;

use Exception;

/**
 * Thrown when URL generation is requested but a required route parameter
 * is not supplied in the parameter array.
 */
class MissingRouteParameterException extends Exception
{
}
