# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is a PHP routing library (ccglabs/router) that provides a simple router for applications. It uses PHP 8 features including enums and follows PSR-4 autoloading standards.

## Architecture

- **Main Router**: `src/Application.php` - Core routing class that handles route registration for different HTTP verbs
- **HTTP Verbs**: `src/HTTP/Verb.php` - PHP enum defining supported HTTP methods (GET, POST, PUT, DELETE, PATCH, HEAD, TRACE, CONNECT)
- **Namespace Structure**: 
  - Root namespace: `CCGLabs\Router`
  - HTTP-related: `CCGLabs\Router\HTTP`

The router uses a simple array-based route storage system organized by HTTP verb, with callable handlers for each route.

## API Stability (v3.0+)

The public API is considered stable as of v3.0. Avoid changes that would
require a major version bump under semver.

**Stable surface:**

- All `public` members of classes and interfaces in `src/`
- The values of public constants (e.g., `Application::ROUTE_PARAMS_ATTRIBUTE`)
- Documented runtime behavior (e.g., route parameters are URL-decoded with
  `rawurldecode()` and attached to the request before middleware runs)
- Existing identifier names, including the `I*` interface prefix

**Prefer when proposing changes:**

- Adding new methods, classes, or constants
- Internal refactors that do not change public behavior
- Widening parameter types or narrowing return types in covariant ways

**Treat as breaking (avoid unless explicitly approved):**

- Renaming or removing any public identifier
- Narrowing parameter types or widening return types
- Changing the meaning of arguments or return values
- Changing the value of public constants
- Style-level renames (e.g., `IRoute` → `RouteInterface`)

If a breaking change looks genuinely necessary, surface it explicitly as
a tradeoff and confirm with the maintainer before implementing. Cosmetic
improvements are not sufficient justification.

## Development Commands

```bash
# Install dependencies
composer install

# Run tests
./vendor/bin/phpunit tests/

# Run a specific test file
./vendor/bin/phpunit tests/CCGLabs/Router/HTTP/VerbTest.php

# Update dependencies
composer update
```

## Testing

- PHPUnit 12 is used for testing
- Test files are located in `tests/` directory mirroring the source structure
- Test namespace follows the source namespace pattern