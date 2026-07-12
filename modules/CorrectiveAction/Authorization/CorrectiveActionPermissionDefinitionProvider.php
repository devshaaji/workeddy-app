<?php

declare(strict_types=1);

namespace WorkEddy\Modules\CorrectiveAction\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class CorrectiveActionPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'corrective_action';
    }

    public function definitions(): array
    {
        $organizationAdmins = ['organization_admin', 'org_admin'];

        return [
            new PermissionDefinition(CorrectiveActionPermissions::VIEW, 'View corrective actions', 'View recommendations and corrective actions.', 'corrective_action', 'read', 'medium', [...$organizationAdmins, 'safety_manager', 'supervisor', 'external_reviewer']),
            new PermissionDefinition(CorrectiveActionPermissions::GENERATE_RECOMMENDATIONS, 'Generate recommendations', 'Generate controls from reviewed assessment evidence.', 'corrective_action', 'write', 'high', [...$organizationAdmins, 'safety_manager']),
            new PermissionDefinition(CorrectiveActionPermissions::REVIEW_RECOMMENDATIONS, 'Review recommendations', 'Accept, edit, or reject generated recommendations.', 'corrective_action', 'write', 'high', [...$organizationAdmins, 'safety_manager']),
            new PermissionDefinition(CorrectiveActionPermissions::ASSIGN, 'Assign corrective actions', 'Assign corrective actions and due dates.', 'corrective_action', 'write', 'high', [...$organizationAdmins, 'safety_manager', 'supervisor']),
            new PermissionDefinition(CorrectiveActionPermissions::UPDATE_STATUS, 'Update corrective action status', 'Move corrective actions through implementation workflow.', 'corrective_action', 'write', 'high', [...$organizationAdmins, 'safety_manager', 'supervisor']),
            new PermissionDefinition(CorrectiveActionPermissions::UPLOAD_EVIDENCE, 'Upload corrective action evidence', 'Upload implementation evidence through Storage.', 'corrective_action', 'write', 'high', [...$organizationAdmins, 'safety_manager', 'supervisor']),
            new PermissionDefinition(CorrectiveActionPermissions::VERIFY, 'Verify corrective actions', 'Verify completed corrective actions and schedule follow-up.', 'corrective_action', 'admin', 'critical', [...$organizationAdmins, 'safety_manager', 'external_reviewer']),
            new PermissionDefinition(CorrectiveActionPermissions::MANAGE_LIBRARY, 'Manage corrective action library', 'Edit reusable controls and recommendation rules.', 'corrective_action', 'admin', 'critical', [...$organizationAdmins, 'safety_manager']),
        ];
    }
}
