<?php

namespace App\Support;

/**
 * Driver-facing assignment status key projection (D2).
 * Does not rename or invent backend statuses — maps live Status.name → stable key.
 */
final class DriverAssignmentStatusKey
{
    public static function fromStatusName(?string $name): ?string
    {
        if ($name === null) {
            return null;
        }

        $trimmed = trim($name);
        if ($trimmed === '') {
            return null;
        }

        return strtolower(str_replace(' ', '_', $trimmed));
    }
}
