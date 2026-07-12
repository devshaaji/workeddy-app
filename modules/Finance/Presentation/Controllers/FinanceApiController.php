<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Finance\Presentation\Controllers;

use WorkEddy\Modules\Finance\Application\UseCases\RecordExpense;
use WorkEddy\Modules\Finance\Application\UseCases\RecordIncome;
use WorkEddy\Modules\Finance\Application\UseCases\RefreshPayrollSummary;
use WorkEddy\Modules\Finance\Domain\Contracts\IFinanceRepository;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\SettingsService;

final class FinanceApiController
{
    public function __construct(
        private readonly IFinanceRepository $repository,
        private readonly RecordIncome $recordIncome,
        private readonly RecordExpense $recordExpense,
        private readonly RefreshPayrollSummary $refreshPayrollSummary,
        private readonly SettingsService $settingsService,
        private readonly ISessionService $session,
    ) {}

    public function dashboard(Request $request): Response
    {
        return Response::success([
            'summary' => $this->repository->summary(),
            'income' => array_map(static fn($item): array => $item->toArray(), $this->repository->listIncome()),
            'expenses' => array_map(static fn($item): array => $item->toArray(), $this->repository->listExpenses()),
            'payroll_summaries' => array_map(static fn($item): array => $item->toArray(), $this->repository->listPayrollSummaries()),
        ]);
    }

    public function summary(Request $request): Response
    {
        return Response::success($this->repository->summary());
    }

    public function listIncome(Request $request): Response
    {
        return Response::success([
            'income' => array_map(static fn($item): array => $item->toArray(), $this->repository->listIncome($request->query)),
        ]);
    }

    public function listExpenses(Request $request): Response
    {
        return Response::success([
            'expenses' => array_map(static fn($item): array => $item->toArray(), $this->repository->listExpenses($request->query)),
        ]);
    }

    public function listPayrollSummaries(Request $request): Response
    {
        return Response::success([
            'payroll_summaries' => array_map(static fn($item): array => $item->toArray(), $this->repository->listPayrollSummaries($request->query)),
        ]);
    }

    public function createIncome(Request $request): Response
    {
        $income = $this->recordIncome->execute(array_replace($request->body, $request->json));

        return Response::success(['income' => $income->toArray()], 'Income recorded.', 201);
    }

    public function viewIncome(Request $request): Response
    {
        $income = $this->repository->findIncomeByUuid((string) $request->routeParam('uuid'));
        if ($income === null) {
            return Response::error('Income record not found.', 404);
        }

        return Response::success(['income' => $income->toArray()]);
    }

    public function updateIncome(Request $request): Response
    {
        $income = $this->repository->findIncomeByUuid((string) $request->routeParam('uuid'));
        if ($income === null) {
            return Response::error('Income record not found.', 404);
        }

        $this->repository->updateIncome($income->id, $this->only(array_replace($request->body, $request->json), [
            'source_type',
            'reference_number',
            'category',
            'amount',
            'currency',
            'description',
        ]));

        $updated = $this->repository->findIncomeByUuid($income->uuid);

        return Response::success(['income' => $updated?->toArray()], 'Income record updated.');
    }

    public function archiveIncome(Request $request): Response
    {
        $income = $this->repository->findIncomeByUuid((string) $request->routeParam('uuid'));
        if ($income === null) {
            return Response::error('Income record not found.', 404);
        }

        $this->repository->archiveIncome($income->id);

        return Response::success(['uuid' => $income->uuid], 'Income record archived.');
    }

    public function bulkArchiveIncome(Request $request): Response
    {
        $payload = array_replace($request->body, $request->json);
        $count = 0;

        foreach ($this->uuidList($payload['uuids'] ?? []) as $uuid) {
            $income = $this->repository->findIncomeByUuid($uuid);
            if ($income === null) {
                continue;
            }
            $this->repository->archiveIncome($income->id);
            $count++;
        }

        return Response::success(['archived' => $count], $count . ' income record' . ($count === 1 ? '' : 's') . ' archived.');
    }

