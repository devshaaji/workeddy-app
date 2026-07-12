<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Platform;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Transport\TransportSchema;
use Doctrine\DBAL\Schema\Schema;

final class TransportSchemaBuilder implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'platform_transport';
    }

    public function tables(): array
    {
        return [
            'transport_destinations',
            'transport_outbox',
            'transport_outbox_attempts',
            'transport_inbound_sources',
            'transport_inbox',
            'transport_inbox_attempts',
        ];
    }

    public function build(Schema $schema): void
    {
        TransportSchema::apply($schema);
    }
}
