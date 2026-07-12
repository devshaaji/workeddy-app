<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Content\Authorization;

final class ContentPermissions
{
    public const PAGES_READ = 'content.pages.read';
    public const PAGES_CREATE = 'content.pages.create';
    public const PAGES_UPDATE = 'content.pages.update';
    public const PAGES_PUBLISH = 'content.pages.publish';
    public const PAGES_RESTORE = 'content.pages.restore';
    public const PAGES_ARCHIVE = 'content.pages.archive';
    public const REFERENCES_MANAGE = 'content.references.manage';
    public const MEDIA_READ = 'content.media.read';
    public const MEDIA_UPLOAD = 'content.media.upload';
    public const MEDIA_UPDATE = 'content.media.update';
    public const MEDIA_ARCHIVE = 'content.media.archive';
    public const PREVIEW = 'content.preview';
}
