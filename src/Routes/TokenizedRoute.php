<?php

/**
 * This file contains CCGLabs\Router\Routes\TokenizedRoute
*/

namespace CCGLabs\Router\Routes;

use BadMethodCallException;
use CCGLabs\Router\IParameterizedRoute;
use CCGLabs\Router\IRoute;
use InvalidArgumentException;

/**
 * A TokenizedRoute is a route which may specify named tokens as part of the
 * route. When the route specifies named tokens, the values of the tokens from
 * the most recent call to match() can be retrieved with getArguments().
 *
 * Example:
 *
 * $route = new TokenizedRoute('user/{id}');
 * $route->matches('user/12'); // Returns true
 * $route->getArguments(); // Returns ['id' => 12]
 */
class TokenizedRoute implements IParameterizedRoute
{
    public const PATH_SEPARATOR = '/';
    public const ERROR_INVALID_TOKEN = '"%s" is an invalid url token';
    public const ERROR_INVALID_PATH = 'Invalid route path: %s';
    public const ERROR_EMPTY_PARAMETER = 'Empty parameter names are not allowed';
    public const ERROR_INVALID_PARAMETER_NAME = 'Invalid parameter name "%s": must contain only '
        . 'letters, numbers, and underscores, and start with a letter or underscore';
    public const ERROR_DUPLICATE_PARAMETER = 'Duplicate parameter name "%s" in route path';
    public const ERROR_PATH_TOO_LONG = 'Route path exceeds maximum length of %d characters';
    public const ERROR_TOO_MANY_SEGMENTS = 'Route path exceeds maximum of %d segments';
    public const ERROR_SEGMENT_TOO_LONG = 'Route segment "%s" exceeds maximum length of %d characters';

    public const MAX_PATH_LENGTH = 2048;
    public const MAX_SEGMENTS = 50;
    public const MAX_SEGMENT_LENGTH = 255;

    /**
     * A hash of the named arguments and their values fmro the most recent
     * call to matches().
     */
    protected ?array $arguments;

    /**
     * Creates a new TokenizedRoute.
     *
     * @param string[] $tokens The list of tokens for the route.
     * @throws InvalidArgumentException if any array items are not strings.
     */
    public function __construct(protected array $tokens)
    {
        foreach ($tokens as $token) {
            if (! is_string($token)) {
                throw new InvalidArgumentException(sprintf(self::ERROR_INVALID_TOKEN, (string) $token));
            }
        }

        $this->arguments = null;
    }

    /**
     * Creates a TokenizedRoute from a string by splitting it on the path
     * separator ("/").
     *
     * Example:
     * $route = TokenizedRoute::fromPath('user/{id}/metadata');
     * // Equivalent to new TokenizedRoute(['user'], '{id}', 'metadata'])
     *
     * @param string $path The path to convert to a TokenizedRoute.
     * @return self
     * @throws InvalidArgumentException if the path is invalid
     */
    public static function fromPath(string $path): self
    {
        // Validate path length
        if (strlen($path) > self::MAX_PATH_LENGTH) {
            throw new InvalidArgumentException(sprintf(self::ERROR_PATH_TOO_LONG, self::MAX_PATH_LENGTH));
        }

        // Split path into tokens
        $tokens = explode(self::PATH_SEPARATOR, $path);

        // Validate number of segments
        if (count($tokens) > self::MAX_SEGMENTS) {
            throw new InvalidArgumentException(sprintf(self::ERROR_TOO_MANY_SEGMENTS, self::MAX_SEGMENTS));
        }

        // Validate each token
        $parameterNames = [];
        foreach ($tokens as $token) {
            // Validate segment length
            if (strlen($token) > self::MAX_SEGMENT_LENGTH) {
                throw new InvalidArgumentException(sprintf(
                    self::ERROR_SEGMENT_TOO_LONG,
                    substr($token, 0, 50) . '...',
                    self::MAX_SEGMENT_LENGTH
                ));
            }

            // Check if this is a parameter token
            $tokenLength = strlen($token);
            if ($tokenLength >= 2 && $token[0] === '{' && $token[$tokenLength - 1] === '}') {
                $paramName = substr($token, 1, -1);

                // Validate parameter name is not empty
                if ($paramName === '') {
                    throw new InvalidArgumentException(self::ERROR_EMPTY_PARAMETER);
                }

                // Validate parameter name format (alphanumeric + underscore, must start with letter or underscore)
                if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]*$/', $paramName)) {
                    throw new InvalidArgumentException(sprintf(self::ERROR_INVALID_PARAMETER_NAME, $paramName));
                }

                // Check for duplicate parameter names
                if (in_array($paramName, $parameterNames, true)) {
                    throw new InvalidArgumentException(sprintf(self::ERROR_DUPLICATE_PARAMETER, $paramName));
                }

                $parameterNames[] = $paramName;
            }
        }

        return new TokenizedRoute($tokens);
    }

    /**
     * Returns true if the specified path matches the TokenizedRoute.
     *
     * The path matches the route if:
     *
     * 1. They have the same number of tokens.
     * 2. String tokens are identical.
     *
     * Examples:
     *
     * $route = TokenizedRoute::fromString('user/{id}/groups');
     * $route->matches('user/12/groups'); // Returns true
     *
     * $route = TokenizedRoute::fromString('group/{id}/metadata');
     * $route->matches('group/12'); // Returns false
     *
     * @param string $path The path to test
     * @return bool Returns true if the patch matches the TokenizedRoute
     */
    public function matches(string $path): bool
    {
        $pathTokens = explode('/', $path);
        $this->arguments = null;
        $arguments = [];

        if (count($pathTokens) !== count($this->tokens)) {
            return false;
        }

        for ($index = 0; $index < count($pathTokens); $index++) {
            $token = $this->tokens[$index];
            $tokenLength = strlen($token);

            // Check if this is a parameter token (wrapped in curly braces)
            if ($tokenLength >= 2 && $token[0] === '{' && $token[$tokenLength - 1] === '}') {
                $paramName = substr($token, 1, -1);

                // Reject empty parameter names
                if ($paramName === '') {
                    throw new InvalidArgumentException(self::ERROR_EMPTY_PARAMETER);
                }

                $arguments[$paramName] = $pathTokens[$index];
                continue;
            }

            if ($this->tokens[$index] !== $pathTokens[$index]) {
                return false;
            }
        }

        $this->arguments = $arguments;
        return true;
    }

    /**
     * @inheritdoc
     */
    public function getParameters(): array
    {
        if ($this->arguments === null) {
            throw new BadMethodCallException('matches() not called or last call failed');
        }

        return $this->arguments;
    }
}
