<?php

declare(strict_types=1);

namespace WorkEddy\Modules\WorkerVoice\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class WorkerVoicePermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function module(): string
    {
        return 'worker_voice';
    }

    public function definitions(): array
    {
        $organizationAdmins = ['organization_admin', 'org_admin'];

        return [
            new PermissionDefinition(WorkerVoicePermissions::SUBMIT, 'Submit worker feedback', 'Submit worker discomfort and worker-voice feedback.', 'worker_voice', 'write', 'medium', [...$organizationAdmins, 'safety_manager', 'supervisor', 'worker']),
            new PermissionDefinition(WorkerVoicePermissions::VIEW, 'View worker feedback', 'View worker feedback records with anonymity redaction applied.', 'worker_voice', 'read', 'high', [...$organizationAdmins, 'safety_manager', 'supervisor', 'external_reviewer']),
            new PermissionDefinition(WorkerVoicePermissions::VIEW_SENSITIVE, 'View sensitive worker feedback', 'View worker feedback identity for non-anonymous records.', 'worker_voice', 'read', 'critical', [...$organizationAdmins, 'safety_manager']),
            new PermissionDefinition(WorkerVoicePermissions::VIEW_AGGREGATES, 'View worker feedback trends', 'View aggregated worker discomfort trends by task and body region.', 'worker_voice', 'read', 'high', [...$organizationAdmins, 'safety_manager', 'supervisor', 'external_reviewer']),
            new PermissionDefinition(WorkerVoicePermissions::EXPORT, 'Export worker feedback data', 'Export worker feedback trends and registers.', 'worker_voice', 'write', 'high', [...$organizationAdmins, 'safety_manager']),
        ];
    }
}
