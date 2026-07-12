<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Application\Services;

use WorkEddy\Modules\CorrectiveAction\Domain\CorrectiveAction;
use WorkEddy\Shared\Exceptions\ValidationException;

final class ControlActionWorkflowService
{
    public function transition(CorrectiveAction $action, string $status): CorrectiveAction
    {
        return $action->transition($status);
    }

    public function assertCanVerify(CorrectiveAction $action): void
    {
        if ($action->status !== 'completed') {
            throw new ValidationException(['status' => 'Only completed corrective actions can be verified.']);
        }
    }

    public function followUpDueDate(int $days): string
    {
        return (new \DateTimeImmutable('today'))->modify('+' . max(1, $days) . ' days')->format('Y-m-d');
    }
}
