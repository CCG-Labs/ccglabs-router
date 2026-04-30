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