<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Presentation;

use WorkEddy\Modules\Reporting\Application\Services\ReportingSnapshotService;
use WorkEddy\Platform\Session\UserContext;

final class ReportingPageData
{
    public function __construct(
        private readonly ReportingSnapshotService $snapshots,
    ) {}

    public function dashboard(UserContext $ctx): array
    {
        return array_replace(
            $this->snapshots->dashboard(),
            ['user' => (string) $ctx->userId],
        );
    }

    public function finance(UserContext $ctx): array
    {
        return array_replace($this->snapshots->finance(), ['user' => (string) $ctx->userId]);
    }

    public function operations(UserContext $ctx): array
    {
        return array_replace($this->snapshots->operations(), ['user' => (string) $ctx->userId]);
    }

    public function pilotSummary(UserContext $ctx, array $filters = []): array
    {
        return array_replace($this->snapshots->pilotSummary($ctx->organizationUuid, $filters), ['user' => (string) $ctx->userId]);
    }

    public function impactTracker(UserContext $ctx, array $filters = []): array
    {
        return array_replace($this->snapshots->impactTracker($ctx->organizationUuid, $filters), ['user' => (string) $ctx->userId]);
    }

    public function dashboardOverview(UserContext $ctx, array $filters = []): array
    {
        return array_replace($this->snapshots->dashboardOverview($ctx->organizationUuid, $filters), ['user' => (string) $ctx->userId]);
    }

    public function assessment(string $uuid, UserContext $ctx): array
    {
        return array_replace($this->snapshots->assessmentReport($uuid), ['user' => (string) $ctx->userId]);
    }

    public function correctiveAction(string $uuid, UserContext $ctx): array
    {
        return array_replace($this->snapshots->correctiveActionReport($uuid), ['user' => (string) $ctx->userId]);
    }

    public function comparison(string $uuid, UserContext $ctx): array
    {
        return array_replace($this->snapshots->comparisonReport($uuid), ['user' => (string) $ctx->userId]);
    }

    public function auditTrail(string $uuid, UserContext $ctx): array
    {
        return array_replace($this->snapshots->auditTrailReport($uuid), ['user' => (string) $ctx->userId]);
    }
}
