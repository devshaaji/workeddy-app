<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class AssessmentPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'assessment';
    }

    public function definitions(): array
    {
        $organizationAdmins = ['organization_admin', 'org_admin'];

        return [
            new PermissionDefinition(AssessmentPermissions::VIEW, 'View assessments', 'View organization-scoped ergonomic assessments.', 'assessment', 'read', 'medium', [...$organizationAdmins, 'safety_manager', 'supervisor', 'worker', 'external_reviewer']),
            new PermissionDefinition(AssessmentPermissions::CREATE, 'Create assessments', 'Create manual ergonomic assessments.', 'assessment', 'write', 'high', [...$organizationAdmins, 'safety_manager', 'supervisor']),
            new PermissionDefinition(AssessmentPermissions::UPDATE, 'Update assessments', 'Update draft ergonomic assessments.', 'assessment', 'write', 'high', [...$organizationAdmins, 'safety_manager', 'supervisor']),
            new PermissionDefinition(AssessmentPermissions::REVIEW, 'Review assessments', 'Approve, adjust, or reject ergonomic assessment scores.', 'assessment', 'admin', 'critical', [...$organizationAdmins, 'safety_manager', 'external_reviewer']),
            new PermissionDefinition(AssessmentPermissions::LOCK, 'Lock assessments', 'Lock reviewed ergonomic assessments from further mutation.', 'assessment', 'admin', 'critical', [...$organizationAdmins, 'safety_manager', 'external_reviewer']),
            new PermissionDefinition(AssessmentPermissions::VIDEO_UPLOAD, 'Upload assessment video', 'Attach consented video evidence to an assessment.', 'assessment', 'write', 'high', [...$organizationAdmins, 'safety_manager', 'supervisor', 'worker']),
            new PermissionDefinition(AssessmentPermissions::VIEW_COMPARISON, 'View comparison reports', 'View before and after ergonomic comparison reports.', 'assessment', 'read', 'high', [...$organizationAdmins, 'safety_manager', 'supervisor', 'external_reviewer']),
            new PermissionDefinition(AssessmentPermissions::GENERATE_COMPARISON, 'Generate comparison reports', 'Generate before and after improvement proof reports.', 'assessment', 'write', 'critical', [...$organizationAdmins, 'safety_manager', 'external_reviewer']),
            new PermissionDefinition(AssessmentPermissions::LOCK_COMPARISON, 'Lock comparison reports', 'Lock finalized comparison reports from further edits.', 'assessment', 'admin', 'critical', [...$organizationAdmins, 'safety_manager', 'external_reviewer']),
        ];
    }
}
