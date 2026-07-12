<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Privacy\Presentation;

use WorkEddy\Modules\Privacy\Application\EnforceVideoRetentionUseCase;
use WorkEddy\Modules\Privacy\Application\IssueSignedVideoAccessUseCase;
use WorkEddy\Modules\Privacy\Application\LogVideoAccessUseCase;
use WorkEddy\Modules\Privacy\Application\ReadSignedVideoAccessUseCase;
use WorkEddy\Modules\Privacy\Application\RecordVideoConsentUseCase;
use WorkEddy\Modules\Privacy\Application\UpdateRetentionPolicyUseCase;
use WorkEddy\Modules\Privacy\Authorization\PrivacyPermissions;
use WorkEddy\Modules\Privacy\Domain\Contracts\IPrivacyRepository;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\ForbiddenException;

final class PrivacyController
{
    public function __construct(
        private readonly RecordVideoConsentUseCase $recordConsent,
        private readonly LogVideoAccessUseCase $logVideoAccess,
        private readonly UpdateRetentionPolicyUseCase $updateRetentionPolicy,
        private readonly EnforceVideoRetentionUseCase $enforceVideoRetention,
        private readonly IssueSignedVideoAccessUseCase $issueSignedVideoAccess,
        private readonly ReadSignedVideoAccessUseCase $readSignedVideoAccess,
        private readonly IPrivacyRepository $privacy,
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
    ) {}

    public function recordConsent(Request $request): Response
    {
        $body = $this->requestData($request);
        $ctx = $this->requirePrivilege(PrivacyPermissions::CONSENT_RECORD);

        return Response::json(['status' => 'ok', 'data' => $this->recordConsent->execute(
            organizationUuid: (string) ($body['organizationUuid'] ?? $body['organization_uuid'] ?? ''),
            assessmentUuid: (string) ($body['assessmentUuid'] ?? $body['assessment_uuid'] ?? ''),
            storageFileUuid: (string) ($body['storageFileUuid'] ?? $body['storage_file_uuid'] ?? ''),
            actor: $ctx,
            textVersion: (string) ($body['textVersion'] ?? $body['text_version'] ?? ''),
            acceptedNotice: (bool) ($body['acceptedNotice'] ?? $body['accepted_notice'] ?? false),
            ipAddress: $request->getClientIp(),
            userAgent: $request->header('user-agent'),
        )], 201);
    }

    public function logVideoAccess(Request $request): Response
    {
        $body = $this->requestData($request);
        $ctx = $this->requirePrivilege(PrivacyPermissions::AUDIT_VIEW);

        return Response::json(['status' => 'ok', 'data' => $this->logVideoAccess->execute(
            organizationUuid: (string) ($body['organizationUuid'] ?? $body['organization_uuid'] ?? ''),
            assessmentUuid: (string) ($body['assessmentUuid'] ?? $body['assessment_uuid'] ?? ''),
            storageFileUuid: (string) ($body['storageFileUuid'] ?? $body['storage_file_uuid'] ?? ''),
            actor: $ctx,
            purpose: (string) ($body['purpose'] ?? ''),
            ipAddress: $request->getClientIp(),
            userAgent: $request->header('user-agent'),
        )], 201);
    }

    public function updateRetentionPolicy(Request $request): Response
    {
        $body = $this->requestData($request);
        $ctx = $this->requirePrivilege(PrivacyPermissions::RETENTION_MANAGE);
        $this->assertOrganizationAccess($ctx, (string) ($request->routeParam('id') ?? ''));

        return Response::json(['status' => 'ok', 'data' => $this->updateRetentionPolicy->execute(
            organizationId: (int) ($ctx->organizationId ?? 0),
            organizationUuid: (string) ($request->routeParam('id') ?? ''),
            actor: $ctx,
            rawVideoPolicy: (string) ($body['rawVideoPolicy'] ?? $body['raw_video_policy'] ?? ''),
            retainScreenshotsOnly: (bool) ($body['retainScreenshotsOnly'] ?? $body['retain_screenshots_only'] ?? false),
            retainForPilotEvidence: (bool) ($body['retainForPilotEvidence'] ?? $body['retain_for_pilot_evidence'] ?? false),
            retentionDays: (int) ($body['retentionDays'] ?? $body['retention_days'] ?? 0),
        )]);
    }

