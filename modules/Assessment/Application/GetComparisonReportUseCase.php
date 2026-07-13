<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\WrongScopeException;
use WorkEddy\Shared\Support\UuidSupport;

final class GetComparisonReportUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IPermissionService $permissions,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $comparisonReportUuid, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::VIEW_COMPARISON);
        $report = $this->assessments->findComparisonReportByUuid(UuidSupport::requireValid($comparisonReportUuid, 'comparisonReportUuid'));
        if ($report === null) {
            throw new NotFoundException('Comparison report not found.');
        }
        if ($actor->organizationId !== null && $actor->organizationId !== $report->organizationId) {
            throw new WrongScopeException('This comparison report belongs to a different organization scope.');
        }

        return $report->toView();
    }
}
