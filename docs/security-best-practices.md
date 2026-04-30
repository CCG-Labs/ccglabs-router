# Security Best Practices for CCGLabs Router

## Introduction

The CCGLabs Router is designed with a clear separation of concerns. The router's responsibility is limited to:
- Matching URL patterns to routes
- Extracting parameters from URLs
- Dispatching requests to the appropriate handler

Security validation of parameter **values** is intentionally NOT the router's responsibility. This document explains why and provides best practices for implementing security in your application.

## Separation of Responsibilities

### What the Router Validates

The router validates the **structure** of routes:
- ✅ Route pattern syntax is valid
- ✅ Parameter names are non-empty
- ✅ URL structure matches the defined pattern

### What the Router Does NOT Validate

The router does not validate the **content** of parameters:
- ❌ Path traversal sequences (../, ..\)
- ❌ SQL injection attempts
- ❌ XSS payloads
- ❌ File type restrictions
- ❌ Numeric ranges or formats

## Why Validation Belongs in Handlers

### 1. Context-Dependent Requirements

Different parameters require different validation based on how they're used:

```php
// File download - NEEDS path traversal protection
$app->get('/download/{filename}', function($request) {
    $filename = $request->getParameter('filename');

    // The handler knows this will access the filesystem
    if (str_contains($filename, '..') || str_contains($filename, '/')) {
        throw new SecurityException('Invalid filename');
    }

    return readfile('/safe/uploads/' . $filename);
});

// Database search - path traversal is irrelevant
$app->get('/search/{query}', function($request) {
    $query = $request->getParameter('query');

    // "../" in a search query is harmless
    // But SQL escaping IS important
    $stmt = $db->prepare('SELECT * FROM products WHERE name LIKE ?');
    $stmt->execute(['%' . $query . '%']);

    return $stmt->fetchAll();
});

// Navigation breadcrumb - might WANT to allow "../"
$app->get('/navigate/{path}', function($request) {
    $path = $request->getParameter('path');

    // Application might intentionally support relative navigation
    return generateBreadcrumb($path);
});
```

### 2. Domain-Specific Logic

Only the handler knows the business requirements:

```php
// User ID must be numeric
$app->get('/user/{id}', function($request) {
    $id = $request->getParameter('id');

    if (!ctype_digit($id)) {
        throw new ValidationException('User ID must be numeric');
    }

    return User::find($id);
});

// Slug can contain letters, numbers, and hyphens
$app->get('/article/{slug}', function($request) {
    $slug = $request->getParameter('slug');

    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        throw new ValidationException('Invalid article slug');
    }

    return Article::findBySlug($slug);
});
```

### 3. Industry Standard Practice

Major routing libraries follow this same principle:
- **Symfony Router**: Parameters are passed as-is to controllers
- **Laravel Router**: Validation happens in controllers or form requests
- **Express.js**: Middleware and route handlers validate parameters
- **FastRoute**: No parameter validation in the router

## Security Best Practices

### 1. File System Access

When parameters will be used to access files, always validate for path traversal:

```php
$app->get('/file/{path}', function($request) {
    $path = $request->getParameter('path');

    // Method 1: Reject any directory traversal
    if (str_contains($path, '..') || str_contains($path, '/') || str_contains($path, '\\')) {
        throw new SecurityException('Invalid file path');
    }

    // Method 2: Use realpath to resolve and validate
    $basePath = '/var/www/uploads';
    $fullPath = realpath($basePath . '/' . $path);

    if ($fullPath === false || !str_starts_with($fullPath, $basePath)) {
        throw new SecurityException('Invalid file path');
    }

    return readfile($fullPath);
});
```

### 2. Database Queries

Always use prepared statements, regardless of parameter source:

```php
$app->get('/product/{category}', function($request) use ($db) {
    $category = $request->getParameter('category');

    // NEVER do this:
    // $result = $db->query("SELECT * FROM products WHERE category = '$category'");

    // ALWAYS use prepared statements:
    $stmt = $db->prepare('SELECT * FROM products WHERE category = ?');
    $stmt->execute([$category]);

    return $stmt->fetchAll();
});
```

### 3. HTML Output

Escape parameters before outputting to HTML:

```php
$app->get('/profile/{username}', function($request) {
    $username = $request->getParameter('username');

    // Escape for HTML output
    $safeUsername = htmlspecialchars($username, ENT_QUOTES, 'UTF-8');

    return "<h1>Profile: $safeUsername</h1>";
});
```

### 4. Command Execution

Never pass parameters directly to system commands:

```php
$app->get('/convert/{filename}', function($request) {
    $filename = $request->getParameter('filename');

    // Validate strictly
    if (!preg_match('/^[a-zA-Z0-9_-]+\.(jpg|png)$/', $filename)) {
        throw new ValidationException('Invalid filename');
    }

    // Use escapeshellarg for extra safety
    $safeFilename = escapeshellarg($filename);

    exec("convert /uploads/$safeFilename /processed/$safeFilename");
});
```

