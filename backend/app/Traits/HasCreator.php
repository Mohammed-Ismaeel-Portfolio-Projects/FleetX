<?php

namespace App\Traits;

/**
 * Has Creator Trait
 * 
 * Automatically tracks the creator of the model.
 */
trait HasCreator
{
    /**
     * Boot the trait.
     */
    protected static function bootHasCreator(): void
    {
        static::creating(function ($model) {
            if (auth()->check() && empty($model->created_by)) {
                $model->created_by = auth()->id();
            }
        });
    }

    /**
     * Relationship to the creator.
     */
    public function creator()
    {
        return $this->belongsTo(config('auth.providers.users.model'), 'created_by');
    }
}
