<?php

/**
 * This file contains CCGLabs\Router\HTTP\Verb.
 *
 * @author Brian Reich <brian@brianreich.dev>
 * @copyright Copyright (C) 2025 Brian Reich
 * @since 2025/09/01
 */

declare(strict_types=1);

namespace CCGLabs\Router\HTTP;

/**
 * Legal HTTP request verbs.
 */
enum Verb: string
{
    case GET = 'GET';
    case POST = 'POST';
    case PUT = 'PUT';
    case DELETE = 'DELETE';
    case PATCH = 'PATCH';
    case HEAD = 'HEAD';
    case TRACE = 'TRACE';
    case CONNECT = 'CONNECT';
}
