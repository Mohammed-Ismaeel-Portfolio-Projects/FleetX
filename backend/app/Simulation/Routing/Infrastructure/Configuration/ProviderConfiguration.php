<?php

namespace App\Simulation\Routing\Infrastructure\Configuration;

/**
 * ProviderConfiguration.
 * Configuration for routing providers.
 * 
 * Responsibilities:
 * - Provider URL
 * - Timeout
 * - Retry count
 * - Authentication
 * - Headers
 * - Feature flags
 * - Rate limits
 */
readonly class ProviderConfiguration
{
    public function __construct(
        public string $baseUrl,
        public string $apiKey,
        public float $timeout, // seconds
        public int $retryCount,
        public string $version,
        public array $headers, // custom headers
        public array $featureFlags, // feature flags
        public array $rateLimits, // rate limits
        public int $cacheTTL, // cache TTL in seconds
        public bool $enabled,
    ) {}

    /**
     * Create from array.
     * 
     * @param array $config The configuration array
     * @return self The configuration
     */
    public static function fromArray(array $config): self
    {
        return new self(
            baseUrl: $config['base_url'] ?? '',
            apiKey: $config['api_key'] ?? '',
            timeout: $config['timeout'] ?? 30.0,
            retryCount: $config['retry_count'] ?? 3,
            version: $config['version'] ?? 'v1',
            headers: $config['headers'] ?? [],
            featureFlags: $config['feature_flags'] ?? [],
            rateLimits: $config['rate_limits'] ?? [],
            cacheTTL: $config['cache_ttl'] ?? 3600,
            enabled: $config['enabled'] ?? true,
        );
    }

    /**
     * Convert to array.
     * 
     * @return array The configuration array
     */
    public function toArray(): array
    {
        return [
            'base_url' => $this->baseUrl,
            'api_key' => $this->apiKey,
            'timeout' => $this->timeout,
            'retry_count' => $this->retryCount,
            'version' => $this->version,
            'headers' => $this->headers,
            'feature_flags' => $this->featureFlags,
            'rate_limits' => $this->rateLimits,
            'cache_ttl' => $this->cacheTTL,
            'enabled' => $this->enabled,
        ];
    }

    /**
     * Get base URL.
     * 
     * @return string The base URL
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get timeout.
     * 
     * @return float The timeout in seconds
     */
    public function getTimeout(): float
    {
        return $this->timeout;
    }

    /**
     * Get version.
     * 
     * @return string The version
     */
    public function getVersion(): string
    {
        return $this->version;
    }

    /**
     * Get cache TTL.
     * 
     * @return int The cache TTL in seconds
     */
    public function getCacheTTL(): int
    {
        return $this->cacheTTL;
    }

    /**
     * Check if has authentication.
     * 
     * @return bool True if has authentication, false otherwise
     */
    public function hasAuthentication(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get authentication headers.
     * 
     * @return array The authentication headers
     */
    public function getAuthenticationHeaders(): array
    {
        if (!$this->hasAuthentication()) {
            return [];
        }

        return [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'X-API-Key' => $this->apiKey,
        ];
    }

    /**
     * Get headers.
     * 
     * @return array The headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get feature flag.
     * 
     * @param string $flag The flag name
     * @param mixed $default The default value
     * @return mixed The flag value
     */
    public function getFeatureFlag(string $flag, $default = null)
    {
        return $this->featureFlags[$flag] ?? $default;
    }

    /**
     * Check if feature flag is enabled.
     * 
     * @param string $flag The flag name
     * @return bool True if enabled, false otherwise
     */
    public function isFeatureEnabled(string $flag): bool
    {
        return (bool) ($this->featureFlags[$flag] ?? false);
    }

    /**
     * Get rate limit.
     * 
     * @param string $limit The limit name
     * @param mixed $default The default value
     * @return mixed The rate limit value
     */
    public function getRateLimit(string $limit, $default = null)
    {
        return $this->rateLimits[$limit] ?? $default;
    }

    /**
     * Check if enabled.
     * 
     * @return bool True if enabled, false otherwise
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }
}
