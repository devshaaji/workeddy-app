<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Ergonomics\Presentation;

use WorkEddy\Modules\Ergonomics\Application\ScoreErgonomicAssessmentUseCase;
use WorkEddy\Modules\Ergonomics\Authorization\ErgonomicsPermissions;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;

final class ErgonomicsController
{
    public function __construct(
        private readonly AssessmentEngine $engine,
        private readonly ScoreErgonomicAssessmentUseCase $score,
        private readonly IPermissionService $permissions,
        private readonly ISessionService $session,
    ) {}

    public function models(Request $request): Response
    {
        unset($request);
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ErgonomicsPermissions::VIEW_MODELS);

        return Response::json(['status' => 'ok', 'data' => $this->engine->modelDescriptors()]);
    }

    public function score(Request $request): Response
    {
        $ctx = $this->requireContext();
        $body = array_replace($request->body, $request->json);

        return Response::json(['status' => 'ok', 'data' => $this->score->execute(
            model: (string) ($body['model'] ?? ''),
            inputType: (string) ($body['inputType'] ?? $body['input_type'] ?? 'manual'),
            metrics: is_array($body['metrics'] ?? null) ? $body['metrics'] : [],
            actor: $ctx,
        )]);
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
