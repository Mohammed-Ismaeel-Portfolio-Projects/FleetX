<?php

namespace App\Actions;

use App\Contracts\ActionInterface;
use App\Core\ApiResult;

/**
 * Base Action
 * 
 * Base class for all actions.
 * Actions represent single-use operations or commands.
 */
abstract class BaseAction implements ActionInterface
{
    /**
     * Execute the action.
     */
    abstract public function execute(): ApiResult;

    /**
     * Validate action parameters.
     */
    protected function validate(): bool
    {
        return true;
    }

    /**
     * Get validation errors.
     */
    protected function getValidationErrors(): array
    {
        return [];
    }
}
