<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application;

use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Platform\Events\EventPublisherInterface;

final class RunCorrectiveActionMaintenanceUseCase
{
    public function __construct(
        private readonly ICorrectiveActionRepository $repository,
        private readonly EventPublisherInterface $events,
    ) {}

    /** @return array{overdue_actions:int,follow_ups_due:int} */
    public function execute(string $today, int $limit = 100): array
    {
        $overdue = 0;
        foreach ($this->repository->listDueActions($today, $limit) as $action) {
            $updated = $action->transition('overdue');
            $this->repository->updateAction($updated);
            $this->repository->addStatusHistory([
                'actionUuid' => $updated->uuid,
                'status' => 'overdue',
                'actorId' => 0,
                'notes' => 'Marked overdue by corrective action maintenance.',
            ]);
            $this->events->publish('corrective_action.overdue', $updated->toView(), 'corrective_action.overdue:' . $updated->uuid);
            $overdue++;
        }

        $followUpsDue = 0;
        foreach ($this->repository->listDueFollowUps($today, $limit) as $followUp) {
            $actionUuid = (string) $followUp['action_uuid'];
            $this->events->publish('corrective_action.follow_up_due', [
                'actionUuid' => $actionUuid,
                'dueDate' => (string) $followUp['due_date'],
                'followUpAssessmentUuid' => $followUp['follow_up_assessment_uuid'] ?? null,
            ], 'corrective_action.follow_up_due:' . $actionUuid . ':' . (string) $followUp['due_date']);
            $this->repository->updateFollowUpStatus($actionUuid, 'due');
            $followUpsDue++;
        }

        return ['overdue_actions' => $overdue, 'follow_ups_due' => $followUpsDue];
    }
}
