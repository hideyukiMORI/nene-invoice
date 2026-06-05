<?php

declare(strict_types=1);

namespace NeneInvoice\Client;

/**
 * Serializes a {@see Client} to its snake_case JSON representation.
 */
final class ClientResponse
{
    /** @return array<string, mixed> */
    public static function toArray(Client $client): array
    {
        return [
            'id' => $client->id,
            'organization_id' => $client->organizationId,
            'name' => $client->name,
            'name_kana' => $client->nameKana,
            'contact_name' => $client->contactName,
            'email' => $client->email,
            'billing_address' => $client->billingAddress,
            'registration_number' => $client->registrationNumber,
            'created_at' => $client->createdAt,
            'updated_at' => $client->updatedAt,
        ];
    }
}
