<?php

namespace App\Traits;

/**
 * Has Audit Trait
 * 
 * Automatically tracks created_by, updated_by, created_at, and updated_at.
 */
trait HasAudit
{
    /**
     * Boot the trait.
     */
    protected static function bootHasAudit(): void
    {
        static::creating(function ($model) {
            if (auth()->check()) {
                $id = auth()->id();
                if (is_string($id) && \Illuminate\Support\Str::isUuid($id)) {
                    $model->created_by = $id;
                }
            }
        });

        static::updating(function ($model) {
            if (auth()->check()) {
                $id = auth()->id();
                if (is_string($id) && \Illuminate\Support\Str::isUuid($id)) {
                    $model->updated_by = $id;
                }
            }
        });
    }
}
