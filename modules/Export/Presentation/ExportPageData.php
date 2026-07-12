<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Export\Presentation;

use WorkEddy\Modules\Export\Domain\Contracts\IResearchExportRepository;
use WorkEddy\Modules\Export\Settings\ExportSettings;
use WorkEddy\Platform\Session\UserContext;

final class ExportPageData
{
    public function __construct(
        private readonly ExportSettings $settings,
        private readonly IResearchExportRepository $exports,
    ) {}

    public function index(UserContext $ctx): array
    {
        $recentExports = array_map(
            static fn($export): array => $export->toView(),
            $this->exports->listByOrganizationUuid((string) $ctx->organizationUuid, 10),
        );
        $readyCount = 0;
        $totalRows = 0;
        $latestGeneratedAt = null;

        foreach ($recentExports as $item) {
            if (($item['status'] ?? null) === 'ready') {
                $readyCount++;
            }

            $totalRows += (int) ($item['rowCount'] ?? 0);
            $generatedAt = isset($item['generatedAt']) ? (string) $item['generatedAt'] : '';
            if ($generatedAt !== '' && ($latestGeneratedAt === null || strcmp($generatedAt, $latestGeneratedAt) > 0)) {
                $latestGeneratedAt = $generatedAt;
            }
        }

        return [
            'user' => (string) $ctx->userId,
            'organizationUuid' => $ctx->organizationUuid,
            'allowedFormats' => $this->settings->allowedFormats(),
            'defaultFormat' => $this->settings->defaultFormat(),
            'datasets' => ['assessments', 'worker_feedback'],
            'recentExports' => $recentExports,
            'summary' => [
                'recentExportCount' => count($recentExports),
                'readyExportCount' => $readyCount,
                'totalRows' => $totalRows,
                'signedLinkTtlMinutes' => $this->settings->signedLinkTtlMinutes(),
                'latestGeneratedAt' => $latestGeneratedAt,
                'maxExportRows' => $this->settings->maxExportRows(),
            ],
            'pageScripts' => ['js/modules/export.js'],
        ];
    }
}
