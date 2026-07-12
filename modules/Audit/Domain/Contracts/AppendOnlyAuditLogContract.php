<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Domain\Contracts;

use WorkEddy\Modules\Audit\Application\DTOs\AuditRecord;

interface AppendOnlyAuditLogContract
{
    public function append(AuditRecord $record): void;
}
