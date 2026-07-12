<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Audit\Presentation;

use WorkEddy\Modules\Audit\Application\DTOs\AuditLogEntryDTO;
use WorkEddy\Modules\Audit\Application\DTOs\QueryAuditLogRequest;
use WorkEddy\Modules\Audit\Application\QueryAuditLogUseCase;
use WorkEddy\Modules\Audit\Authorization\AuditPermissions;
use WorkEddy\Modules\Audit\Domain\Contracts\IAuditLogRepository;
use WorkEddy\Modules\Audit\Settings\AuditSettings;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\NotFoundException;
use WorkEddy\Shared\Support\CsvSecurity;
use WorkEddy\Shared\Support\UuidSupport;

final class AuditController
{
    public function __construct(
        private readonly QueryAuditLogUseCase $queryAuditLog,
        private readonly IAuditLogRepository $repository,
        private readonly AuditSettings $auditSettings,
        private readonly SettingsService $settings,
        private readonly IPermissionService $permissions,
        private readonly IAuditService $audit,
        private readonly ISessionService $session,
    ) {}

    public function list(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, AuditPermissions::VIEW);

        $query = $this->buildQueryRequest($request);
        if ($this->isOrganizationScoped($ctx)) {
            $visible = $this->filterEntriesForContext(
                $this->queryAuditLog->execute(new QueryAuditLogRequest(
                    actorId: $query->actorId,
                    module: $query->module,
                    action: $query->action,
                    entityType: $query->entityType,
                    entityId: $query->entityId,
                    fromDate: $query->fromDate,
                    toDate: $query->toDate,
                    limit: $this->auditSettings->maxQueryResults(),
                    offset: 0,
                )),
                $ctx,
            );
            $total = count($visible);
            $entries = array_slice($visible, $query->offset, $query->limit);
        } else {
            $entries = $this->queryAuditLog->execute($query);
            $total = $this->queryAuditLog->count($query);
        }

