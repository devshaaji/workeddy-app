<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Infrastructure;

use WorkEddy\Modules\Audit\Domain\Contracts\AppendOnlyAuditLogContract;
use WorkEddy\Modules\Audit\Application\DTOs\AuditRecord;

final class InMemoryAuditLog implements AppendOnlyAuditLogContract
{
    /** @var list<AuditRecord> */
    private array $records = [];

    public function append(AuditRecord $record): void
    {
        $this->records[] = $record;
    }

    /**
     * @return list<AuditRecord>
     */
    public function records(): array
    {
        return $this->records;
    }
}