    public function getRetentionPolicy(Request $request): Response
    {
        $ctx = $this->requirePrivilege(PrivacyPermissions::RETENTION_MANAGE);
        $this->assertOrganizationAccess($ctx, (string) ($request->routeParam('id') ?? ''));
        $policy = $ctx->organizationId === null ? null : $this->privacy->findRetentionPolicyByOrganizationId($ctx->organizationId);

        return Response::json(['status' => 'ok', 'data' => $policy?->toView()]);
    }

    public function enforceVideoRetention(Request $request): Response
    {
        $ctx = $this->requirePrivilege(PrivacyPermissions::RETENTION_ENFORCE);

        return Response::json(['status' => 'ok', 'data' => $this->enforceVideoRetention->execute(
            assessmentUuid: (string) ($request->routeParam('assessmentId') ?? ''),
            actor: $ctx,
        )]);
    }

    public function issueSignedVideoAccess(Request $request): Response
    {
        $body = $this->requestData($request);
        $ctx = $this->requirePrivilege(PrivacyPermissions::VIDEO_ACCESS);

        return Response::json(['status' => 'ok', 'data' => $this->issueSignedVideoAccess->execute(
            organizationUuid: (string) ($body['organizationUuid'] ?? $body['organization_uuid'] ?? ''),
            assessmentUuid: (string) ($body['assessmentUuid'] ?? $body['assessment_uuid'] ?? ''),
            storageFileUuid: (string) ($body['storageFileUuid'] ?? $body['storage_file_uuid'] ?? ''),
            actor: $ctx,
            purpose: (string) ($body['purpose'] ?? 'review'),
            ttlSeconds: (int) ($body['ttlSeconds'] ?? $body['ttl_seconds'] ?? 300),
        )]);
    }

    public function readSignedVideoAccess(Request $request): Response
    {
        $stream = $this->readSignedVideoAccess->execute((string) ($request->routeParam('token') ?? ''));

        return Response::stream(static function () use ($stream): void {
            echo $stream['body'];
        }, headers: [
            'Content-Type' => $stream['mimeType'],
            'Content-Disposition' => 'inline; filename="' . str_replace(['"', "\r", "\n"], '', $stream['filename']) . '"',
        ]);
    }

    public function listVideoConsents(Request $request): Response
    {
        $ctx = $this->requirePrivilege(PrivacyPermissions::AUDIT_VIEW);

        return Response::json(['status' => 'ok', 'data' => $this->privacy->listVideoConsents(
            $this->organizationScope($ctx, $request),
            (int) ($request->query['limit'] ?? 100),
            (int) ($request->query['offset'] ?? 0),
        )]);
    }

    public function listVideoAccessLogs(Request $request): Response
    {
        $ctx = $this->requirePrivilege(PrivacyPermissions::AUDIT_VIEW);

        return Response::json(['status' => 'ok', 'data' => $this->privacy->listVideoAccessLogs(
            $this->organizationScope($ctx, $request),
            (int) ($request->query['limit'] ?? 100),
            (int) ($request->query['offset'] ?? 0),
        )]);
    }

    public function listVideoAssetActivity(Request $request): Response
    {
        $ctx = $this->requirePrivilege(PrivacyPermissions::AUDIT_VIEW);
        $organizationUuid = (string) ($request->routeParam('id') ?? '');
        $this->assertOrganizationAccess($ctx, $organizationUuid);

        return Response::json(['status' => 'ok', 'data' => $this->privacy->listVideoAssetActivity(
            $organizationUuid,
            (string) ($request->routeParam('assessmentId') ?? ''),
            (string) ($request->routeParam('storageFileUuid') ?? ''),
            max(1, min(20, (int) ($request->query['limit'] ?? 10))),
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

    private function requirePrivilege(string $privilege): UserContext
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, $privilege);

        return $ctx;
    }

    private function assertOrganizationAccess(UserContext $ctx, string $organizationUuid): void
    {
        if ($ctx->organizationUuid !== null && $organizationUuid !== '' && $ctx->organizationUuid !== $organizationUuid) {
            throw new ForbiddenException('Cannot access another organization.');
        }
    }

    private function organizationScope(UserContext $ctx, Request $request): ?string
    {
        $requested = (string) ($request->query['organizationUuid'] ?? $request->query['organization_uuid'] ?? '');
        if ($ctx->organizationUuid !== null) {
            if ($requested !== '' && $requested !== $ctx->organizationUuid) {
                throw new ForbiddenException('Cannot access another organization.');
            }

            return $ctx->organizationUuid;
        }

        return $requested === '' ? null : $requested;
    }
}
