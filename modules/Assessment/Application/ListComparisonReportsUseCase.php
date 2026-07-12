<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;

final class ListComparisonReportsUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IPermissionService $permissions,
    ) {}

    /** @param array<string, mixed> $filters @return list<array<string, mixed>> */
    public function execute(UserContext $actor, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::VIEW_COMPARISON);
        $organizationId = $actor->organizationId ?? 0;

        return array_map(
            static fn($report): array => $report->toView(),
            $this->assessments->findComparisonReportsByOrganizationId($organizationId, $filters, $limit, $offset),
        );
    }
}
