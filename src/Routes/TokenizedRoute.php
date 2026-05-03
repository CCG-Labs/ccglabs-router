<?php

/**
 * This file contains CCGLabs\Router\Routes\TokenizedRoute
 */

declare(strict_types=1);

namespace CCGLabs\Router\Routes;

use CCGLabs\Router\Exceptions\MissingRouteParameterException;
use CCGLabs\Router\IRenderableRoute;
use CCGLabs\Router\IRoute;
use InvalidArgumentException;
use Stringable;

/**
 * A TokenizedRoute is a route which may specify named tokens as part of the
 * route path. When the route specifies named tokens, the values of the tokens
 * are returned from matches() as an associative array.
 *
 * Example:
 *
 *     $route = TokenizedRoute::fromPath('user/{id}');
 *     $route->matches('user/12'); // Returns ['id' => '12']
 *     $route->matches('foo');     // Returns null
 *
 * Parameter values are URL-decoded via rawurldecode() before being returned.
 */
class TokenizedRoute implements IRenderableRoute
{
    public const PATH_SEPARATOR = '/';
    public const ERROR_INVALID_TOKEN = '"%s" is an invalid url token';
    public const ERROR_INVALID_PATH = 'Invalid route path: %s';
    public const ERROR_EMPTY_PARAMETER = 'Empty parameter names are not allowed';
    public const ERROR_INVALID_PARAMETER_NAME = 'Invalid parameter name "%s": must contain only letters, numbers, and underscores, and start with a letter or underscore';
    public const ERROR_DUPLICATE_PARAMETER = 'Duplicate parameter name "%s" in route path';
    public const ERROR_PATH_TOO_LONG = 'Route path exceeds maximum length of %d characters';
    public const ERROR_TOO_MANY_SEGMENTS = 'Route path exceeds maximum of %d segments';
    public const ERROR_SEGMENT_TOO_LONG = 'Route segment "%s" exceeds maximum length of %d characters';
    public const ERROR_MISSING_PARAMETER = 'Missing required route parameter "%s"';

    public const MAX_PATH_LENGTH = 2048;
    public const MAX_SEGMENTS = 50;
    public const MAX_SEGMENT_LENGTH = 255;

    /**
     * @param string[] $tokens The list of tokens for the route.
     * @throws InvalidArgumentException if any array items are not strings.
     */
    public function __construct(protected array $tokens)
    {
        foreach ($tokens as $token) {
            // Intentional runtime guard: the @param string[] annotation is a
            // contract, not enforced by PHP. Defensive validation protects
            // against callers that bypass static type checking.
            // @phpstan-ignore function.alreadyNarrowedType
            if (! is_string($token)) {
                throw new InvalidArgumentException(sprintf(self::ERROR_INVALID_TOKEN, (string) $token));
            }
        }
    }

    /**
     * Returns the tokens that make up this route.
     *
     * Primarily intended for cache persistence: callers that have a route
     * object and want to round-trip its parsed structure to disk can read
     * the token list here and reconstruct the route via the constructor
     * (which skips fromPath()'s validation, since the tokens were already
     * validated when first parsed).
     *
     * @return string[]
     */
    public function getTokens(): array
    {
        return $this->tokens;
    }

    /**
     * Creates a TokenizedRoute from a string by splitting it on the path
     * separator ("/").
     *
     * Example:
     *     $route = TokenizedRoute::fromPath('user/{id}/metadata');
     *     // Equivalent to new TokenizedRoute(['user', '{id}', 'metadata'])
     *
     * @param string $path The path to convert to a TokenizedRoute.
     * @throws InvalidArgumentException if the path is invalid.
     */
    public static function fromPath(string $path): self
    {
        if (strlen($path) > self::MAX_PATH_LENGTH) {
            throw new InvalidArgumentException(sprintf(self::ERROR_PATH_TOO_LONG, self::MAX_PATH_LENGTH));
        }

        $tokens = explode(self::PATH_SEPARATOR, $path);

        if (count($tokens) > self::MAX_SEGMENTS) {
            throw new InvalidArgumentException(sprintf(self::ERROR_TOO_MANY_SEGMENTS, self::MAX_SEGMENTS));
        }

        $parameterNames = [];
        foreach ($tokens as $token) {
            if (strlen($token) > self::MAX_SEGMENT_LENGTH) {
                throw new InvalidArgumentException(sprintf(
                    self::ERROR_SEGMENT_TOO_LONG,
                    substr($token, 0, 50) . '...',
                    self::MAX_SEGMENT_LENGTH
                ));
            }

            $tokenLength = strlen($token);
            if ($tokenLength >= 2 && $token[0] === '{' && $token[$tokenLength - 1] === '}') {
                $paramName = substr($token, 1, -1);

                if ($paramName === '') {
                    throw new InvalidArgumentException(self::ERROR_EMPTY_PARAMETER);
                }

                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $paramName)) {
                    throw new InvalidArgumentException(sprintf(self::ERROR_INVALID_PARAMETER_NAME, $paramName));
                }

                if (in_array($paramName, $parameterNames, true)) {
                    throw new InvalidArgumentException(sprintf(self::ERROR_DUPLICATE_PARAMETER, $paramName));
                }

                $parameterNames[] = $paramName;
            }
        }

        return new TokenizedRoute($tokens);
    }

    /**
     * Tests whether the given path matches this route.
     *
     * Returns an associative array of decoded parameter values on match
     * (empty array if there are no parameters), or null on miss.
     *
     * @return array<string, string>|null
     */
    public function matches(string $path): ?array
    {
        $pathTokens = explode('/', $path);

        if (count($pathTokens) !== count($this->tokens)) {
            return null;
        }

        $params = [];
        for ($i = 0, $n = count($pathTokens); $i < $n; $i++) {
            $token = $this->tokens[$i];
            $tokenLength = strlen($token);

            if ($tokenLength >= 2 && $token[0] === '{' && $token[$tokenLength - 1] === '}') {
                $paramName = substr($token, 1, -1);

                if ($paramName === '') {
                    throw new InvalidArgumentException(self::ERROR_EMPTY_PARAMETER);
                }

                $params[$paramName] = rawurldecode($pathTokens[$i]);
                continue;
            }

            if ($token !== $pathTokens[$i]) {
                return null;
            }
        }

        return $params;
    }

    /**
     * Renders this route to a URL path with the given parameters substituted in.
     *
     * @param array<string, string|int|float|Stringable> $params
     * @throws MissingRouteParameterException If a parameter declared in the
     *     route pattern is not present in $params.
     */
    public function render(array $params = []): string
    {
        $segments = [];
        foreach ($this->tokens as $token) {
            $tokenLength = strlen($token);

            if ($tokenLength >= 2 && $token[0] === '{' && $token[$tokenLength - 1] === '}') {
                $name = substr($token, 1, -1);

                if (! array_key_exists($name, $params)) {
                    throw new MissingRouteParameterException(
                        sprintf(self::ERROR_MISSING_PARAMETER, $name)
                    );
                }

                $segments[] = rawurlencode((string) $params[$name]);
                continue;
            }

            $segments[] = $token;
        }

        return implode(self::PATH_SEPARATOR, $segments);
    }
}
