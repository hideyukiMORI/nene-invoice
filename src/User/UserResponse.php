<?php

declare(strict_types=1);

namespace NeneInvoice\User;

/**
 * Serializes a {@see User} to its snake_case JSON representation.
 * Never exposes `password_hash`.
 */
final class UserResponse
{
    /** @return array<string, mixed> */
    public static function toArray(User $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'role' => $user->role->value,
            'organization_id' => $user->organizationId,
            'status' => $user->status,
            'created_at' => $user->createdAt,
            'updated_at' => $user->updatedAt,
        ];
    }
}
