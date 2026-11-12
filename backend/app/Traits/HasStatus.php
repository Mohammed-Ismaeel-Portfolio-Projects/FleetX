<?php

namespace App\Traits;

/**
 * Has Status Trait
 * 
 * Provides status-related scopes and methods for models.
 */
trait HasStatus
{
    /**
     * Scope a query to only include records with a specific status.
     */
    public function scopeWithStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope a query to only include active records.
     */
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /**
     * Scope a query to only include inactive records.
     */
    public function scopeInactive($query)
    {
        return $query->where('status', 'inactive');
    }

    /**
     * Check if the model has a specific status.
     */
    public function hasStatus(string $status): bool
    {
        return $this->status === $status;
    }

    /**
     * Update the model's status.
     */
    public function setStatus(string $status): bool
    {
        $this->status = $status;
        return $this->save();
    }
}
