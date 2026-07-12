<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class ScheduleFollowUpAssessmentUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
        private readonly ?EventPublisherInterface $events = null,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $actionUuid, UserContext $actor, string $dueDate): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::VERIFY);
        $dueDate = trim($dueDate);
        if ($dueDate === '') {
            throw new ValidationException(['dueDate' => 'Follow-up due date is required.']);
        }

        $action = $this->repository->findActionByUuid(UuidSupport::requireValid($actionUuid, 'actionUuid'));
        if ($action === null || ($actor->organizationId !== null && $actor->organizationId !== $action->organizationId)) {
            throw new NotFoundException('Corrective action not found.');
        }
        $updated = $action->withFollowUpDueDate($dueDate);
        $this->repository->updateAction($updated);
        $this->repository->createOrUpdateFollowUp($updated->uuid, $dueDate);
        $this->audit->record('corrective_action.follow_up_scheduled', 'corrective_action', $updated->uuid, afterState: $updated->toView(), actorId: (string) $actor->userId, actorType: 'user');
        $this->events?->publish('corrective_action.follow_up_scheduled', $updated->toView(), 'corrective_action.follow_up_scheduled:' . $updated->uuid . ':' . $dueDate);

        return $updated->toView();
    }
}
