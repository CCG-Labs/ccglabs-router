# ccglabs/router

[![CI](https://github.com/CCG-Labs/ccglabs-router/workflows/CI/badge.svg)](https://github.com/CCG-Labs/ccglabs-router/actions/workflows/ci.yml)
[![Security](https://github.com/CCG-Labs/ccglabs-router/workflows/Security/badge.svg)](https://github.com/CCG-Labs/ccglabs-router/actions/workflows/security.yml)
[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-blue.svg)](https://www.php.net)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A simple PHP router for simple PHP projects

## Features

- `CCGLabs\Router\Application` - Front controller where routes and route groups are registered.


## Installation

Install into your project:

```bash
composer require ccglabs/router
```

### Verify Installation

After installation, verify the router is working by creating a test file:

```php
// test-router.php
<?php
require 'vendor/autoload.php';

use CCGLabs\Router\Application;
use CCGLabs\Router\HandlerLocators\DefaultHandlerLocator;

// If this runs without errors, the router is installed correctly
$app = new Application(new DefaultHandlerLocator());
echo "✓ CCGLabs Router installed successfully!\n";
```

Run the test:
```bash
php test-router.php
```

## Usage/Examples

### Basic Routing

```php
<?php
require 'vendor/autoload.php';

use CCGLabs\Router\Application;
use CCGLabs\Router\HandlerLocators\DefaultHandlerLocator;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\ServerRequest;

// Create the application
$app = new Application(new DefaultHandlerLocator());

// Define routes
$app->get('/hello', function($request) {
    return new Response(200, [], 'Hello, World!');
});

// Route with parameters
$app->get('/user/{id}', function($request) {
    // The router attaches matched parameters to the request.
    $params = Application::getRouteParams($request);
    $userId = $params['id'];

    return new Response(200, [], "User ID: $userId");
});

// Handle multiple HTTP methods
$app->post('/users', function($request) {
    return new Response(201, [], 'User created');
});

$app->put('/users/{id}', function($request) {
    return new Response(200, [], 'User updated');
});

$app->delete('/users/{id}', function($request) {
    return new Response(204);
});

// Handle the request
$request = ServerRequest::fromGlobals();
try {
    $response = $app->handle($request);
} catch (\CCGLabs\Router\Exceptions\RouteHandlerNotFoundException $e) {
    $response = new Response(404, [], 'Not Found');
}

// Send the response
http_response_code($response->getStatusCode());
foreach ($response->getHeaders() as $name => $values) {
    foreach ($values as $value) {
        header("$name: $value", false);
    }
}
echo $response->getBody();
```

### Using Middleware

```php
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

class AuthMiddleware implements MiddlewareInterface {
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Check authentication
        $token = $request->getHeaderLine('Authorization');

        if (!$this->isValidToken($token)) {
            return new Response(401, [], 'Unauthorized');
        }

        // Pass to next handler
        return $handler->handle($request);
    }

    private function isValidToken(string $token): bool {
        // Your authentication logic here
        return $token === 'Bearer valid-token';
    }
}

// Add middleware to the application
$app->add(new AuthMiddleware());
```

### Route Parameters

Route parameters are attached to the request as a single attribute, keyed by
the constant `Application::ROUTE_PARAMS_ATTRIBUTE`. The static helper
`Application::getRouteParams()` reads it for you and returns an empty array
when the attribute is missing.

Parameter values are URL-decoded with `rawurldecode()` before being delivered
to the handler — a request to `/search/hello%20world` matching
`/search/{query}` yields `['query' => 'hello world']`.

```php
// Multiple parameters in a route
$app->get('/posts/{year}/{month}/{slug}', function($request) {
    $params = Application::getRouteParams($request);

    $year = $params['year'];
    $month = $params['month'];
    $slug = $params['slug'];

    return new Response(200, [], "Post: $year/$month/$slug");
});

// Parameters can appear anywhere in the route
$app->get('/{lang}/products/{category}', function($request) {
    $params = Application::getRouteParams($request);

    $language = $params['lang'];
    $category = $params['category'];

    return new Response(200, [], "Language: $language, Category: $category");
});

// Direct attribute access also works:
$app->get('/user/{id}', function($request) {
    $params = $request->getAttribute(Application::ROUTE_PARAMS_ATTRIBUTE);
    return new Response(200, [], "User: {$params['id']}");
});
```

### Named Routes and URL Generation

Routes can be given a name at registration time. Named routes can be
referenced by `Application::urlFor()` to build URL paths from parameter
values. This is useful for generating links and redirects without
hard-coding URL strings.

```php
$app->get('/users/{id}', $userShowHandler, name: 'user.show');
$app->post('/users', $userCreateHandler, name: 'user.create');

// Build URLs by name + params:
$url = $app->urlFor('user.show', ['id' => 42]);
// → '/users/42'

$url = $app->urlFor('search', ['q' => 'hello world']);
// → '/search/hello%20world'
```

Parameter values are URL-encoded with `rawurlencode()` so that
`urlFor()` and the router's path matching round-trip cleanly. Extra
parameters are ignored. Missing parameters throw
`MissingRouteParameterException`.

`urlFor()` throws `UnknownRouteException` when the name was never
registered, and `RouteNotRenderableException` when the named route's
`IRoute` implementation does not also implement `IRenderableRoute`
(the built-in `TokenizedRoute` and `StringRoute` both do).

## Migrating from 2.x

Version 3.0 changes how route parameters reach handlers. The previous
documented pattern (`$request->getAttribute('route')->getParameters()`)
never actually worked — the router did not attach the matched route to
the request. 3.0 fixes this by attaching the extracted parameters
directly to the request.

**Updating handler code:**

```php
// 2.x — broken, never worked
$route = $request->getAttribute('route');
$userId = $route->getParameters()['id'];

// 3.0
$params = Application::getRouteParams($request);
$userId = $params['id'];
```

**Other breaking changes:**

- `IRoute::matches()` now returns `array<string,string>|null` (parameters on
  match, `null` on miss) instead of `bool`.
- `IHandlerLocator::locate()` now returns `RouteMatch` instead of
  `RequestHandlerInterface`. Custom locator implementations must be updated.
- `IHandlerLocator::addRoute()` now accepts `callable|RequestHandlerInterface`.
- The `IParameterizedRoute` interface is removed. Its `getParameters()`
  contract has been folded into `IRoute::matches()`.
- Path parameters are now URL-decoded via `rawurldecode()` before delivery
  to handlers. A request to `/search/hello%20world` matching
  `/search/{query}` previously yielded `'hello%20world'`; it now yields
  `'hello world'`.

## Running Tests

To run tests, run the following command

```bash
composer test
```


## License

[MIT](https://choosealicense.com/licenses/mit/)

## Authors

- [@therealbrianreich](https://www.github.com/therealbrianreich)
