<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Application\UseCases;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissions;
use WorkEddy\Modules\Reporting\Domain\Contracts\INationalStatisticRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;

final class DeleteNationalStatisticUseCase
{
    public function __construct(
        private readonly INationalStatisticRepository $statistics,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
    ) {}

    public function execute(string $uuid, UserContext $actor): void
    {
        $this->permissions->requirePrivilege($actor, ReportingPermissions::NATIONAL_CONTEXT_MANAGE);

        $existing = $this->statistics->findByUuid($uuid);
        if ($existing === null) {
            throw new NotFoundException('National statistic not found.');
        }

        $this->statistics->delete($uuid);

        $this->audit->record(
            action: 'reporting.national_statistic.deleted',
            entityType: 'NationalStatistic',
            entityId: $uuid,
            beforeState: $existing->toView(),
            actorId: (string) $actor->userId,
            actorType: 'User',
        );
    }
}
