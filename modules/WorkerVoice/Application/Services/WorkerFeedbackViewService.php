<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Application\Services;

use WorkEddy\Modules\WorkerVoice\Domain\WorkerFeedback;

final class WorkerFeedbackViewService
{
    /** @return array<string, mixed> */
    public function make(WorkerFeedback $feedback, bool $includeSensitiveIdentity): array
    {
        $view = $feedback->toView();

        if ($feedback->isAnonymous() || !$includeSensitiveIdentity) {
            $view['submittedByUserId'] = null;
        }

        return $view;
    }
}
