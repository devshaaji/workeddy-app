<?php

declare(strict_types=1);

use WorkEddy\Modules\Reporting\Authorization\ReportingPermissions;
use WorkEddy\Modules\Reporting\Presentation\ReportingApiController;
use WorkEddy\Modules\Reporting\Presentation\ReportingPageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $routes->module('Reporting', static function (RouteRegistrar $module): void {
        $module->group('/reporting', static function (RouteRegistrar $web): void {
            $uuid = '[0-9a-fA-F-]{36}';
            $web->add('GET', '/dashboard', [ReportingPageController::class, 'dashboard'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $web->add('GET', '/finance', [ReportingPageController::class, 'finance'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $web->add('GET', '/operations', [ReportingPageController::class, 'operations'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $web->add('GET', '/pilot-summary', [ReportingPageController::class, 'pilotSummary'], ['permission:' . ReportingPermissions::VIEW]);
            $web->add('GET', '/settings', [ReportingPageController::class, 'settings'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $web->add('GET', '/assessment/{uuid:' . $uuid . '}', [ReportingPageController::class, 'assessment'], ['permission:' . ReportingPermissions::VIEW]);
            $web->add('GET', '/corrective-action/{uuid:' . $uuid . '}', [ReportingPageController::class, 'correctiveAction'], ['permission:' . ReportingPermissions::VIEW]);
            $web->add('GET', '/comparison/{uuid:' . $uuid . '}', [ReportingPageController::class, 'comparison'], ['permission:' . ReportingPermissions::VIEW]);
            $web->add('GET', '/audit-trail/{uuid:' . $uuid . '}', [ReportingPageController::class, 'auditTrail'], ['permission:' . ReportingPermissions::VIEW]);
        }, ['auth']);

        $module->group('/api/v1/reporting', static function (RouteRegistrar $api): void {
            $uuid = '[0-9a-fA-F-]{36}';

            $api->add('GET', '/dashboard', [ReportingApiController::class, 'dashboard'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $api->add('GET', '/dashboard/pdf', [ReportingApiController::class, 'downloadDashboardPdf'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $api->add('GET', '/dashboard/csv', [ReportingApiController::class, 'downloadDashboardCsv'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $api->add('GET', '/summary', [ReportingApiController::class, 'summary'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/finance', [ReportingApiController::class, 'finance'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $api->add('GET', '/finance/pdf', [ReportingApiController::class, 'downloadFinancePdf'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $api->add('GET', '/finance/csv', [ReportingApiController::class, 'downloadFinanceCsv'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $api->add('GET', '/operations', [ReportingApiController::class, 'operations'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $api->add('GET', '/operations/pdf', [ReportingApiController::class, 'downloadOperationsPdf'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $api->add('GET', '/operations/csv', [ReportingApiController::class, 'downloadOperationsCsv'], ['permission:' . ReportingPermissions::SYSTEM_VIEW]);
            $api->add('GET', '/pilot-summary', [ReportingApiController::class, 'pilotSummary'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/pilot-summary/pdf', [ReportingApiController::class, 'downloadPilotSummaryPdf'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/pilot-summary/csv', [ReportingApiController::class, 'downloadPilotSummaryCsv'], ['permission:' . ReportingPermissions::VIEW]);

            // Assessment Reports
            $api->add('GET', '/assessment/{uuid:' . $uuid . '}', [ReportingApiController::class, 'assessment'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/assessment/{uuid:' . $uuid . '}/pdf', [ReportingApiController::class, 'downloadAssessmentPdf'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/assessment/{uuid:' . $uuid . '}/csv', [ReportingApiController::class, 'downloadAssessmentCsv'], ['permission:' . ReportingPermissions::VIEW]);

            // Corrective Action Reports
            $api->add('GET', '/corrective-action/{uuid:' . $uuid . '}', [ReportingApiController::class, 'correctiveAction'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/corrective-action/{uuid:' . $uuid . '}/pdf', [ReportingApiController::class, 'downloadCorrectiveActionPdf'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/corrective-action/{uuid:' . $uuid . '}/csv', [ReportingApiController::class, 'downloadCorrectiveActionCsv'], ['permission:' . ReportingPermissions::VIEW]);

            // Comparison Reports
            $api->add('GET', '/comparison/{uuid:' . $uuid . '}', [ReportingApiController::class, 'comparison'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/comparison/{uuid:' . $uuid . '}/pdf', [ReportingApiController::class, 'downloadComparisonPdf'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/comparison/{uuid:' . $uuid . '}/csv', [ReportingApiController::class, 'downloadComparisonCsv'], ['permission:' . ReportingPermissions::VIEW]);

            // Audit Trail Reports
            $api->add('GET', '/audit-trail/{uuid:' . $uuid . '}', [ReportingApiController::class, 'auditTrail'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/audit-trail/{uuid:' . $uuid . '}/pdf', [ReportingApiController::class, 'downloadAuditTrailPdf'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/audit-trail/{uuid:' . $uuid . '}/csv', [ReportingApiController::class, 'downloadAuditTrailCsv'], ['permission:' . ReportingPermissions::VIEW]);

            $api->add('GET', '/artifacts', [ReportingApiController::class, 'listArtifacts'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('GET', '/artifacts/{artifactUuid:' . $uuid . '}/versions', [ReportingApiController::class, 'versionChain'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('POST', '/artifacts/{artifactUuid:' . $uuid . '}/regenerate', [ReportingApiController::class, 'regenerateArtifact'], ['permission:' . ReportingPermissions::VIEW]);
            $api->add('POST', '/artifacts/{artifactUuid:' . $uuid . '}/signed-access', [ReportingApiController::class, 'issueSignedAccess'], ['permission:' . ReportingPermissions::VIEW]);
        }, ['auth']);

        $module->group('/api/v1/reporting', static function (RouteRegistrar $public): void {
            $token = '[A-Za-z0-9\\-_\\.]+';
            $public->add('GET', '/signed-access/{token:' . $token . '}', [ReportingApiController::class, 'readSignedAccess']);
        });
    });
};
