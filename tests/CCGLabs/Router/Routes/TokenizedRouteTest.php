<?php

declare(strict_types=1);

namespace Tests\CCGLabs\Router\Routes;

use CCGLabs\Router\Exceptions\MissingRouteParameterException;
use CCGLabs\Router\IRenderableRoute;
use CCGLabs\Router\Routes\TokenizedRoute;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TokenizedRouteTest extends TestCase
{
    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(TokenizedRoute::class));
    }

    public function testMatchesReturnsNullIfDifferentNumberOfTokens(): void
    {
        $route = TokenizedRoute::fromPath('user/{userId}');
        $this->assertNull($route->matches('user/profile/12'));
    }

    public function testMatchesReturnsEmptyArrayForStaticMatch(): void
    {
        $route = TokenizedRoute::fromPath('user/profile');
        $this->assertSame([], $route->matches('user/profile'));
    }

    public function testMatchesReturnsParamsForParameterizedMatch(): void
    {
        $route = TokenizedRoute::fromPath('user/{id}');
        $this->assertSame(['id' => '12'], $route->matches('user/12'));
    }

    public function testMatchesReturnsNullForStaticMismatch(): void
    {
        $route = TokenizedRoute::fromPath('user/profile');
        $this->assertNull($route->matches('user/settings'));
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
        // Construct directly to bypass fromPath() validation.
        $route = new TokenizedRoute(['user', '{}']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Empty parameter names are not allowed');
        $route->matches('user/123');
    }

    // Boundary testing

    public function testFromPathAcceptsMaximumPathLength(): void
    {
        $segmentLength = 200;
        $segmentsNeeded = intval(TokenizedRoute::MAX_PATH_LENGTH / ($segmentLength + 1));
        $segments = array_fill(0, $segmentsNeeded, str_repeat('a', $segmentLength));
        $maxPath = implode('/', $segments);

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

    public function testSpecialCharactersInStaticParts(): void
    {
        $route = TokenizedRoute::fromPath('/api/v1.0/users');
        $this->assertSame([], $route->matches('/api/v1.0/users'));
        $this->assertNull($route->matches('/api/v1/users'));

        $route2 = TokenizedRoute::fromPath('/files/image-name.jpg');
        $this->assertSame([], $route2->matches('/files/image-name.jpg'));

        $route3 = TokenizedRoute::fromPath('/path/with-dashes_and_underscores');
        $this->assertSame([], $route3->matches('/path/with-dashes_and_underscores'));
    }

    public function testUrlEncodedPathsAreDecoded(): void
    {
        $route = TokenizedRoute::fromPath('/search/{query}');

        // %20 decodes to space
        $this->assertSame(['query' => 'hello world'], $route->matches('/search/hello%20world'));

        // %26 decodes to &
        $this->assertSame(['query' => 'test&filter'], $route->matches('/search/test&filter'));

        // Encoded ampersand input also decodes
        $this->assertSame(['query' => 'a&b'], $route->matches('/search/a%26b'));
    }

    public function testUnicodeCharactersInPaths(): void
    {
        $route = TokenizedRoute::fromPath('/user/{name}');

        $this->assertSame(['name' => 'José'], $route->matches('/user/José'));
        $this->assertSame(['name' => '😀'], $route->matches('/user/😀'));
        $this->assertSame(['name' => '用户'], $route->matches('/user/用户'));
    }

    public function testMalformedTokenFormats(): void
    {
        // Unclosed brace is treated as a static segment
        $route = TokenizedRoute::fromPath('/user/{id');
        $this->assertSame([], $route->matches('/user/{id'));
        $this->assertNull($route->matches('/user/123'));
    }

    public function testMalformedTokenClosingBraceOnly(): void
    {
        $route = TokenizedRoute::fromPath('/user/id}');
        $this->assertSame([], $route->matches('/user/id}'));
        $this->assertNull($route->matches('/user/id'));
    }

    public function testMalformedTokenEmptyBraces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        TokenizedRoute::fromPath('/user/{}');
    }

    public function testMalformedTokenNestedBraces(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid parameter name');
        TokenizedRoute::fromPath('/user/{id{nested}}');
    }

    public function testPathsWithQueryStringCharacters(): void
    {
        $route = TokenizedRoute::fromPath('/search/{term}');

        $this->assertSame(['term' => 'what?'], $route->matches('/search/what?'));
        $this->assertSame(['term' => '#tag'], $route->matches('/search/#tag'));
        $this->assertSame(['term' => 'rock&roll'], $route->matches('/search/rock&roll'));
    }

    public function testConsecutiveSlashesInPath(): void
    {
        $route = TokenizedRoute::fromPath('/api/{version}/users');

        $this->assertNull($route->matches('//api/v1/users'));
        $this->assertNull($route->matches('/api//v1/users'));
        $this->assertNull($route->matches('/api/v1//users'));
    }

    public function testTrailingSlashHandling(): void
    {
        $route = TokenizedRoute::fromPath('/users/{id}');

        $this->assertSame(['id' => '123'], $route->matches('/users/123'));
        $this->assertNull($route->matches('/users/123/'));

        $routeWithSlash = TokenizedRoute::fromPath('/users/{id}/');
        $this->assertNull($routeWithSlash->matches('/users/123'));
        $this->assertSame(['id' => '123'], $routeWithSlash->matches('/users/123/'));
    }

    public function testParameterExtractionWithDotsInSegment(): void
    {
        $route = TokenizedRoute::fromPath('/file/{filename}');

        $this->assertSame(['filename' => 'document.pdf'], $route->matches('/file/document.pdf'));
        $this->assertSame(['filename' => 'my-file.name.txt'], $route->matches('/file/my-file.name.txt'));
    }

    public function testMultipleParameters(): void
    {
        $route = TokenizedRoute::fromPath('/date/{date}/file/{filename}');

        $this->assertSame(
            ['date' => '2025-09-30', 'filename' => 'my-post'],
            $route->matches('/date/2025-09-30/file/my-post')
        );
    }

    public function testConstructorWithNonStringTokenThrowsException(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('"123" is an invalid url token');

        new TokenizedRoute(['user', 123, 'profile']);
    }

    public function testConstructorWithMultipleNonStringTokens(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new TokenizedRoute(['valid', null, false, 456]);
    }

    public function testConstructorWithObjectToken(): void
    {
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('Object of class stdClass could not be converted to string');

        $obj = new \stdClass();
        new TokenizedRoute(['path', $obj, 'end']);
    }

    public function testConstructorAcceptsStringTokens(): void
    {
        $route = new TokenizedRoute(['users', '{id}', 'profile']);

        $this->assertSame(['id' => '123'], $route->matches('users/123/profile'));
    }

    public function testConstructorWithEmptyArray(): void
    {
        $route = new TokenizedRoute(['']);

        $this->assertSame([], $route->matches(''));
    }

    // render() / IRenderableRoute

    public function testIsRenderable(): void
    {
        $route = TokenizedRoute::fromPath('/users/{id}');
        $this->assertInstanceOf(IRenderableRoute::class, $route);
    }

    public function testRenderStaticRoute(): void
    {
        $route = TokenizedRoute::fromPath('/users/profile');
        $this->assertSame('/users/profile', $route->render());
    }

    public function testRenderRouteWithSingleParameter(): void
    {
        $route = TokenizedRoute::fromPath('/users/{id}');
        $this->assertSame('/users/42', $route->render(['id' => '42']));
    }

    public function testRenderAcceptsIntegerParameter(): void
    {
        $route = TokenizedRoute::fromPath('/users/{id}');
        $this->assertSame('/users/42', $route->render(['id' => 42]));
    }

    public function testRenderAcceptsFloatParameter(): void
    {
        $route = TokenizedRoute::fromPath('/version/{v}');
        $this->assertSame('/version/1.5', $route->render(['v' => 1.5]));
    }

    public function testRenderAcceptsStringableParameter(): void
    {
        $route = TokenizedRoute::fromPath('/users/{id}');

        $stringable = new class {
            public function __toString(): string
            {
                return 'jane';
            }
        };

        $this->assertSame('/users/jane', $route->render(['id' => $stringable]));
    }

    public function testRenderRouteWithMultipleParameters(): void
    {
        $route = TokenizedRoute::fromPath('/posts/{year}/{slug}');
        $this->assertSame(
            '/posts/2025/hello-world',
            $route->render(['year' => '2025', 'slug' => 'hello-world'])
        );
    }

    public function testRenderUrlEncodesParameterValues(): void
    {
        $route = TokenizedRoute::fromPath('/search/{q}');
        $this->assertSame('/search/hello%20world', $route->render(['q' => 'hello world']));
        $this->assertSame('/search/a%26b', $route->render(['q' => 'a&b']));
    }

    public function testRenderRoundTripsThroughMatches(): void
    {
        $route = TokenizedRoute::fromPath('/search/{q}');
        $url = $route->render(['q' => 'hello world']);
        $this->assertSame(['q' => 'hello world'], $route->matches($url));
    }

    public function testRenderThrowsForMissingParameter(): void
    {
        $route = TokenizedRoute::fromPath('/users/{id}');

        $this->expectException(MissingRouteParameterException::class);
        $this->expectExceptionMessage('id');
        $route->render([]);
    }

    public function testRenderIgnoresExtraParameters(): void
    {
        $route = TokenizedRoute::fromPath('/users/{id}');
        $this->assertSame(
            '/users/42',
            $route->render(['id' => '42', 'unused' => 'value'])
        );
    }

    public function testRenderRootPath(): void
    {
        $route = TokenizedRoute::fromPath('/');
        $this->assertSame('/', $route->render());
    }
}