    public function createExpense(Request $request): Response
    {
        $expense = $this->recordExpense->execute(array_replace($request->body, $request->json));

        return Response::success(['expense' => $expense->toArray()], 'Expense recorded.', 201);
    }

    public function viewExpense(Request $request): Response
    {
        $expense = $this->repository->findExpenseByUuid((string) $request->routeParam('uuid'));
        if ($expense === null) {
            return Response::error('Expense record not found.', 404);
        }

        return Response::success(['expense' => $expense->toArray()]);
    }

    public function updateExpense(Request $request): Response
    {
        $expense = $this->repository->findExpenseByUuid((string) $request->routeParam('uuid'));
        if ($expense === null) {
            return Response::error('Expense record not found.', 404);
        }

        $this->repository->updateExpense($expense->id, $this->only(array_replace($request->body, $request->json), [
            'reference_number',
            'category',
            'amount',
            'currency',
            'description',
        ]));

        $updated = $this->repository->findExpenseByUuid($expense->uuid);

        return Response::success(['expense' => $updated?->toArray()], 'Expense record updated.');
    }

    public function archiveExpense(Request $request): Response
    {
        $expense = $this->repository->findExpenseByUuid((string) $request->routeParam('uuid'));
        if ($expense === null) {
            return Response::error('Expense record not found.', 404);
        }

        $this->repository->archiveExpense($expense->id);

        return Response::success(['uuid' => $expense->uuid], 'Expense record archived.');
    }

    public function bulkArchiveExpenses(Request $request): Response
    {
        $payload = array_replace($request->body, $request->json);
        $count = 0;

        foreach ($this->uuidList($payload['uuids'] ?? []) as $uuid) {
            $expense = $this->repository->findExpenseByUuid($uuid);
            if ($expense === null) {
                continue;
            }
            $this->repository->archiveExpense($expense->id);
            $count++;
        }

        return Response::success(['archived' => $count], $count . ' expense record' . ($count === 1 ? '' : 's') . ' archived.');
    }

    public function refreshPayrollSummary(Request $request): Response
    {
        $summary = $this->refreshPayrollSummary->execute((string) $request->input('period_key'));

        return Response::success(['payroll_summary' => $summary->toArray()], 'Payroll summary refreshed.');
    }

    public function getSettings(Request $request): Response
    {
        $registry = $this->settingsService->getRegistry();

        return Response::json([
            'status' => 'ok',
            'data' => [
                'values' => $this->settingsService->getAllForModule('finance'),
                'definitions' => $registry === null ? [] : array_map(static fn($definition): array => [
                    'key' => $definition->key,
                    'type' => $definition->type->value,
                    'default' => $definition->default,
                    'label' => $definition->label,
                    'description' => $definition->description,
                    'editable' => $definition->editable,
                    'sensitive' => $definition->sensitive,
                    'restartRequired' => $definition->restartRequired,
                ], $registry->getForModule('finance')),
            ],
        ]);
    }

    public function updateSettings(Request $request): Response
    {
        $payload = array_replace($request->body, $request->json);
        $values = $payload['values'] ?? $payload;
        $allowed = array_flip([
            'default_expense_currency',
            'payroll_summary_enabled',
        ]);

        $ctx = $this->session->getUserContext();
        $actorId = $ctx ? (string) $ctx->userId : 'system';
        $this->settingsService->setMany('finance', is_array($values) ? array_intersect_key($values, $allowed) : [], $actorId);

        return $this->getSettings($request);
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $allowed
     * @return array<string, mixed>
     */
    private function only(array $data, array $allowed): array
    {
        return array_intersect_key($data, array_flip($allowed));
    }

    /**
     * @param mixed $value
     * @return list<string>
     */
    private function uuidList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn(mixed $uuid): string => trim((string) $uuid),
            $value,
        )));
    }
}
