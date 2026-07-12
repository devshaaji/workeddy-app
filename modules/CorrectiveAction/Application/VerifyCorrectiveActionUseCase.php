<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Application\Services\ControlActionWorkflowService;
use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Settings\CorrectiveActionSettings;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\UuidSupport;

final class VerifyCorrectiveActionUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly ControlActionWorkflowService $workflow,
        private readonly CorrectiveActionSettings $settings,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
        private readonly ?EventPublisherInterface $events = null,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $actionUuid, UserContext $actor, ?string $notes = null): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::VERIFY);
        $action = $this->repository->findActionByUuid(UuidSupport::requireValid($actionUuid, 'actionUuid'));
        if ($action === null || ($actor->organizationId !== null && $actor->organizationId !== $action->organizationId)) {
            throw new NotFoundException('Corrective action not found.');
        }
        $this->workflow->assertCanVerify($action);
        $followUpDueDate = $action->followUpAssessmentDueDate ?? $this->workflow->followUpDueDate($this->settings->followUpDaysAfterVerification());
        $verified = $action->transition('verified')->withFollowUpDueDate($followUpDueDate);
        $this->repository->updateAction($verified);
        $this->repository->createOrUpdateFollowUp($verified->uuid, (string) $followUpDueDate);
        $this->repository->addStatusHistory(['actionUuid' => $verified->uuid, 'status' => 'verified', 'actorId' => $actor->userId, 'notes' => $notes]);
        $this->audit->record('corrective_action.verified', 'corrective_action', $verified->uuid, beforeState: $action->toView(), afterState: $verified->toView(), actorId: (string) $actor->userId, actorType: 'user');
        $this->events?->publish('corrective_action.verified', $verified->toView(), 'corrective_action.verified:' . $verified->uuid);

        return $verified->toView();
    }
}
