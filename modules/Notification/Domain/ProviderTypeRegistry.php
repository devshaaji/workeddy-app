<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Notification\Domain;

final class ProviderTypeRegistry
{
    /** @return array<string, ProviderTypeDefinition> */
    public static function all(): array
    {
        return [
            'twilio' => new ProviderTypeDefinition(
                type: 'twilio',
                channels: ['sms', 'whatsapp'],
                requiredFields: ['account_sid', 'auth_token', 'sms_from'],
                sensitiveFields: ['auth_token'],
                optionalFields: ['whatsapp_from', 'status_callback_url']
            ),
            'smtp' => new ProviderTypeDefinition(
                type: 'smtp',
                channels: ['email'],
                requiredFields: ['host', 'port'],
                sensitiveFields: ['pass'],
                optionalFields: ['user', 'encryption']
            )
        ];
    }

    public static function get(string $type): ?ProviderTypeDefinition
    {
        return self::all()[$type] ?? null;
    }

    public static function validate(string $providerType, array $config): ?string
    {
        $def = self::get($providerType);
        if (!$def) {
            return "Unknown provider type: $providerType";
        }

        foreach ($def->requiredFields as $field) {
            if (!isset($config[$field]) || $config[$field] === '') {
                return "Missing required field '$field' for provider type '$providerType'";
            }
        }

        return null;
    }
}