## Validation Middleware

For common validations, consider using middleware:

```php
// Create validation middleware
class FileParameterValidator implements MiddlewareInterface {
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface {
        $route = $request->getAttribute('route');
        $params = $route->getParameters();

        if (isset($params['filename'])) {
            if (str_contains($params['filename'], '..') || str_contains($params['filename'], '/')) {
                throw new SecurityException('Invalid filename parameter');
            }
        }

        return $handler->handle($request);
    }
}

// Apply to specific routes
$app->add(new FileParameterValidator());
$app->get('/download/{filename}', $downloadHandler);
$app->get('/preview/{filename}', $previewHandler);
```

## Security Checklist

When implementing route handlers, ask yourself:

- [ ] **File System**: Will this parameter be used to access files? → Add path traversal protection
- [ ] **Database**: Will this parameter be used in queries? → Use prepared statements
- [ ] **HTML Output**: Will this parameter be displayed? → Escape for HTML
- [ ] **System Commands**: Will this parameter be used in commands? → Validate strictly and escape
- [ ] **Numeric IDs**: Should this parameter be numeric? → Validate type and range
- [ ] **Format Requirements**: Does this parameter have a specific format? → Use regex validation
- [ ] **Length Limits**: Should this parameter have length restrictions? → Check string length
- [ ] **Character Sets**: Should this parameter only contain certain characters? → Validate character set

## Example: Comprehensive Handler Validation

Here's an example of a well-validated route handler:

```php
$app->get('/api/v1/files/{userId}/{filename}', function($request) {
    $userId = $request->getParameter('userId');
    $filename = $request->getParameter('filename');

    // Validate user ID
    if (!ctype_digit($userId) || $userId < 1 || $userId > 999999) {
        throw new ValidationException('Invalid user ID');
    }

    // Validate filename
    if (strlen($filename) > 255) {
        throw new ValidationException('Filename too long');
    }

    if (!preg_match('/^[a-zA-Z0-9_-]+\.[a-zA-Z0-9]+$/', $filename)) {
        throw new ValidationException('Invalid filename format');
    }

    // Prevent path traversal
    if (str_contains($filename, '..') || str_contains($filename, '/') || str_contains($filename, '\\')) {
        throw new SecurityException('Path traversal attempt detected');
    }

    // Check file extension
    $allowedExtensions = ['jpg', 'png', 'pdf', 'doc', 'docx'];
    $extension = pathinfo($filename, PATHINFO_EXTENSION);
    if (!in_array(strtolower($extension), $allowedExtensions)) {
        throw new ValidationException('File type not allowed');
    }

    // Build safe path
    $basePath = '/var/www/user_files';
    $userPath = $basePath . '/' . $userId;
    $filePath = $userPath . '/' . $filename;

    // Verify final path is within bounds
    $realPath = realpath($filePath);
    if ($realPath === false || !str_starts_with($realPath, $userPath)) {
        throw new SecurityException('Invalid file path');
    }

    // Check file exists
    if (!file_exists($realPath)) {
        throw new NotFoundException('File not found');
    }

    // Return file
    return new FileResponse($realPath);
});
```

## Error Handling and Information Disclosure

### Router Exception Philosophy

The CCGLabs Router intentionally provides **detailed, informative exceptions** when routes cannot be matched or other errors occur. This is a deliberate architectural decision that follows industry standards.

### Why Detailed Exceptions Are Correct

```php
// Router throws detailed exceptions
throw new RouteHandlerNotFoundException($request, "Handler not found for /admin/users/{id}");

// This is GOOD because:
// 1. Developers can debug route matching issues
// 2. Logs contain useful troubleshooting information
// 3. Tests can verify specific error conditions
// 4. Applications can decide what to expose publicly
```

### Industry Standard Approach

Major routing libraries follow the same pattern:

- **Symfony Router**: Throws `ResourceNotFoundException` with full route details
- **Laravel Router**: Throws `NotFoundHttpException` with specific route information
- **Express.js**: Provides detailed errors to error middleware for filtering
- **FastRoute**: Returns specific "method not allowed" vs "not found" information

### Secure Error Handling in Applications

The **application layer** should decide what error information to expose:

#### Development Environment

```php
class ErrorHandler {
    public function handle(Exception $e): ResponseInterface {
        if ($e instanceof RouteHandlerNotFoundException) {
            // Show detailed error for debugging
            return new JsonResponse([
                'error' => 'Route Not Found',
                'details' => $e->getMessage(),
                'path' => $e->getRequest()->getUri()->getPath(),
                'method' => $e->getRequest()->getMethod(),
                'debug' => true
            ], 404);
        }
    }
}
```

