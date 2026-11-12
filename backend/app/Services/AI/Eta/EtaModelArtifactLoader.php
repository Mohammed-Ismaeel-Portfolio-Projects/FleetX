<?php

namespace App\Services\AI\Eta;

use Illuminate\Support\Facades\Config;

class EtaModelArtifactLoader
{
    public function isArtifactReady(): bool
    {
        return file_exists($this->getScriptPath())
            && file_exists($this->getModelPath())
            && file_exists($this->getManifestPath())
            && file_exists($this->getMetadataPath());
    }

    public function getScriptPath(): string
    {
        return Config::get('ai.eta.script_path', base_path('ai/eta/predict_residual.py'));
    }

    public function getModelPath(): string
    {
        return Config::get('ai.eta.models_dir', base_path('ai/eta/models')) . '/eta_residual_model.joblib';
    }

    public function getManifestPath(): string
    {
        return Config::get('ai.eta.manifest_path', base_path('ai/eta/models/feature_manifest.json'));
    }

    public function getMetadataPath(): string
    {
        return Config::get('ai.eta.metadata_path', base_path('ai/eta/models/model_metadata.json'));
    }

    /**
     * Single authoritative source for model versioning.
     */
    public function getModelVersion(): string
    {
        $metadata = $this->getMetadata();

        return (string) ($metadata['model_version'] ?? $metadata['hyperparameters']['version'] ?? 'v1.0');
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getManifest(): ?array
    {
        $path = $this->getManifestPath();
        if (! file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if (! is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getMetadata(): ?array
    {
        $path = $this->getMetadataPath();
        if (! file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if (! is_string($content)) {
            return null;
        }

        $decoded = json_decode($content, true);

        return is_array($decoded) ? $decoded : null;
    }
}
