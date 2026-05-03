<?php

declare(strict_types=1);

namespace CCGLabs\Router;

use Throwable;

/**
 * RouteCache persists parsed route token lists to a PHP file so that the
 * cost of parsing route patterns can be skipped on subsequent requests.
 *
 * The cache stores only structural data (a path-string to token-list map),
 * not handlers — handlers remain in user code and are wired up on every
 * request as usual.
 *
 * The cache is implicit and defensive: failures to load or persist the
 * cache file never throw. In the worst case the application runs without
 * caching, parsing routes from scratch as if the cache were absent.
 *
 * Pass false to the constructor to disable caching entirely.
 */
class RouteCache
{
    private const DEFAULT_PATH_PREFIX = 'ccglabs-router-';

    /**
     * Default cache file path: derived from cwd so different projects on the
     * same host don't collide on a shared temp file.
     */
    public static function defaultPath(): string
    {
        // Fall back to __FILE__ if getcwd() fails (e.g. cwd was deleted under us)
        // so the hash still resolves to a stable, project-specific value.
        $cwd = getcwd() ?: __FILE__;
        $hash = substr(md5($cwd), 0, 16);
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::DEFAULT_PATH_PREFIX . $hash . '.php';
    }

    /** @var array<string, string[]> */
    private array $tokens = [];

    /** @var array<string, true> */
    private array $touched = [];

    private bool $dirty = false;

    /**
     * Short-circuits persist() on the request hot path. persist() is called
     * from Application::handle() on every request, but the cache shape can't
     * change during dispatch, so we only need to act once per process.
     */
    private bool $persisted = false;

    /**
     * @param string|false $path Path to the cache file, or false to disable caching.
     */
    public function __construct(private string|false $path)
    {
        if ($this->path !== false) {
            $this->load();
        }
    }

    /**
     * @internal
     */
    public function isEnabled(): bool
    {
        return $this->path !== false;
    }

    /**
     * @return string[]|null
     */
    public function getCached(string $path): ?array
    {
        if (! isset($this->tokens[$path])) {
            return null;
        }

        // Touch on read so persist() retains the entry even when no new
        // record() call confirms it this run.
        $this->touched[$path] = true;
        return $this->tokens[$path];
    }

    /**
     * @param string[] $tokens
     */
    public function record(string $path, array $tokens): void
    {
        if (! isset($this->tokens[$path]) || $this->tokens[$path] !== $tokens) {
            $this->tokens[$path] = $tokens;
            $this->dirty = true;
        }
        $this->touched[$path] = true;
    }

    /**
     * Best-effort write of the cache file. Idempotent across a process;
     * silently swallows write failures so the application is unaffected by
     * an unwritable cache.
     */
    public function persist(): void
    {
        if ($this->persisted) {
            return;
        }

        if ($this->path === false) {
            $this->persisted = true;
            return;
        }

        // Drop entries that weren't touched this run — those routes were
        // removed from user code since the cache was last written.
        $pruned = array_intersect_key($this->tokens, $this->touched);
        if (count($pruned) !== count($this->tokens)) {
            $this->tokens = $pruned;
            $this->dirty = true;
        }

        if (! $this->dirty) {
            $this->persisted = true;
            return;
        }

        $contents = "<?php\n\nreturn " . var_export($this->tokens, true) . ";\n";

        try {
            $dir = dirname($this->path);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            $bytes = @file_put_contents($this->path, $contents, LOCK_EX);
            if ($bytes !== false) {
                $this->dirty = false;
                $this->persisted = true;
            }
        } catch (Throwable) {
            // Caching is best-effort. Failures are silent by design.
        }
    }

    private function load(): void
    {
        try {
            // @include returns false on missing/unreadable files; that path is
            // handled by the is_array check, so we skip an explicit existence
            // pre-check (which would race against the include anyway).
            $loaded = @include $this->path;
            if (is_array($loaded)) {
                $this->tokens = $loaded;
            }
        } catch (Throwable) {
            // Corrupt cache file: behave as if it didn't exist.
        }
    }
}
