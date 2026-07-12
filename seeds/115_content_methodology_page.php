<?php

declare(strict_types=1);

use Doctrine\DBAL\Connection;
use WorkEddy\Modules\Content\Support\MethodologyPageDefinition;
use WorkEddy\Platform\Seeding\SeederInterface;
use WorkEddy\Shared\Support\UuidSupport;

return new class implements SeederInterface
{
    public function run(Connection $db): void
    {
        $existing = $db->fetchAssociative('SELECT * FROM content_pages WHERE page_key = ? LIMIT 1', [MethodologyPageDefinition::PAGE_KEY]);
        if (is_array($existing)) {
            return;
        }

        $now = gmdate('Y-m-d H:i:s');
        $snapshot = MethodologyPageDefinition::seedSnapshot();
        $db->insert('content_pages', [
            'uuid' => UuidSupport::generate(),
            'page_key' => MethodologyPageDefinition::PAGE_KEY,
            'route_path' => '/content/methodology-and-limitations',
            'content_type' => 'managed_page',
            'template_key' => 'internal_methodology',
            'audience' => 'internal',
            'status' => 'draft',
            'published_revision_id' => null,
            'draft_revision_id' => null,
            'lock_version' => 1,
            'created_by' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'archived_at' => null,
            'archived_by' => null,
        ]);
        $pageId = (int) $db->lastInsertId();

        $db->insert('content_page_revisions', [
            'uuid' => UuidSupport::generate(),
            'page_id' => $pageId,
            'version_number' => 1,
            'revision_status' => 'published',
            'title' => 'Methodology and Limitations',
            'seo_title' => null,
            'seo_description' => null,
            'change_summary' => 'Seeded initial methodology publication.',
            'content_snapshot' => json_encode($snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE),
            'created_by' => null,
            'created_at' => $now,
            'updated_by' => null,
            'updated_at' => $now,
            'published_by' => null,
            'published_at' => $now,
        ]);
        $revisionId = (int) $db->lastInsertId();

        $db->update('content_pages', [
            'status' => 'published',
            'published_revision_id' => $revisionId,
            'draft_revision_id' => null,
            'lock_version' => 2,
            'updated_at' => $now,
        ], ['id' => $pageId]);
    }
};
