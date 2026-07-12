<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Presentation;

use WorkEddy\Modules\WorkerVoice\Application\GetWorkerFeedbackTrendsUseCase;
use WorkEddy\Modules\WorkerVoice\Application\GetSupervisorFeedbackTrendsUseCase;
use WorkEddy\Modules\WorkerVoice\Application\GetWorkerFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Application\ListWorkerFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Application\SubmitSupervisorFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Application\SubmitWorkerFeedbackUseCase;
use WorkEddy\Modules\WorkerVoice\Settings\WorkerVoiceSettings;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;

final class WorkerVoiceController
{
    public function __construct(
        private readonly SubmitWorkerFeedbackUseCase $submitFeedback,
        private readonly SubmitSupervisorFeedbackUseCase $submitSupervisorFeedback,
        private readonly GetWorkerFeedbackUseCase $getFeedback,
        private readonly ListWorkerFeedbackUseCase $listFeedback,
        private readonly GetWorkerFeedbackTrendsUseCase $getTrends,
        private readonly GetSupervisorFeedbackTrendsUseCase $getSupervisorTrends,
        private readonly WorkerVoiceSettings $settings,
        private readonly ISessionService $session,
    ) {}

    public function questions(Request $request): Response
    {
        unset($request);
        $this->requireContext();

        return Response::json(['status' => 'ok', 'data' => [
            'bodyRegions' => $this->settings->bodyRegions(),
            'questions' => $this->settings->questionCatalog(),
        ]]);
    }

    public function submit(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->submitFeedback->execute($this->requestData($request), $this->requireContext())], 201);
    }

    public function list(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->listFeedback->execute(
            actor: $this->requireContext(),
            filters: [
                'taskUuid' => $request->query['taskUuid'] ?? $request->query['task_uuid'] ?? null,
                'assessmentUuid' => $request->query['assessmentUuid'] ?? $request->query['assessment_uuid'] ?? null,
                'bodyRegion' => $request->query['bodyRegion'] ?? $request->query['body_region'] ?? null,
                'anonymousStatus' => $request->query['anonymousStatus'] ?? $request->query['anonymous_status'] ?? null,
                'dateFrom' => $request->query['dateFrom'] ?? $request->query['date_from'] ?? null,
                'dateTo' => $request->query['dateTo'] ?? $request->query['date_to'] ?? null,
            ],
            limit: max(1, min(200, (int) ($request->query('limit') ?? 50))),
            offset: max(0, (int) ($request->query('offset') ?? 0)),
        )]);
    }

    public function get(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->getFeedback->execute(
            feedbackUuid: (string) ($request->routeParam('feedbackId') ?? ''),
            actor: $this->requireContext(),
        )]);
    }

    public function trends(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->getTrends->execute(
            actor: $this->requireContext(),
            filters: [
                'taskUuid' => $request->query['taskUuid'] ?? $request->query['task_uuid'] ?? null,
                'bodyRegion' => $request->query['bodyRegion'] ?? $request->query['body_region'] ?? null,
                'anonymousStatus' => $request->query['anonymousStatus'] ?? $request->query['anonymous_status'] ?? null,
                'dateFrom' => $request->query['dateFrom'] ?? $request->query['date_from'] ?? null,
                'dateTo' => $request->query['dateTo'] ?? $request->query['date_to'] ?? null,
            ],
        )]);
    }

    public function submitSupervisor(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->submitSupervisorFeedback->execute($this->requestData($request), $this->requireContext())], 201);
    }

    public function supervisorTrends(Request $request): Response
    {
        return Response::json(['status' => 'ok', 'data' => $this->getSupervisorTrends->execute(
            actor: $this->requireContext(),
            filters: [
                'taskUuid' => $request->query['taskUuid'] ?? $request->query['task_uuid'] ?? null,
                'bodyRegion' => $request->query['bodyRegion'] ?? $request->query['body_region'] ?? null,
                'departmentUuid' => $request->query['departmentUuid'] ?? $request->query['department_uuid'] ?? null,
                'dateFrom' => $request->query['dateFrom'] ?? $request->query['date_from'] ?? null,
                'dateTo' => $request->query['dateTo'] ?? $request->query['date_to'] ?? null,
                'observedRiskLevel' => $request->query['observedRiskLevel'] ?? $request->query['observed_risk_level'] ?? null,
            ],
        )]);
    }

    /** @return array<string, mixed> */
    private function requestData(Request $request): array
    {
        return array_replace($request->body, $request->json);
    }

    private function requireContext(): UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }
}
