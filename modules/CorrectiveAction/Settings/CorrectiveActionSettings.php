<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Settings;

use WorkEddy\Platform\Settings\ModuleSettings;

final class CorrectiveActionSettings extends ModuleSettings
{
    protected function moduleName(): string
    {
        return 'corrective_action';
    }

    public function defaultDueDays(): int
    {
        return $this->getInt('default_due_days');
    }

    public function followUpDaysAfterVerification(): int
    {
        return $this->getInt('follow_up_days_after_verification');
    }

    public function requireEvidenceForCompletion(): bool
    {
        return $this->getBool('require_evidence_for_completion');
    }
}
