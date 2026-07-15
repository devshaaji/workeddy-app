<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Reporting\Presentation;

use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Reporting\Application\UseCases\CreateNationalStatisticUseCase;
use WorkEddy\Modules\Reporting\Application\UseCases\DeleteNationalStatisticUseCase;
use WorkEddy\Modules\Reporting\Application\UseCases\UpdateNationalStatisticUseCase;
use WorkEddy\Modules\Reporting\Authorization\ReportingPermissions;
use WorkEddy\Modules\Reporting\Domain\Contracts\INationalStatisticRepository;
use WorkEddy\Modules\Reporting\Domain\NationalStatisticCategory;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Exceptions\NotFoundException;

/**
 * Admin CRUD for the National Importance dashboard's static, source-cited
 * national statistics. Gated by ReportingPermissions::NATIONAL_CONTEXT_MANAGE
 * (a system-only permission) \u2014 separate from SYSTEM_VIEW, so more people can
 * view the dashboard than can edit the cited figures on it.
 */
final class NationalStatisticAdminController
{
    public function __construct(
        private readonly INationalStatisticRepository $statistics,
        private readonly CreateNationalStatisticUseCase $createStatistic,
        private readonly UpdateNationalStatisticUseCase $updateStatistic,
        private readonly DeleteNationalStatisticUseCase $deleteStatistic,
        private readonly IPermissionService $permissions,
        private readonly ISessionService $session,
    ) {}

    public function list(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ReportingPermissions::NATIONAL_CONTEXT_MANAGE);

        $category = trim((string) $request->query('category', ''));
        $rows = $category !== ''
            ? $this->statistics->listByCategory($category, publishedOnly: false)
            : $this->statistics->listAll(publishedOnly: false);

        return Response::json([
            'status' => 'ok',
            'data' => array_map(static fn($statistic): array => $statistic->toView(), $rows),
            'categories' => NationalStatisticCategory::labels(),
        ]);
    }

    public function show(Request $request): Response
    {
        $ctx = $this->requireContext();
        $this->permissions->requirePrivilege($ctx, ReportingPermissions::NATIONAL_CONTEXT_MANAGE);

        $statistic = $this->statistics->findByUuid((string) ($request->routeParam('uuid') ?? ''));
        if ($statistic === null) {
            throw new NotFoundException('National statistic not found.');
        }

        return Response::json(['status' => 'ok', 'data' => $statistic->toView()]);
    }

    public function create(Request $request): Response
    {
        $ctx = $this->requireContext();
        $result = $this->createStatistic->execute($this->requestData($request), $ctx);

        return Response::json(['status' => 'ok', 'data' => $result], 201);
    }

    public function update(Request $request): Response
    {
        $ctx = $this->requireContext();
        $uuid = (string) ($request->routeParam('uuid') ?? '');
        $result = $this->updateStatistic->execute($uuid, $this->requestData($request), $ctx);

        return Response::json(['status' => 'ok', 'data' => $result]);
    }

    public function delete(Request $request): Response
    {
        $ctx = $this->requireContext();
        $uuid = (string) ($request->routeParam('uuid') ?? '');
        $this->deleteStatistic->execute($uuid, $ctx);

        return Response::json(['status' => 'ok', 'data' => ['uuid' => $uuid, 'deleted' => true]]);
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
            throw new AuthenticationException('Unauthenticated.');
        }

        return $ctx;
    }
}