        return Response::json([
            'status' => 'ok',
            'data' => array_map(static fn($entry): array => [
                'id' => $entry->id,
                'actorId' => $entry->actorId,
                'actorName' => $entry->actorName,
                'actorUsername' => $entry->actorUsername,
                'actorLabel' => $entry->actorLabel,
                'action' => $entry->action,
                'entityType' => $entry->entityType,
                'entityId' => $entry->entityId,
                'module' => $entry->module,
                'before' => $entry->before,
                'after' => $entry->after,
                'ipAddress' => $entry->ipAddress,
                'createdAt' => $entry->createdAt,
            ], $entries),
            'meta' => [
                'total' => $total,
                'limit' => $query->limit,
                'offset' => $query->offset,
            ],
        ]);
    }

    public function show(Request $request): Response
    {

        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, AuditPermissions::VIEW);

        $uuid = $this->requireAuditLogUuid($request);
        $entry = $this->repository->findByUuid($uuid);
        if ($entry === null || !$this->entryVisibleToContext($entry, $ctx)) {
            throw new NotFoundException('Audit log entry not found ' . $uuid);
        }
        return Response::json(['status' => 'ok', 'data' => $this->serialize($entry)]);
    }

    public function export(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, AuditPermissions::EXPORT);

        $query = $this->buildQueryRequest(
            $request,
            max(1, min(10000, (int) ($request->query('limit') ?? 1000))),
            0,
        );
        $entries = $this->queryAuditLog->execute($query);
        if ($this->isOrganizationScoped($ctx)) {
            $entries = $this->filterEntriesForContext($entries, $ctx);
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['id', 'actorId', 'actorName', 'actorUsername', 'actorLabel', 'action', 'entityType', 'entityId', 'module', 'before', 'after', 'ipAddress', 'createdAt']);
        foreach ($entries as $entry) {
            fputcsv($handle, array_map([CsvSecurity::class, 'value'], [
                $entry->id,
                $entry->actorId,
                $entry->actorName,
                $entry->actorUsername,
                $entry->actorLabel,
                $entry->action,
                $entry->entityType,
                $entry->entityId,
                $entry->module,
                json_encode($entry->before, JSON_UNESCAPED_UNICODE),
                json_encode($entry->after, JSON_UNESCAPED_UNICODE),
                $entry->ipAddress,
                $entry->createdAt,
            ]));
        }
        rewind($handle);
        $csv = stream_get_contents($handle) ?: '';
        fclose($handle);
        $this->audit->record(
            action: 'audit.exported',
            entityType: 'AuditLog',
            entityId: $ctx->organizationUuid ?? 'platform',
            afterState: [
                'organizationUuid' => $ctx->organizationUuid,
                'rowCount' => count($entries),
                'filters' => [
                    'actorId' => $query->actorId,
                    'module' => $query->module,
                    'action' => $query->action,
                    'entityType' => $query->entityType,
                    'entityId' => $query->entityId,
                    'fromDate' => $query->fromDate,
                    'toDate' => $query->toDate,
                ],
            ],
            actorId: (string) $ctx->userId,
            actorType: 'user',
        );

        return Response::stream(static function () use ($csv): void {
            echo $csv;
        }, 200, [
            'Content-Type' => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="audit-log-export.csv"',
        ]);
    }

    /** GET /api/audit/settings */
    public function settings(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, AuditPermissions::SETTINGS_MANAGE);

        return Response::json([
            'status' => 'ok',
            'data' => [
                'values' => $this->settings->getAllForModule('audit'),
                'definitions' => array_map(static fn($definition): array => [
                    'key' => $definition->key,
                    'type' => $definition->type->value,
                    'default' => $definition->default,
                    'label' => $definition->label,
                    'description' => $definition->description,
                    'editable' => $definition->editable,
                    'sensitive' => $definition->sensitive,
                    'restartRequired' => $definition->restartRequired,
                ], $this->settings->getRegistry()?->getForModule('audit') ?? []),
                'derived' => [
                    'retentionDays' => $this->auditSettings->retentionDays(),
                    'maxQueryResults' => $this->auditSettings->maxQueryResults(),
                ],
            ],
        ]);
    }

    /** PUT /api/audit/settings */
    public function updateSettings(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, AuditPermissions::SETTINGS_MANAGE);

        $payload = array_replace($request->body, $request->json);
        $values = $payload['values'] ?? $payload;
        $allowed = array_flip([
            'retention_days',
            'max_query_results',
            'mask_sensitive_fields',
            'record_ip_address',
            'store_state_diffs',
        ]);
        $this->settings->setMany('audit', is_array($values) ? array_intersect_key($values, $allowed) : [], $ctx->userId);

        return $this->settings($request);
    }

    private function requireContext(): \WorkEddy\Platform\Session\UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }

    private function serialize($entry): array
    {
        return [
            'id' => $entry->id,
            'actorId' => $entry->actorId,
            'actorName' => $entry->actorName,
            'actorUsername' => $entry->actorUsername,
            'actorLabel' => $entry->actorLabel,
            'action' => $entry->action,
            'entityType' => $entry->entityType,
            'entityId' => $entry->entityId,
            'module' => $entry->module,
            'before' => $entry->before,
            'after' => $entry->after,
            'ipAddress' => $entry->ipAddress,
            'createdAt' => $entry->createdAt,
        ];
    }

    private function buildQueryRequest(
        Request $request,
        ?int $limit = null,
        ?int $offset = null,
    ): QueryAuditLogRequest {
        $resolvedLimit = $limit ?? max(1, min(10000, (int) ($request->query('limit') ?? 100)));
        $resolvedOffset = $offset ?? max(0, (int) ($request->query('offset') ?? 0));
        $actorId = $this->nullableIntQuery($request, 'actorId');
        return new QueryAuditLogRequest(
            actorId: $actorId,
            module: $this->nullableStringQuery($request, 'module'),
            action: $this->nullableStringQuery($request, 'action'),
            entityType: $this->nullableStringQuery($request, 'entityType'),
            entityId: $this->nullableStringQuery($request, 'entityId'),
            fromDate: $this->nullableStringQuery($request, 'fromDate'),
            toDate: $this->nullableStringQuery($request, 'toDate'),
            limit: $resolvedLimit,
            offset: $resolvedOffset,
        );
    }

    private function nullableIntQuery(Request $request, string $key): ?int
    {
        $value = $request->query($key);

        return $value !== null && trim((string) $value) !== ''
            ? (int) $value
            : null;
    }

    private function nullableStringQuery(Request $request, string $key): ?string
    {
        $value = $request->query($key);

        return $value !== null && trim((string) $value) !== ''
            ? (string) $value
            : null;
    }

    /**
     * @param array<string, mixed> $vars
     */
    private function requireAuditLogUuid(Request $request): string
    {
        $vars = $request->routeParams;
        return UuidSupport::requireValid((string) ($vars['id'] ?? ''));
    }

    private function isOrganizationScoped(\WorkEddy\Platform\Session\UserContext $ctx): bool
    {
        return $ctx->organizationUuid !== null && !in_array($ctx->roleType, ['super_admin', 'admin'], true);
    }

    /**
     * @param list<AuditLogEntryDTO> $entries
     * @return list<AuditLogEntryDTO>
     */
    private function filterEntriesForContext(array $entries, \WorkEddy\Platform\Session\UserContext $ctx): array
    {
        return array_values(array_filter($entries, fn(AuditLogEntryDTO $entry): bool => $this->entryVisibleToContext($entry, $ctx)));
    }

    private function entryVisibleToContext(AuditLogEntryDTO $entry, \WorkEddy\Platform\Session\UserContext $ctx): bool
    {
        if (!$this->isOrganizationScoped($ctx)) {
            return true;
        }

        $organizationUuid = (string) $ctx->organizationUuid;
        $organizationId = $ctx->organizationId;

        if ($entry->entityId === $organizationUuid) {
            return true;
        }

        return $this->payloadContainsOrganization($entry->before, $organizationUuid, $organizationId)
            || $this->payloadContainsOrganization($entry->after, $organizationUuid, $organizationId);
    }

    /**
     * @param array<string, mixed>|null $payload
     */
    private function payloadContainsOrganization(?array $payload, string $organizationUuid, ?int $organizationId): bool
    {
        if ($payload === null) {
            return false;
        }

        foreach ($payload as $key => $value) {
            if (is_array($value) && $this->payloadContainsOrganization($value, $organizationUuid, $organizationId)) {
                return true;
            }

            $normalizedKey = strtolower((string) $key);
            if (in_array($normalizedKey, ['organizationuuid', 'organization_uuid'], true) && (string) $value === $organizationUuid) {
                return true;
            }

            if ($organizationId !== null && in_array($normalizedKey, ['organizationid', 'organization_id'], true) && (string) $value === (string) $organizationId) {
                return true;
            }
        }

        return false;
    }
}
