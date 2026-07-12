<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Application;

use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class LockComparisonReportUseCase
{
    public function __construct(
        private readonly IAssessmentRepository $assessments,
        private readonly IPermissionService $permissions,
        private readonly TransactionManagerInterface $tx,
        private readonly IAuditService $audit,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $comparisonReportUuid, UserContext $actor): array
    {
        $this->permissions->requirePrivilege($actor, AssessmentPermissions::LOCK_COMPARISON);
        $report = $this->assessments->findComparisonReportByUuid(UuidSupport::requireValid($comparisonReportUuid, 'comparisonReportUuid'));
        if ($report === null || ($actor->organizationId !== null && $actor->organizationId !== $report->organizationId)) {
            throw new NotFoundException('Comparison report not found.');
        }

        $before = $report->toView();
        $locked = $report->lock();
        $this->tx->transactional(fn() => $this->assessments->updateComparisonReport($locked));
        $result = $locked->toView();
        $this->audit->record('comparison_report.locked', 'comparison_report', $locked->uuid, beforeState: $before, afterState: $result, actorId: (string) $actor->userId, actorType: 'user');

        return $result;
    }
}
