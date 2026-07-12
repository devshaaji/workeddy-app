<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Content;

use Doctrine\DBAL\Schema\Schema;
use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Content\Authorization\ContentPermissionDefinitionProvider;
use WorkEddy\Modules\Content\Authorization\ContentPermissions;
use WorkEddy\Modules\Content\ServiceProvider;
use WorkEddy\Platform\Schema\CanonicalSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Content\ContentSchemaBuilder;

final class ContentModuleTest extends TestCase
{
    public function test_service_provider_exposes_routes_and_permissions(): void
    {
        $provider = new ServiceProvider();

        self::assertSame('content', $provider->getName());
        self::assertStringEndsWith('Presentation/routes.php', (string) $provider->getRouteFile());
        self::assertNotNull($provider->getPermissionDefinitionProvider());
    }

    public function test_permission_definition_provider_exposes_canonical_permission_keys(): void
    {
        $provider = new ContentPermissionDefinitionProvider();
        $definitions = [];

        foreach ($provider->definitions() as $definition) {
            $definitions[$definition->key] = $definition;
        }

        self::assertArrayHasKey(ContentPermissions::PAGES_READ, $definitions);
        self::assertArrayHasKey(ContentPermissions::PAGES_CREATE, $definitions);
        self::assertArrayHasKey(ContentPermissions::PAGES_UPDATE, $definitions);
        self::assertArrayHasKey(ContentPermissions::PAGES_PUBLISH, $definitions);
        self::assertArrayHasKey(ContentPermissions::PAGES_RESTORE, $definitions);
        self::assertArrayHasKey(ContentPermissions::PAGES_ARCHIVE, $definitions);
        self::assertArrayHasKey(ContentPermissions::REFERENCES_MANAGE, $definitions);
        self::assertArrayHasKey(ContentPermissions::MEDIA_READ, $definitions);
        self::assertArrayHasKey(ContentPermissions::MEDIA_UPLOAD, $definitions);
        self::assertArrayHasKey(ContentPermissions::MEDIA_UPDATE, $definitions);
        self::assertArrayHasKey(ContentPermissions::MEDIA_ARCHIVE, $definitions);
        self::assertArrayHasKey(ContentPermissions::PREVIEW, $definitions);
    }

    public function test_schema_builder_registers_content_tables_and_canonical_schema_includes_them(): void
    {
        $builder = new ContentSchemaBuilder();
        $schema = new Schema();
        $builder->build($schema);

        self::assertTrue($schema->hasTable('content_pages'));
        self::assertTrue($schema->hasTable('content_page_revisions'));
        self::assertTrue($schema->hasTable('content_media'));

        $canonical = new CanonicalSchemaBuilder();
        self::assertContains('content_pages', $canonical->tables());
        self::assertContains('content_page_revisions', $canonical->tables());
        self::assertContains('content_media', $canonical->tables());
    }
}
