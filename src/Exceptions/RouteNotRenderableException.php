<?php

declare(strict_types=1);

namespace CCGLabs\Router\Exceptions;

use Exception;

/**
 * Thrown when URL generation is requested for a named route whose IRoute
 * implementation does not implement IRenderableRoute.
 */
class RouteNotRenderableException extends Exception
{
}
