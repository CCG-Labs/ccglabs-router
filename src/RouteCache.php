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
    /**
     * Path-prefix used by defaultPath() under sys_get_temp_dir().
     */
    public const DEFAULT_PATH_PREFIX = 'ccglabs-router-';

    /**
     * Returns the default cache file path: a PHP file in the system
     * temp directory whose name is derived from the current working
     * directory so that different projects on the same host get
     * different default cache files.
     */
    public static function defaultPath(): string
    {
        $cwd = getcwd() ?: __FILE__;
        $hash = substr(md5($cwd), 0, 16);
        return sys_get_temp_dir() . DIRECTORY_SEPARATOR . self::DEFAULT_PATH_PREFIX . $hash . '.php';
    }

    /**
     * Path-string to token-list map. Loaded from the cache file at
     * construction; mutated by record() during route registration;
     * trimmed and rewritten by persist().
     *
     * @var array<string, string[]>
     */
    private array $tokens = [];

    /**
     * Set of path-strings recorded or hit during this run. Used by
     * persist() to prune entries no longer in the route table.
     *
     * @var array<string, true>
     */
    private array $touched = [];

    /**
     * Whether persist() needs to write the cache file.
     */
    private bool $dirty = false;

    /**
     * @param string|false $path Path to the cache file, or false to disable caching.
     */
    public function __construct(private string|false $path)
    {
        if ($this->path !== false) {
            $this->load();
        }
    }

    public function isEnabled(): bool
    {
        return $this->path !== false;
    }

    /**
     * Returns the cached tokens for $path, or null if not cached.
     * Calling this also marks the path as touched so persist() retains it.
     *
     * @return string[]|null
     */
    public function getCached(string $path): ?array
    {
        if (! isset($this->tokens[$path])) {
            return null;
        }

        $this->touched[$path] = true;
        return $this->tokens[$path];
    }

    /**
     * Records that $path was registered with the given tokens. Used both
     * for newly-parsed routes (cold misses) and for re-asserting cached
     * routes (so they're retained on persist).
     *
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
     * Writes the cache file if anything changed during this run.
     * Silently no-ops if caching is disabled. Silently swallows write
     * failures (e.g. unwritable destination).
     */
    public function persist(): void
    {
        if ($this->path === false) {
            return;
        }

        // Detect entries that were in the cache but never touched this run.
        // Those are routes that have been removed from user code; prune them.
        $stale = array_diff_key($this->tokens, $this->touched);
        if (! empty($stale)) {
            $this->tokens = array_intersect_key($this->tokens, $this->touched);
            $this->dirty = true;
        }

        if (! $this->dirty) {
            return;
        }

        $contents = "<?php\n\nreturn " . var_export($this->tokens, true) . ";\n";

        try {
            $dir = dirname($this->path);
            if (! is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            @file_put_contents($this->path, $contents, LOCK_EX);
        } catch (Throwable) {
            // Caching is best-effort. Failures are silent by design.
        }

        $this->dirty = false;
    }

    /**
     * Loads the cache file if it exists. Silently no-ops on read or
     * parse failure.
     */
    private function load(): void
    {
        if (! is_string($this->path) || ! is_file($this->path) || ! is_readable($this->path)) {
            return;
        }

        try {
            $loaded = @include $this->path;
            if (is_array($loaded)) {
                $this->tokens = $loaded;
            }
        } catch (Throwable) {
            // Corrupt cache file: behave as if it didn't exist.
        }
    }
}