#### Production Environment

```php
class ErrorHandler {
    public function handle(Exception $e): ResponseInterface {
        if ($e instanceof RouteHandlerNotFoundException) {
            // Log detailed error for ops team
            $this->logger->warning('Route not found', [
                'path' => $e->getRequest()->getUri()->getPath(),
                'method' => $e->getRequest()->getMethod(),
                'user_agent' => $e->getRequest()->getHeaderLine('User-Agent')
            ]);

            // Return generic error to user
            return new JsonResponse([
                'error' => 'Not Found',
                'message' => 'The requested resource was not found'
            ], 404);
        }
    }
}
```

#### Conditional Error Handling

```php
class EnvironmentAwareErrorHandler {
    public function __construct(
        private bool $isDebug,
        private LoggerInterface $logger
    ) {}

    public function handle(Exception $e): ResponseInterface {
        if ($e instanceof RouteHandlerNotFoundException) {
            // Always log for debugging/monitoring
            $this->logger->info('Route not found', [
                'path' => $e->getRequest()->getUri()->getPath(),
                'method' => $e->getRequest()->getMethod()
            ]);

            if ($this->isDebug) {
                // Development: show details
                return new JsonResponse([
                    'error' => $e->getMessage(),
                    'path' => $e->getRequest()->getUri()->getPath(),
                    'trace' => $e->getTraceAsString()
                ], 404);
            } else {
                // Production: generic message
                return new JsonResponse(['error' => 'Not Found'], 404);
            }
        }
    }
}
```

### Security Considerations for Error Handling

#### What Information Is Safe to Expose

**Generally Safe** (even in production):
- Generic error types ("Not Found", "Method Not Allowed")
- HTTP status codes
- General error categories

**Context-Dependent** (varies by application):
- Route paths (might be public knowledge)
- Parameter names (might be in documentation)
- HTTP methods tried

**Usually Sensitive** (avoid in production):
- Internal server paths
- Database connection details
- Full stack traces
- User session information
- Internal IP addresses

#### Error Logging Best Practices

```php
// Good: Structured logging with context
$this->logger->warning('Route not found', [
    'path' => $request->getUri()->getPath(),
    'method' => $request->getMethod(),
    'ip' => $request->getServerParams()['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $request->getHeaderLine('User-Agent'),
    'referer' => $request->getHeaderLine('Referer')
]);

// Avoid: Logging sensitive headers or request body
$this->logger->info('Request failed', [
    'full_request' => (string) $request, // Contains headers, body, auth tokens!
]);
```

### Configuration Example

```php
// Application configuration
class RouterConfig {
    public function createErrorHandler(): ErrorHandlerInterface {
        return new ErrorHandler(
            isDebug: $_ENV['APP_DEBUG'] ?? false,
            logger: $this->getLogger(),
            shouldExposeDetails: $_ENV['EXPOSE_ROUTE_ERRORS'] ?? false
        );
    }
}

// Usage in application
$app = new Application($handlerLocator);

try {
    $response = $app->handle($request);
} catch (RouteHandlerNotFoundException $e) {
    $response = $errorHandler->handle($e);
} catch (Exception $e) {
    $response = $errorHandler->handleGeneric($e);
}
```

### Testing Error Conditions

```php
public function testRouteNotFoundProvidesDetailedError(): void {
    $app = new Application();
    $request = new ServerRequest('GET', '/nonexistent');

    $this->expectException(RouteHandlerNotFoundException::class);
    $this->expectExceptionMessage('Handler not found for /nonexistent');

    $app->handle($request);
}

public function testErrorHandlerSanitizesProductionErrors(): void {
    $errorHandler = new ErrorHandler(isDebug: false);
    $exception = new RouteHandlerNotFoundException($request, 'Handler not found for /admin/secret');

    $response = $errorHandler->handle($exception);
    $body = json_decode($response->getBody()->getContents(), true);

    $this->assertEquals('Not Found', $body['error']);
    $this->assertArrayNotHasKey('details', $body);
}
```

## Summary

The CCGLabs Router follows the principle of separation of concerns. It extracts parameters but doesn't validate their contents, and it provides detailed exceptions but doesn't sanitize them because:

1. **It can't know** how parameters will be used or what information is sensitive
2. **It shouldn't assume** what validation or sanitization is needed
3. **It would limit flexibility** if it enforced security policies
4. **Industry standards** demonstrate this is the correct approach

Security validation and error sanitization are the responsibility of route handlers and error handling middleware, where the context, environment, and security requirements are known. This design provides maximum flexibility while maintaining security when properly implemented at the application level.

Remember: **The router extracts and reports, the application layer secures and sanitizes.**