<?php

namespace App\Simulation\Routing\Infrastructure\Cache;

/**
 * CacheManager.
 * Cache manager for routing results.
 * 
 * Responsibilities:
 * - Route cache
 * - TTL management
 * - Cache invalidation
 * - Provider cache
 * - Future Redis support
 */
class CacheManager
{
    /**
     * In-memory cache storage.
     * @var array<string, mixed>
     */
    private array $cache = [];

    /**
     * Cache TTL storage.
     * @var array<string, int>
     */
    private array $ttl = [];

    /**
     * Cache creation times.
     * @var array<string, float>
     */
    private array $createdAt = [];

    /**
     * Default TTL in seconds.
     * @var int
     */
    private int $defaultTTL = 3600;

    /**
     * Cache prefix.
     * @var string
     */
    private string $prefix = 'routing_';

    /**
     * Constructor.
     * 
     * @param int $defaultTTL Default TTL in seconds
     * @param string $prefix Cache prefix
     */
    public function __construct(int $defaultTTL = 3600, string $prefix = 'routing_')
    {
        $this->defaultTTL = $defaultTTL;
        $this->prefix = $prefix;
    }

    /**
     * Get value from cache.
     * 
     * @param string $key The cache key
     * @return mixed The cached value, or null if not found or expired
     */
    public function get(string $key)
    {
        $fullKey = $this->prefix . $key;

        if (!isset($this->cache[$fullKey])) {
            return null;
        }

        // Check if expired
        if ($this->isExpired($fullKey)) {
            $this->delete($key);
            return null;
        }

        return $this->cache[$fullKey];
    }

    /**
     * Set value in cache.
     * 
     * @param string $key The cache key
     * @param mixed $value The value to cache
     * @param int $ttl TTL in seconds
     */
    public function set(string $key, $value, int $ttl = null): void
    {
        $fullKey = $this->prefix . $key;
        $ttl = $ttl ?? $this->defaultTTL;

        $this->cache[$fullKey] = $value;
        $this->ttl[$fullKey] = $ttl;
        $this->createdAt[$fullKey] = microtime(true);
    }

    /**
     * Delete value from cache.
     * 
     * @param string $key The cache key
     */
    public function delete(string $key): void
    {
        $fullKey = $this->prefix . $key;

        unset(
            $this->cache[$fullKey],
            $this->ttl[$fullKey],
            $this->createdAt[$fullKey]
        );
    }

    /**
     * Clear all cache.
     */
    public function clear(): void
    {
        $this->cache = [];
        $this->ttl = [];
        $this->createdAt = [];
    }

    /**
     * Check if key exists in cache.
     * 
     * @param string $key The cache key
     * @return bool True if exists, false otherwise
     */
    public function has(string $key): bool
    {
        $fullKey = $this->prefix . $key;

        if (!isset($this->cache[$fullKey])) {
            return false;
        }

        // Check if expired
        if ($this->isExpired($fullKey)) {
            $this->delete($key);
            return false;
        }

        return true;
    }

    /**
     * Invalidate cache by pattern.
     * 
     * @param string $pattern The pattern to match
     */
    public function invalidatePattern(string $pattern): void
    {
        foreach (array_keys($this->cache) as $key) {
            if (fnmatch($pattern, $key)) {
                $this->delete(str_replace($this->prefix, '', $key));
            }
        }
    }

    /**
     * Get cache statistics.
     * 
     * @return array The cache statistics
     */
    public function getStats(): array
    {
        return [
            'count' => count($this->cache),
            'keys' => array_keys($this->cache),
        ];
    }

    /**
     * Check if cache entry is expired.
     * 
     * @param string $fullKey The full cache key
     * @return bool True if expired, false otherwise
     */
    private function isExpired(string $fullKey): bool
    {
        if (!isset($this->createdAt[$fullKey]) || !isset($this->ttl[$fullKey])) {
            return true;
        }

        $elapsed = microtime(true) - $this->createdAt[$fullKey];
        return $elapsed >= $this->ttl[$fullKey];
    }

    /**
     * Get default TTL.
     * 
     * @return int The TTL in seconds
     */
    public function getDefaultTTL(): int
    {
        return $this->defaultTTL;
    }

    /**
     * Set default TTL.
     * 
     * @param int $ttl The TTL in seconds
     */
    public function setDefaultTTL(int $ttl): void
    {
        $this->defaultTTL = $ttl;
    }

    /**
     * Get cache prefix.
     * 
     * @return string The prefix
     */
    public function getPrefix(): string
    {
        return $this->prefix;
    }

    /**
     * Set cache prefix.
     * 
     * @param string $prefix The prefix
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    /**
     * Clean expired entries.
     */
    public function cleanExpired(): void
    {
        foreach (array_keys($this->cache) as $key) {
            if ($this->isExpired($key)) {
                $this->delete(str_replace($this->prefix, '', $key));
            }
        }
    }
}
