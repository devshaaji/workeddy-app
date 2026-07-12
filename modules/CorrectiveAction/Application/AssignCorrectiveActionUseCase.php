<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Authorization\CorrectiveActionPermissions;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveAction;
use WorkEddy\Modules\CorrectiveAction\Settings\CorrectiveActionSettings;
use WorkEddy\Modules\IAM\Domain\Contracts\IUserRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Events\EventPublisherInterface;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Exceptions\ValidationException;
use WorkEddy\Shared\Support\UuidSupport;

final class AssignCorrectiveActionUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly CorrectiveActionSettings $settings,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
        private readonly ?IUserRepository $users = null,
        private readonly ?EventPublisherInterface $events = null,
    ) {}

    /** @return array<string, mixed> */
    public function execute(string $recommendationUuid, UserContext $actor, int|string $assignedTo, ?string $dueDate = null, ?string $followUpDueDate = null): array
    {
        $this->permissions->requirePrivilege($actor, CorrectiveActionPermissions::ASSIGN);
        $recommendation = $this->repository->findRecommendationByUuid(UuidSupport::requireValid($recommendationUuid, 'recommendationUuid'));
        if ($recommendation === null || ($actor->organizationId !== null && $actor->organizationId !== $recommendation->organizationId)) {
            throw new NotFoundException('Recommendation not found.');
        }
        if ($recommendation->status !== 'accepted') {
            throw new ValidationException(['recommendation' => 'Only accepted recommendations can be assigned.']);
        }

        $assignedToUserId = $this->resolveAssignedUserId($assignedTo, $actor);
        $dueDate ??= (new \DateTimeImmutable('today'))->modify('+' . ($recommendation->dueDays ?? $this->settings->defaultDueDays()) . ' days')->format('Y-m-d');
        $action = new CorrectiveAction(
            id: null,
            uuid: UuidSupport::generate(),
            organizationId: $recommendation->organizationId,
            organizationUuid: $recommendation->organizationUuid,
            assessmentUuid: $recommendation->assessmentUuid,
            recommendationUuid: $recommendation->uuid,
            libraryItemUuid: $recommendation->libraryItemUuid,
            title: $recommendation->title,
            description: $recommendation->description,
            reason: $recommendation->reason,
            controlType: $recommendation->controlType,
            hierarchyLevel: $recommendation->hierarchyLevel,
            priority: $recommendation->priority,
            status: 'assigned',
            assignedToUserId: $assignedToUserId,
            assignedByUserId: $actor->userId,
            dueDate: $dueDate,
            followUpAssessmentDueDate: $followUpDueDate ?? $this->defaultFollowUpDate($dueDate, $recommendation->followUpDays),
            evidenceRequirements: is_array($recommendation->evidence['evidence_types'] ?? null) ? $recommendation->evidence['evidence_types'] : [],
        );
        $this->repository->createAction($action);
        $this->repository->addStatusHistory(['actionUuid' => $action->uuid, 'status' => 'assigned', 'actorId' => $actor->userId, 'notes' => null]);
        $this->audit->record('corrective_action.assigned', 'corrective_action', $action->uuid, afterState: $action->toView(), actorId: (string) $actor->userId, actorType: 'user');
        $this->events?->publish('corrective_action.assigned', $action->toView(), 'corrective_action.assigned:' . $action->uuid);

        return $action->toView();
    }

    private function resolveAssignedUserId(int|string $assignedTo, UserContext $actor): int
    {
        if (is_int($assignedTo) || ctype_digit((string) $assignedTo)) {
            $id = (int) $assignedTo;
            if ($id > 0) {
                return $id;
            }
        }

        $userUuid = trim((string) $assignedTo);
        if ($userUuid === '') {
            throw new ValidationException(['assignedToUserUuid' => 'A responsible person is required.']);
        }
        if ($this->users === null) {
            throw new ValidationException(['assignedToUserUuid' => 'Assigned user directory is unavailable.']);
        }

        $user = $this->users->findByUuid(UuidSupport::requireValid($userUuid, 'assignedToUserUuid'));
        if ($user === null || $user->getId() === null) {
            throw new NotFoundException('Assigned user not found.');
        }
        if ($actor->organizationId !== null && $user->getOrganizationId() !== null && $actor->organizationId !== $user->getOrganizationId()) {
            throw new NotFoundException('Assigned user not found.');
        }

        return (int) $user->getId();
    }

    private function defaultFollowUpDate(string $dueDate, ?int $followUpDays): ?string
    {
        if ($followUpDays === null) {
            return null;
        }

        return (new \DateTimeImmutable($dueDate))->modify('+' . $followUpDays . ' days')->format('Y-m-d');
    }
}
