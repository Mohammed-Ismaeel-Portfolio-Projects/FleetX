<?php

namespace App\Simulation\Domain\Telemetry\Configuration;

/**
 * TelemetryConfiguration.
 * Configuration for telemetry processing.
 */
class TelemetryConfiguration
{
    public function __construct(
        private int $collectionIntervalMs = 1000, // Default 1 second
        private bool $enableValidation = true,
        private string $defaultEncoder = 'json',
        private string $defaultPublisher = 'event_bus',
        private array $encoderOptions = [],
        private array $publisherOptions = [],
    ) {}

    /**
     * Create from array.
     */
    public static function fromArray(array $config): self
    {
        return new self(
            collectionIntervalMs: $config['collection_interval_ms'] ?? 1000,
            enableValidation: $config['enable_validation'] ?? true,
            defaultEncoder: $config['default_encoder'] ?? 'json',
            defaultPublisher: $config['default_publisher'] ?? 'event_bus',
            encoderOptions: $config['encoder_options'] ?? [],
            publisherOptions: $config['publisher_options'] ?? [],
        );
    }

    /**
     * Convert to array.
     */
    public function toArray(): array
    {
        return [
            'collection_interval_ms' => $this->collectionIntervalMs,
            'enable_validation' => $this->enableValidation,
            'default_encoder' => $this->defaultEncoder,
            'default_publisher' => $this->defaultPublisher,
            'encoder_options' => $this->encoderOptions,
            'publisher_options' => $this->publisherOptions,
        ];
    }

    /**
     * Get collection interval in milliseconds.
     */
    public function getCollectionIntervalMs(): int
    {
        return $this->collectionIntervalMs;
    }

    /**
     * Set collection interval in milliseconds.
     */
    public function setCollectionIntervalMs(int $intervalMs): void
    {
        $this->collectionIntervalMs = $intervalMs;
    }

    /**
     * Check if validation is enabled.
     */
    public function isValidationEnabled(): bool
    {
        return $this->enableValidation;
    }

    /**
     * Enable or disable validation.
     */
    public function setValidationEnabled(bool $enabled): void
    {
        $this->enableValidation = $enabled;
    }

    /**
     * Get default encoder.
     */
    public function getDefaultEncoder(): string
    {
        return $this->defaultEncoder;
    }

    /**
     * Set default encoder.
     */
    public function setDefaultEncoder(string $encoder): void
    {
        $this->defaultEncoder = $encoder;
    }

    /**
     * Get default publisher.
     */
    public function getDefaultPublisher(): string
    {
        return $this->defaultPublisher;
    }

    /**
     * Set default publisher.
     */
    public function setDefaultPublisher(string $publisher): void
    {
        $this->defaultPublisher = $publisher;
    }

    /**
     * Get encoder options.
     */
    public function getEncoderOptions(): array
    {
        return $this->encoderOptions;
    }

    /**
     * Set encoder options.
     */
    public function setEncoderOptions(array $options): void
    {
        $this->encoderOptions = $options;
    }

    /**
     * Get publisher options.
     */
    public function getPublisherOptions(): array
    {
        return $this->publisherOptions;
    }

    /**
     * Set publisher options.
     */
    public function setPublisherOptions(array $options): void
    {
        $this->publisherOptions = $options;
    }
}
