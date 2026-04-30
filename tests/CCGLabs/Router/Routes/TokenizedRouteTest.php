<?php

/**
 * This file contains Tests\CCGLabs\Router\Routes\TokenizedRouteTest
 *
 * Copyright 2025 Brian Reich
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy of this
 * software and associated documentation files (the “Software”), to deal in the
 * Software without restriction, including without limitation the rights to use, copy,
 * modify, merge, publish, distribute, sublicense, and/or sell copies of the Software,
 * and to permit persons to whom the Software is furnished to do so, subject to the
 * following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED “AS IS”, WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED,
 * INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A
 * PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT
 * HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF
 * CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE
 * OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 *
 * @author Brian Reich <brian@brianreich.dev>
 * @copyright Copyright (C) 2025 Brian Reich
 * @license MIT
 */

declare(strict_types=1);

namespace Tests\CCGLabs\Router\Routes;

use CCGLabs\Router\Routes\TokenizedRoute;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TokenizedRouteTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(TokenizedRoute::class));
    }

    public function testMatchesFailedIfDifferentNumberOfTokens(): void
    {
        $route = TokenizedRoute::fromPath('user/{userId}');
        $path = 'user/profile/12';
        $this->assertFalse($route->matches($path));
    }

    public function testReturnsTrueForExactMatch(): void
    {
        $route = TokenizedRoute::fromPath('user/profile');
        $this->assertTrue($route->matches('user/profile'));
    }

    public function testReturnsTrueForArgumentMatch(): void
    {
        $route = TokenizedRoute::fromPath('user/{id}');
        $this->assertTrue($route->matches('user/12'));
        $this->assertEquals('12', $route->getParameters()['id']);
    }

    // Validation Tests

    public function testFromPathThrowsExceptionForEmptyParameter(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty parameter names are not allowed');
        TokenizedRoute::fromPath('user/{}');
    }

    public function testFromPathThrowsExceptionForInvalidParameterName(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parameter name "123invalid"');
        TokenizedRoute::fromPath('user/{123invalid}');
    }

    public function testFromPathThrowsExceptionForParameterNameStartingWithNumber(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parameter name "1id"');
        TokenizedRoute::fromPath('user/{1id}');
    }

    public function testFromPathThrowsExceptionForParameterNameWithInvalidCharacters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parameter name "user-id"');
        TokenizedRoute::fromPath('api/{user-id}');
    }

    public function testFromPathThrowsExceptionForParameterNameWithSpaces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parameter name "user id"');
        TokenizedRoute::fromPath('api/{user id}');
    }

    public function testFromPathThrowsExceptionForDuplicateParameters(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Duplicate parameter name "id"');
        TokenizedRoute::fromPath('user/{id}/profile/{id}');
    }

    public function testFromPathThrowsExceptionForPathTooLong(): void
    {
        $longPath = str_repeat('a', TokenizedRoute::MAX_PATH_LENGTH + 1);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route path exceeds maximum length');
        TokenizedRoute::fromPath($longPath);
    }

    public function testFromPathThrowsExceptionForTooManySegments(): void
    {
        $segments = array_fill(0, TokenizedRoute::MAX_SEGMENTS + 1, 'segment');
        $longPath = implode('/', $segments);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route path exceeds maximum of');
        TokenizedRoute::fromPath($longPath);
    }

    public function testFromPathThrowsExceptionForSegmentTooLong(): void
    {
        $longSegment = str_repeat('a', TokenizedRoute::MAX_SEGMENT_LENGTH + 1);
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Route segment');
        $this->expectExceptionMessage('exceeds maximum length');
        TokenizedRoute::fromPath("user/$longSegment");
    }

    // Valid parameter names

    public function testFromPathAcceptsValidParameterNames(): void
    {
        // These should all work without throwing exceptions
        $validRoutes = [
            'user/{id}',
            'api/{userId}',
            'post/{_id}',
            'article/{post_id}',
            'category/{_category_name}',
            'files/{fileName123}',
            'search/{QUERY}',
        ];

        foreach ($validRoutes as $routePath) {
            $route = TokenizedRoute::fromPath($routePath);
            $this->assertInstanceOf(TokenizedRoute::class, $route);
        }
    }

    public function testFromPathAcceptsComplexValidRoutes(): void
    {
        $complexRoutes = [
            'api/v1/users/{userId}/posts/{postId}/comments/{commentId}',
            'admin/{_section}/manage/{entity_type}',
            'files/{directory}/{subdirectory}/{filename}',
            '{lang}/category/{cat_id}/product/{product_slug}',
        ];

        foreach ($complexRoutes as $routePath) {
            $route = TokenizedRoute::fromPath($routePath);
            $this->assertInstanceOf(TokenizedRoute::class, $route);
        }
    }

    // Edge cases

    public function testFromPathAcceptsEmptyPath(): void
    {
        $route = TokenizedRoute::fromPath('');
        $this->assertInstanceOf(TokenizedRoute::class, $route);
    }

    public function testFromPathAcceptsRootPath(): void
    {
        $route = TokenizedRoute::fromPath('/');
        $this->assertInstanceOf(TokenizedRoute::class, $route);
    }

    public function testMatchesThrowsExceptionForEmptyParameterInRuntime(): void
    {
        // Create route with direct constructor (bypassing fromPath validation)
        $route = new TokenizedRoute(['user', '{}']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty parameter names are not allowed');
        $route->matches('user/123');
    }

    // Boundary testing

    public function testFromPathAcceptsMaximumPathLength(): void
    {
        // Create a path that's at maximum length but with valid segments
        $segmentLength = 200; // Less than MAX_SEGMENT_LENGTH (255)
        $segmentsNeeded = intval(TokenizedRoute::MAX_PATH_LENGTH / ($segmentLength + 1)); // +1 for separator
        $segments = array_fill(0, $segmentsNeeded, str_repeat('a', $segmentLength));
        $maxPath = implode('/', $segments);

        // Ensure we're at or under the maximum path length
        $this->assertLessThanOrEqual(TokenizedRoute::MAX_PATH_LENGTH, strlen($maxPath));

        $route = TokenizedRoute::fromPath($maxPath);
        $this->assertInstanceOf(TokenizedRoute::class, $route);
    }

    public function testFromPathAcceptsMaximumSegments(): void
    {
        $segments = array_fill(0, TokenizedRoute::MAX_SEGMENTS, 'seg');
        $maxSegmentsPath = implode('/', $segments);
        $route = TokenizedRoute::fromPath($maxSegmentsPath);
        $this->assertInstanceOf(TokenizedRoute::class, $route);
    }

    public function testFromPathAcceptsMaximumSegmentLength(): void
    {
        $maxSegment = str_repeat('a', TokenizedRoute::MAX_SEGMENT_LENGTH);
        $route = TokenizedRoute::fromPath("user/$maxSegment");
        $this->assertInstanceOf(TokenizedRoute::class, $route);
    }

    /**
     * Test that special characters in static parts work correctly
     */
    public function testSpecialCharactersInStaticParts(): void
    {
        $route = TokenizedRoute::fromPath('/api/v1.0/users');
        $this->assertTrue($route->matches('/api/v1.0/users'));
        $this->assertFalse($route->matches('/api/v1/users'));

        $route2 = TokenizedRoute::fromPath('/files/image-name.jpg');
        $this->assertTrue($route2->matches('/files/image-name.jpg'));

        $route3 = TokenizedRoute::fromPath('/path/with-dashes_and_underscores');
        $this->assertTrue($route3->matches('/path/with-dashes_and_underscores'));
    }

    /**
     * Test URL encoded paths
     */
    public function testUrlEncodedPaths(): void
    {
        $route = TokenizedRoute::fromPath('/search/{query}');

        // URL encoded space
        $this->assertTrue($route->matches('/search/hello%20world'));
        $params = $route->getParameters('/search/hello%20world');
        $this->assertEquals('hello%20world', $params['query']);

        // URL encoded special characters
        $this->assertTrue($route->matches('/search/test%26filter'));
        $params = $route->getParameters('/search/test%26filter');
        $this->assertEquals('test%26filter', $params['query']);
    }

    /**
     * Test Unicode characters in paths
     */
    public function testUnicodeCharactersInPaths(): void
    {
        $route = TokenizedRoute::fromPath('/user/{name}');

        // Unicode characters
        $this->assertTrue($route->matches('/user/José'));
        $params = $route->getParameters('/user/José');
        $this->assertEquals('José', $params['name']);

        // Emoji
        $this->assertTrue($route->matches('/user/😀'));
        $params = $route->getParameters('/user/😀');
        $this->assertEquals('😀', $params['name']);

        // Chinese characters
        $this->assertTrue($route->matches('/user/用户'));
        $params = $route->getParameters('/user/用户');
        $this->assertEquals('用户', $params['name']);
    }

    /**
     * Test malformed token formats
     */
    public function testMalformedTokenFormats(): void
    {
        // Unclosed brace - this is actually treated as a static segment
        $route = TokenizedRoute::fromPath('/user/{id');
        $this->assertTrue($route->matches('/user/{id'));
        $this->assertFalse($route->matches('/user/123'));
    }

    /**
     * Test malformed token with closing brace only
     */
    public function testMalformedTokenClosingBraceOnly(): void
    {
        // Closing brace without opening
        $route = TokenizedRoute::fromPath('/user/id}');
        $this->assertTrue($route->matches('/user/id}'));
        $this->assertFalse($route->matches('/user/id'));
    }

    /**
     * Test malformed token with empty braces
     */
    public function testMalformedTokenEmptyBraces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TokenizedRoute::fromPath('/user/{}');
    }

    /**
     * Test malformed token with nested braces
     */
    public function testMalformedTokenNestedBraces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parameter name');
        TokenizedRoute::fromPath('/user/{id{nested}}');
    }

    /**
     * Test paths with query string characters
     */
    public function testPathsWithQueryStringCharacters(): void
    {
        $route = TokenizedRoute::fromPath('/search/{term}');

        // Question mark in parameter
        $this->assertTrue($route->matches('/search/what?'));
        $params = $route->getParameters('/search/what?');
        $this->assertEquals('what?', $params['term']);

        // Hash in parameter
        $this->assertTrue($route->matches('/search/#tag'));
        $params = $route->getParameters('/search/#tag');
        $this->assertEquals('#tag', $params['term']);

        // Ampersand in parameter
        $this->assertTrue($route->matches('/search/rock&roll'));
        $params = $route->getParameters('/search/rock&roll');
        $this->assertEquals('rock&roll', $params['term']);
    }

    /**
     * Test consecutive slashes in path
     */
    public function testConsecutiveSlashesInPath(): void
    {
        $route = TokenizedRoute::fromPath('/api/{version}/users');

        // Double slash should not match
        $this->assertFalse($route->matches('//api/v1/users'));
        $this->assertFalse($route->matches('/api//v1/users'));
        $this->assertFalse($route->matches('/api/v1//users'));
    }

    /**
     * Test trailing slash handling
     */
    public function testTrailingSlashHandling(): void
    {
        $route = TokenizedRoute::fromPath('/users/{id}');

        // Without trailing slash
        $this->assertTrue($route->matches('/users/123'));

        // With trailing slash - should not match (different number of segments)
        $this->assertFalse($route->matches('/users/123/'));

        // Route with trailing slash
        $routeWithSlash = TokenizedRoute::fromPath('/users/{id}/');
        $this->assertFalse($routeWithSlash->matches('/users/123'));
        $this->assertTrue($routeWithSlash->matches('/users/123/'));
    }

    /**
     * Test parameter extraction with dots in segment
     */
    public function testParameterExtractionWithDotsInSegment(): void
    {
        // Parameters can contain dots
        $route = TokenizedRoute::fromPath('/file/{filename}');

        $this->assertTrue($route->matches('/file/document.pdf'));
        $params = $route->getParameters('/file/document.pdf');
        $this->assertEquals('document.pdf', $params['filename']);

        $this->assertTrue($route->matches('/file/my-file.name.txt'));
        $params = $route->getParameters('/file/my-file.name.txt');
        $this->assertEquals('my-file.name.txt', $params['filename']);
    }

    /**
     * Test multiple parameters per segment
     */
    public function testMultipleParametersPerSegment(): void
    {
        // Current implementation treats the whole segment as one parameter or static
        // Multiple parameters in one segment would require complex parsing
        $route = TokenizedRoute::fromPath('/date/{date}/file/{filename}');

        $this->assertTrue($route->matches('/date/2025-09-30/file/my-post'));
        $params = $route->getParameters('/date/2025-09-30/file/my-post');
        $this->assertEquals('2025-09-30', $params['date']);
        $this->assertEquals('my-post', $params['filename']);
    }

    /**
     * Test constructor with non-string token throws exception
     */
    public function testConstructorWithNonStringTokenThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"123" is an invalid url token');

        // Directly instantiate with non-string token
        new TokenizedRoute(['user', 123, 'profile']);
    }

    /**
     * Test constructor with multiple non-string tokens
     */
    public function testConstructorWithMultipleNonStringTokens(): void
    {
        $this->expectException(InvalidArgumentException::class);
        // Should fail on first non-string token

        // Mix of valid and invalid tokens
        new TokenizedRoute(['valid', null, false, 456]);
    }

    /**
     * Test constructor with object token
     */
    public function testConstructorWithObjectToken(): void
    {
        // PHP will throw an Error when trying to cast stdClass to string
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Object of class stdClass could not be converted to string');

        $obj = new \stdClass();
        new TokenizedRoute(['path', $obj, 'end']);
    }

    /**
     * Test getParameters without calling matches throws exception
     */
    public function testGetParametersWithoutMatchesThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('matches() not called or last call failed');

        $route = TokenizedRoute::fromPath('/user/{id}');
        // Call getParameters without calling matches first
        $route->getParameters();
    }

    /**
     * Test getParameters after failed match throws exception
     */
    public function testGetParametersAfterFailedMatchThrowsException(): void
    {
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('matches() not called or last call failed');

        $route = TokenizedRoute::fromPath('/user/{id}');

        // Call matches with non-matching path
        $this->assertFalse($route->matches('/post/123'));

        // getParameters should throw since last match failed
        $route->getParameters();
    }

    /**
     * Test getParameters after successful match then failed match
     */
    public function testGetParametersResetAfterFailedMatch(): void
    {
        $route = TokenizedRoute::fromPath('/user/{id}');

        // First successful match
        $this->assertTrue($route->matches('/user/123'));
        $params = $route->getParameters();
        $this->assertEquals('123', $params['id']);

        // Failed match should reset parameters
        $this->assertFalse($route->matches('/post/456'));

        // Should throw exception now
        $this->expectException(\BadMethodCallException::class);
        $this->expectExceptionMessage('matches() not called or last call failed');
        $route->getParameters();
    }

    /**
     * Test constructor accepts all string tokens
     */
    public function testConstructorAcceptsStringTokens(): void
    {
        // Direct instantiation with all strings should work
        $route = new TokenizedRoute(['users', '{id}', 'profile']);

        // Verify it works correctly
        $this->assertTrue($route->matches('users/123/profile'));
        $params = $route->getParameters('users/123/profile');
        $this->assertEquals('123', $params['id']);
    }

    /**
     * Test constructor with empty array
     */
    public function testConstructorWithEmptyArray(): void
    {
        // Should accept empty array
        $route = new TokenizedRoute(['']);

        // Should match empty string (explode creates [''] for empty string)
        $this->assertTrue($route->matches(''));
        $params = $route->getParameters();
        $this->assertEquals([], $params);
    }
}
