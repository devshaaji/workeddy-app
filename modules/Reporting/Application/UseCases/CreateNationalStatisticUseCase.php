<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application\UseCases;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissions;
use WorkEddy\Modules\Reporting\Domain\Contracts\INationalStatisticRepository;
use WorkEddy\Modules\Reporting\Domain\NationalStatistic;
use WorkEddy\Modules\Reporting\Domain\NationalStatisticCategory;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class CreateNationalStatisticUseCase
{
    public function __construct(
        private readonly INationalStatisticRepository $statistics,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
        private readonly IClock $clock,
    ) {}

    /**
     * @param array<string, mixed> $input
     * @return array<string, mixed>
     */
    public function execute(array $input, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, ReportingPermissions::NATIONAL_CONTEXT_MANAGE);

        $clean = NationalStatisticInput::validate($input);

        $statistic = new NationalStatistic(
            id: null,
            uuid: UuidSupport::generate(),
            title: $clean['title'],
            value: $clean['value'],
            unit: $clean['unit'],
            category: $clean['category'],
            industryRelevance: $clean['industryRelevance'],
            sourceName: $clean['sourceName'],
            sourceYear: $clean['sourceYear'],
            sourceUrl: $clean['sourceUrl'],
            isPublished: $clean['isPublished'],
            dateAdded: $this->clock->now()->format('Y-m-d'),
            createdByUserId: (int) $actor->userId,
            updatedByUserId: (int) $actor->userId,
            createdAt: '',
            updatedAt: '',
        );

        $this->statistics->create($statistic);

        $this->audit->record(
            action: 'reporting.national_statistic.created',
            entityType: 'NationalStatistic',
            entityId: $statistic->uuid,
            afterState: $statistic->toView(),
            actorId: (string) $actor->userId,
            actorType: 'User',
        );

        return $this->statistics->findByUuid($statistic->uuid)?->toView() ?? $statistic->toView();
    }
}
