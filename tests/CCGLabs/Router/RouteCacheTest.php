<?php

declare(strict_types=1);

namespace Tests\CCGLabs\Router;

use CCGLabs\Router\RouteCache;
use PHPUnit\Framework\TestCase;

class RouteCacheTest extends TestCase
{
    private string $tempFile;

    protected function setUp(): void
    {
        $this->tempFile = tempnam(sys_get_temp_dir(), 'ccglabs-router-test-') ?: '';
        // tempnam creates the file; we want a fresh path each test that may or may not exist
        @unlink($this->tempFile);
    }

    protected function tearDown(): void
    {
        @unlink($this->tempFile);
    }

    public function testIsEnabledReturnsTrueWhenGivenAPath(): void
    {
        $cache = new RouteCache($this->tempFile);
        $this->assertTrue($cache->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenGivenFalse(): void
    {
        $cache = new RouteCache(false);
        $this->assertFalse($cache->isEnabled());
    }

    public function testGetCachedReturnsNullForUnknownPath(): void
    {
        $cache = new RouteCache($this->tempFile);
        $this->assertNull($cache->getCached('/users/{id}'));
    }

    public function testRecordThenGetCachedReturnsTokens(): void
    {
        $cache = new RouteCache($this->tempFile);
        $cache->record('/users/{id}', ['', 'users', '{id}']);

        $this->assertSame(['', 'users', '{id}'], $cache->getCached('/users/{id}'));
    }

    public function testPersistWritesPhpFileReturningRecordedTokens(): void
    {
        $cache = new RouteCache($this->tempFile);
        $cache->record('/users/{id}', ['', 'users', '{id}']);
        $cache->record('/posts/{year}', ['', 'posts', '{year}']);
        $cache->persist();

        $this->assertFileExists($this->tempFile);
        $loaded = include $this->tempFile;
        $this->assertSame([
            '/users/{id}' => ['', 'users', '{id}'],
            '/posts/{year}' => ['', 'posts', '{year}'],
        ], $loaded);
    }

    public function testNewCacheLoadsExistingFile(): void
    {
        $cache = new RouteCache($this->tempFile);
        $cache->record('/users/{id}', ['', 'users', '{id}']);
        $cache->persist();

        $reloaded = new RouteCache($this->tempFile);
        $this->assertSame(['', 'users', '{id}'], $reloaded->getCached('/users/{id}'));
    }

    public function testPersistPrunesEntriesNotTouchedThisRun(): void
    {
        // First run: register two routes.
        $cache = new RouteCache($this->tempFile);
        $cache->record('/old', ['', 'old']);
        $cache->record('/keep/{id}', ['', 'keep', '{id}']);
        $cache->persist();

        // Second run: load cache, only "touch" /keep, register a new /new.
        $reloaded = new RouteCache($this->tempFile);
        $this->assertSame(['', 'keep', '{id}'], $reloaded->getCached('/keep/{id}'));  // touches
        $reloaded->record('/new', ['', 'new']);
        $reloaded->persist();

        // /old should be gone; /keep and /new should remain.
        $thirdLoad = new RouteCache($this->tempFile);
        $this->assertNull($thirdLoad->getCached('/old'));
        $this->assertSame(['', 'keep', '{id}'], $thirdLoad->getCached('/keep/{id}'));
        $this->assertSame(['', 'new'], $thirdLoad->getCached('/new'));
    }

    public function testPersistIsNoopWhenDisabled(): void
    {
        $cache = new RouteCache(false);
        $cache->record('/users/{id}', ['', 'users', '{id}']);
        $cache->persist();

        // Nothing to assert beyond "no exception thrown" — this is the contract.
        $this->expectNotToPerformAssertions();
    }

    public function testPersistIsDefensiveAgainstUnwritablePath(): void
    {
        // /proc/self is read-only on Linux; trying to write a file under
        // a non-existent subdirectory there will fail. RouteCache must
        // swallow the error rather than throwing.
        $cache = new RouteCache('/proc/self/nonexistent-dir/cache.php');
        $cache->record('/users/{id}', ['', 'users', '{id}']);
        $cache->persist();

        $this->expectNotToPerformAssertions();
    }

    public function testLoadIsDefensiveAgainstCorruptCacheFile(): void
    {
        file_put_contents($this->tempFile, '<?php this is not valid php at all !!!');

        // Should not throw, just behave as if cache is empty.
        $cache = new RouteCache($this->tempFile);
        $this->assertNull($cache->getCached('/users/{id}'));
    }

    public function testLoadIsDefensiveAgainstNonArrayContent(): void
    {
        file_put_contents($this->tempFile, '<?php return "not an array";');

        $cache = new RouteCache($this->tempFile);
        $this->assertNull($cache->getCached('/users/{id}'));
    }

    public function testDefaultPathIsUnderTempDir(): void
    {
        $path = RouteCache::defaultPath();
        $this->assertStringStartsWith(sys_get_temp_dir(), $path);
        $this->assertStringEndsWith('.php', $path);
    }

    public function testDefaultPathIsStableForSameWorkingDirectory(): void
    {
        $a = RouteCache::defaultPath();
        $b = RouteCache::defaultPath();
        $this->assertSame($a, $b);
    }
}
