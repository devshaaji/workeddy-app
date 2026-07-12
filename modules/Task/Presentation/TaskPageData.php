<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Task\Presentation;

use WorkEddy\Modules\Task\Authorization\TaskPermissions;
use WorkEddy\Platform\Session\UserContext;

final class TaskPageData
{
    /**
     * @return array<string, mixed>
     */
    public function common(UserContext $ctx, string $title): array
    {
        return [
            'pageTitle' => $title,
            'pagePurpose' => 'Task structure',
            'organizationUuid' => $ctx->organizationUuid,
            'can' => [
                'createTask' => in_array(TaskPermissions::CREATE, $ctx->privileges, true),
                'updateTask' => in_array(TaskPermissions::UPDATE, $ctx->privileges, true),
            ],
        ];
    }
}
