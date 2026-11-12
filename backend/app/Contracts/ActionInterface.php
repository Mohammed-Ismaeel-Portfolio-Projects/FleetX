<?php

namespace App\Contracts;

/**
 * Action Interface
 * 
 * Base interface for all actions.
 * Actions represent single-use operations or commands.
 */
interface ActionInterface
{
    /**
     * Execute the action.
     */
    public function execute(): mixed;
}
