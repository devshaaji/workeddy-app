<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Authorization;

final class CorrectiveActionPermissions
{
    public const VIEW = 'corrective_action.view';
    public const GENERATE_RECOMMENDATIONS = 'corrective_action.generate_recommendations';
    public const REVIEW_RECOMMENDATIONS = 'corrective_action.review_recommendations';
    public const ASSIGN = 'corrective_action.assign';
    public const UPDATE_STATUS = 'corrective_action.update_status';
    public const UPLOAD_EVIDENCE = 'corrective_action.upload_evidence';
    public const VERIFY = 'corrective_action.verify';
    public const MANAGE_LIBRARY = 'corrective_action.manage_library';
}
