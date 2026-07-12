<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Platform;

use PHPUnit\Framework\TestCase;
use WorkEddy\Platform\Schema\CanonicalSchemaBuilder;

final class QueueSchemaTest extends TestCase
{
    public function test_platform_jobs_schema_matches_queue_service_columns(): void
    {
        $table = (new CanonicalSchemaBuilder())->buildAll()->getTable('platform_jobs');

        foreach (['job_id', 'queue', 'job_type', 'payload', 'status', 'attempts', 'max_attempts', 'available_at', 'locked_by', 'locked_until', 'last_error', 'completed_at', 'failed_at', 'created_at', 'updated_at'] as $column) {
            self::assertTrue($table->hasColumn($column), 'Missing platform_jobs.' . $column);
        }
    }
}
