<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Presentation;

use WorkEddy\Modules\CorrectiveAction\Application\AssignCorrectiveActionUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\GenerateRecommendationsUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\GetCorrectiveActionUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ListCorrectiveActionsUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ListCorrectiveActionLibraryUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ListRecommendationRulesUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ListRecommendationsByAssessmentUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ReviewRecommendationUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\ScheduleFollowUpAssessmentUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\UpdateCorrectiveActionStatusUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\UpsertCorrectiveActionLibraryItemUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\UpsertRecommendationRuleUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\UploadCorrectiveActionEvidenceUseCase;
use WorkEddy\Modules\CorrectiveAction\Application\VerifyCorrectiveActionUseCase;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;

final class CorrectiveActionController
{
    public function __construct(
        private readonly GenerateRecommendationsUseCase $generateRecommendations,
        private readonly ReviewRecommendationUseCase $reviewRecommendation,
        private readonly AssignCorrectiveActionUseCase $assignAction,
        private readonly ScheduleFollowUpAssessmentUseCase $scheduleFollowUp,
        private readonly UpdateCorrectiveActionStatusUseCase $updateStatus,
        private readonly UploadCorrectiveActionEvidenceUseCase $uploadEvidence,
        private readonly VerifyCorrectiveActionUseCase $verifyAction,
        private readonly ListCorrectiveActionsUseCase $listActions,
        private readonly GetCorrectiveActionUseCase $getAction,
        private readonly ListRecommendationsByAssessmentUseCase $listRecommendations,
        private readonly ListCorrectiveActionLibraryUseCase $listLibrary,
        private readonly UpsertCorrectiveActionLibraryItemUseCase $upsertLibraryItem,
        private readonly ListRecommendationRulesUseCase $listRules,
        private readonly UpsertRecommendationRuleUseCase $upsertRule,
        private readonly ISessionService $session,
    ) {}

    public function generateRecommendations(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->generateRecommendations->execute((string) $request->routeParam('assessmentId'), $this->actor())]);
    }

    public function listRecommendations(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->listRecommendations->execute((string) $request->routeParam('assessmentId'), $this->actor())]);
    }

    public function listActions(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->listActions->execute($this->actor(), $request->query)]);
    }

    public function getAction(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->getAction->execute((string) $request->routeParam('actionId'), $this->actor())]);
    }

    public function acceptRecommendation(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->reviewRecommendation->accept((string) $request->routeParam('recommendationId'), $this->actor(), $this->data($request))]);
    }

    public function rejectRecommendation(Request $request): Response
    {
        $body = $this->data($request);
        return Response::json(['status' => 'ok', 'data' => $this->reviewRecommendation->reject((string) $request->routeParam('recommendationId'), $this->actor(), isset($body['reason']) ? (string) $body['reason'] : null)]);
    }

    public function assign(Request $request): Response
    {
        $body = $this->data($request);
        $dueDate = isset($body['dueDate']) ? (string) $body['dueDate'] : (isset($body['due_date']) ? (string) $body['due_date'] : null);
        $followUpDueDate = isset($body['followUpDueDate']) ? (string) $body['followUpDueDate'] : (isset($body['follow_up_due_date']) ? (string) $body['follow_up_due_date'] : null);
        $assignedTo = $body['assignedToUserUuid']
            ?? $body['assigned_to_user_uuid']
            ?? $body['assignedToUserId']
            ?? $body['assigned_to_user_id']
            ?? '';

        return Response::json(['status' => 'ok', 'data' => $this->assignAction->execute((string) $request->routeParam('recommendationId'), $this->actor(), is_int($assignedTo) ? $assignedTo : (string) $assignedTo, $dueDate, $followUpDueDate)], 201);
    }

    public function updateStatus(Request $request): Response
    {
        $body = $this->data($request);
        return Response::json(['status' => 'ok', 'data' => $this->updateStatus->execute((string) $request->routeParam('actionId'), (string) ($body['status'] ?? ''), $this->actor(), isset($body['notes']) ? (string) $body['notes'] : null)]);
    }

    public function uploadEvidence(Request $request): Response
    {
        $body = $this->data($request);
        $file = $request->files['evidence'] ?? $request->files['file'] ?? [];
        return Response::json(['status' => 'ok', 'data' => $this->uploadEvidence->execute((string) $request->routeParam('actionId'), $this->actor(), is_array($file) ? $file : [], (string) ($body['evidenceType'] ?? $body['evidence_type'] ?? 'photo'), isset($body['notes']) ? (string) $body['notes'] : null)], 201);
    }

    public function verify(Request $request): Response
    {
        $body = $this->data($request);
        return Response::json(['status' => 'ok', 'data' => $this->verifyAction->execute((string) $request->routeParam('actionId'), $this->actor(), isset($body['notes']) ? (string) $body['notes'] : null)]); 
    }

    public function scheduleFollowUp(Request $request): Response
    {
        $body = $this->data($request);
        return Response::json(['status' => 'ok', 'data' => $this->scheduleFollowUp->execute((string) $request->routeParam('actionId'), $this->actor(), (string) ($body['dueDate'] ?? $body['due_date'] ?? ''))]);
    }

    public function listLibrary(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->listLibrary->execute($this->actor(), $request->query)]);
    }

    public function upsertLibraryItem(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->upsertLibraryItem->execute($this->actor(), $this->data($request))], 201);
    }

    public function listRules(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->listRules->execute($this->actor(), $request->query)]);
    }

    public function upsertRule(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->upsertRule->execute($this->actor(), $this->data($request))], 201);
    }

    /** @return array<string, mixed> */
    private function data(Request $request): array
    {
        return array_replace($request->body, $request->json);
    }

    private function actor(): UserContext
    {
        $ctx = $this->session->getUserContext();
        if (!$ctx instanceof UserContext) {
            throw new AuthenticationException('Authentication required.');
        }
        return $ctx;
    }
}
