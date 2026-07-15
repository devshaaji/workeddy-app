<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application\UseCases;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissions;
use WorkEddy\Modules\Reporting\Domain\Contracts\INationalStatisticRepository;
use WorkEddy\Modules\Reporting\Domain\NationalStatistic;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class UpdateNationalStatisticUseCase
{
    public function __construct(
        private readonly INationalStatisticRepository $statistics,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
    ) {}

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function execute(string $uuid, array $input, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, ReportingPermissions::NATIONAL_CONTEXT_MANAGE);

        $existing = $this->statistics->findByUuid($uuid);
        if ($existing === null) {
            throw new NotFoundException('National statistic not found.');
        }

        $clean = NationalStatisticInput::validate($input);
        $before = $existing->toView();

        $updated = new NationalStatistic(
            id: $existing->id,
            uuid: $existing->uuid,
            title: $clean['title'],
            value: $clean['value'],
            unit: $clean['unit'],
            category: $clean['category'],
            industryRelevance: $clean['industryRelevance'],
            sourceName: $clean['sourceName'],
            sourceYear: $clean['sourceYear'],
            sourceUrl: $clean['sourceUrl'],
            isPublished: $clean['isPublished'],
            dateAdded: $existing->dateAdded,
            createdByUserId: $existing->createdByUserId,
            updatedByUserId: (int) $actor->userId,
            createdAt: $existing->createdAt,
            updatedAt: '',
        );

        $this->statistics->update($updated);

        $after = $this->statistics->findByUuid($uuid)?->toView() ?? $updated->toView();

        $this->audit->record(
            action: 'reporting.national_statistic.updated',
            entityType: 'NationalStatistic',
            entityId: $uuid,
            beforeState: $before,
            afterState: $after,
            actorId: (string) $actor->userId,
            actorType: 'User',
        );

        return $after;
    }
}
