<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Authorization;

use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Authorization\PermissionDefinition;

final class ContentPermissionDefinitionProvider implements IPermissionDefinitionProvider
{
    public function definitions(): array
    {
        $admins = ['organization_admin', 'org_admin', 'safety_manager'];

        return [
            new PermissionDefinition(ContentPermissions::PAGES_READ, 'Read content pages', 'View managed editorial pages inside the platform.', 'content', 'read', 'medium', [...$admins, 'external_reviewer']),
            new PermissionDefinition(ContentPermissions::PAGES_CREATE, 'Create content pages', 'Create new managed editorial pages.', 'content', 'write', 'high', $admins),
            new PermissionDefinition(ContentPermissions::PAGES_UPDATE, 'Update content pages', 'Edit mutable page drafts.', 'content', 'write', 'high', $admins),
            new PermissionDefinition(ContentPermissions::PAGES_PUBLISH, 'Publish content pages', 'Publish managed content revisions.', 'content', 'admin', 'critical', $admins),
            new PermissionDefinition(ContentPermissions::PAGES_RESTORE, 'Restore content pages', 'Restore historical content revisions into a new draft.', 'content', 'admin', 'critical', $admins),
            new PermissionDefinition(ContentPermissions::PAGES_ARCHIVE, 'Archive content pages', 'Archive or unarchive managed pages.', 'content', 'admin', 'critical', $admins),
            new PermissionDefinition(ContentPermissions::REFERENCES_MANAGE, 'Manage references', 'Manage references embedded in managed content revisions.', 'content', 'write', 'high', $admins),
            new PermissionDefinition(ContentPermissions::MEDIA_READ, 'Read content media', 'Browse the content media library.', 'content', 'read', 'medium', $admins),
            new PermissionDefinition(ContentPermissions::MEDIA_UPLOAD, 'Upload content media', 'Upload editorial media into the content library.', 'content', 'write', 'high', $admins),
            new PermissionDefinition(ContentPermissions::MEDIA_UPDATE, 'Update content media', 'Update content media metadata.', 'content', 'write', 'high', $admins),
            new PermissionDefinition(ContentPermissions::MEDIA_ARCHIVE, 'Archive content media', 'Archive content media without deleting referenced assets.', 'content', 'admin', 'critical', $admins),
            new PermissionDefinition(ContentPermissions::PREVIEW, 'Preview content revisions', 'View draft and historical content previews.', 'content', 'read', 'high', $admins),
        ];
    }
}
