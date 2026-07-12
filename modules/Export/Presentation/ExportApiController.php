<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Presentation;

use WorkEddy\Modules\Export\Application\IssueSignedResearchExportAccessUseCase;
use WorkEddy\Modules\Export\Application\ReadSignedResearchExportAccessUseCase;
use WorkEddy\Modules\Export\Application\UseCases\GenerateResearchExportUseCase;
use WorkEddy\Modules\Export\Application\UseCases\PreviewResearchExportUseCase;
use WorkEddy\Modules\Export\Domain\Contracts\IResearchExportRepository;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Shared\Exceptions\AuthenticationException;

final class ExportApiController
{
    public function __construct(
        private readonly PreviewResearchExportUseCase $preview,
        private readonly GenerateResearchExportUseCase $generate,
        private readonly IResearchExportRepository $exports,
        private readonly IssueSignedResearchExportAccessUseCase $issueSignedAccess,
        private readonly ReadSignedResearchExportAccessUseCase $readSignedAccess,
        private readonly ISessionService $session,
    ) {}

    public function preview(Request $request): Response
    {
        $ctx = $this->requireContext();
        $payload = array_replace($request->query, $request->body, $request->json);

        return Response::success($this->preview->execute(
            (string) ($payload['dataset'] ?? 'assessments'),
            (string) ($payload['format'] ?? 'csv'),
            $ctx,
            is_array($payload['filters'] ?? null) ? $payload['filters'] : $payload,
        )->toArray());
    }

    public function generate(Request $request): Response
    {
        $ctx = $this->requireContext();
        $payload = array_replace($request->query, $request->body, $request->json);
        $export = $this->generate->execute(
            (string) ($payload['dataset'] ?? 'assessments'),
            (string) ($payload['format'] ?? 'csv'),
            $ctx,
            is_array($payload['filters'] ?? null) ? $payload['filters'] : $payload,
        );
        $signed = $this->issueSignedAccess->execute($export->uuid, $ctx);

        return Response::success([
            'export' => $export->toView(),
            'signedAccess' => $signed,
        ], status: 201);
    }

    public function list(Request $request): Response
    {
        $ctx = $this->requireContext();

        return Response::success([
            'items' => array_map(
                static fn($export): array => $export->toView(),
                $this->exports->listByOrganizationUuid((string) $ctx->organizationUuid, (int) ($request->query('limit') ?? 20)),
            ),
        ]);
    }

    public function issueSignedAccess(Request $request): Response
    {
        $ctx = $this->requireContext();
        $exportUuid = (string) ($request->routeParam('exportUuid') ?? '');

        return Response::success($this->issueSignedAccess->execute($exportUuid, $ctx, (string) ($request->query('purpose') ?? 'download')));
    }

    public function readSignedAccess(Request $request): Response
    {
        $stream = $this->readSignedAccess->execute((string) ($request->routeParam('token') ?? ''));

        return Response::stream(static function () use ($stream): void {
            echo $stream['body'];
        }, headers: [
            'Content-Type' => $stream['mimeType'],
            'Content-Disposition' => $stream['disposition'] . '; filename="' . str_replace(['"', "\r", "\n"], '', $stream['filename']) . '"',
        ]);
    }

    private function requireContext(): \WorkEddy\Platform\Session\UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Authentication required.');
        }

        return $ctx;
    }
}
