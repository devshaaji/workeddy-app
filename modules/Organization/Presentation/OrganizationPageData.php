<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Organization\Presentation;

use WorkEddy\Modules\Organization\Authorization\OrganizationPermissions;
use WorkEddy\Platform\Session\UserContext;

final class OrganizationPageData
{
    /**
     * @return array<string, mixed>
     */
    public function common(UserContext $ctx, string $title): array
    {
        return [
            'pageTitle' => $title,
            'pagePurpose' => 'Organization structure',
            'organizationUuid' => $ctx->organizationUuid,
            'can' => [
                'manageOrganization' => in_array(OrganizationPermissions::MANAGE, $ctx->privileges, true),
                'manageMembers' => in_array(OrganizationPermissions::MEMBERS_MANAGE, $ctx->privileges, true),
                'manageStructure' => in_array(OrganizationPermissions::STRUCTURE_MANAGE, $ctx->privileges, true),
            ],
        ];
    }
}
