<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Audit;

interface AppendOnlyAuditLogContract
{
    public function append(AuditRecord $record): void;
}
