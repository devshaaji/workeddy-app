<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema\Modules\Assessment;

use Doctrine\DBAL\Schema\Schema;
use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\SchemaSupport;

final class AssessmentSchemaBuilder extends SchemaSupport implements ModuleSchemaBuilder
{
    public function module(): string
    {
        return 'assessment';
    }

    public function tables(): array
    {
        return [
            'assessments',
            'assessment_risk_factors',
            'assessment_body_regions',
            'assessment_videos',
            'assessment_video_processing_results',
            'ai_score_outputs',
            'comparison_reports',
            'validation_reviews',
        ];
    }

    public function build(Schema $schema): void
    {
        $this->buildAssessments($schema);
        $this->buildRiskFactors($schema);
        $this->buildBodyRegions($schema);
        $this->buildVideos($schema);
        $this->buildVideoProcessingResults($schema);
        $this->buildAiScoreOutputs($schema);
        $this->buildComparisonReports($schema);
        $this->buildValidationReviews($schema);
    }

    private function buildAssessments(Schema $schema): void
    {
        if ($schema->hasTable('assessments')) {
            return;
        }

        $table = $schema->createTable('assessments');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $this->uuid($table, 'organization_uuid');
        $table->addColumn('task_id', 'integer');
        $this->uuid($table, 'task_uuid');
        $table->addColumn('model', 'string', ['length' => 40]);
        $table->addColumn('metrics_json', 'text');
        $table->addColumn('initial_score_json', 'text');
        $table->addColumn('final_score_json', 'text', ['notnull' => false]);
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'draft']);
        $table->addColumn('is_baseline', 'boolean', ['default' => false]);
        $table->addColumn('score_source', 'string', ['length' => 40, 'default' => 'manual']);
        $table->addColumn('created_by', 'integer');
        $table->addColumn('reviewer_id', 'integer', ['notnull' => false]);
        $table->addColumn('reviewer_name', 'string', ['length' => 180, 'notnull' => false]);
        $table->addColumn('reviewer_credentials', 'string', ['length' => 120, 'notnull' => false]);
        $table->addColumn('reviewer_notes', 'text', ['notnull' => false]);
        $table->addColumn('adjustment_reason', 'text', ['notnull' => false]);
        $this->timestamps($table);
        $this->softDeletes($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'assessments_uuid_unique');
        $table->addIndex(['organization_id', 'status'], 'assessments_org_status_idx');
        $table->addIndex(['task_id', 'model'], 'assessments_task_model_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'assessments_org_fk');
        $table->addForeignKeyConstraint('tasks', ['task_id'], ['id'], ['onDelete' => 'CASCADE'], 'assessments_task_fk');
    }

    private function buildRiskFactors(Schema $schema): void
    {
        if ($schema->hasTable('assessment_risk_factors')) {
            return;
        }

        $table = $schema->createTable('assessment_risk_factors');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('assessment_id', 'integer');
        $table->addColumn('factor_key', 'string', ['length' => 120]);
        $this->createdAt($table);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['assessment_id', 'factor_key'], 'assessment_risk_factor_idx');
        $table->addForeignKeyConstraint('assessments', ['assessment_id'], ['id'], ['onDelete' => 'CASCADE'], 'assessment_risk_factor_assessment_fk');
    }

    private function buildBodyRegions(Schema $schema): void
    {
        if ($schema->hasTable('assessment_body_regions')) {
            return;
        }

        $table = $schema->createTable('assessment_body_regions');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $table->addColumn('assessment_id', 'integer');
        $table->addColumn('region', 'string', ['length' => 120]);
        $table->addColumn('side', 'string', ['length' => 40]);
        $table->addColumn('intensity', 'integer', ['default' => 0]);
        $this->createdAt($table);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['assessment_id', 'region'], 'assessment_body_region_idx');
        $table->addForeignKeyConstraint('assessments', ['assessment_id'], ['id'], ['onDelete' => 'CASCADE'], 'assessment_body_region_assessment_fk');
    }

    private function buildVideos(Schema $schema): void
    {
        if ($schema->hasTable('assessment_videos')) {
            return;
        }

        $table = $schema->createTable('assessment_videos');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('assessment_id', 'integer');
        $this->uuid($table, 'storage_file_uuid');
        $table->addColumn('original_filename', 'string', ['length' => 255]);
        $table->addColumn('mime_type', 'string', ['length' => 120]);
        $table->addColumn('size_bytes', 'integer');
        $table->addColumn('duration_seconds', 'integer');
        $table->addColumn('consent_text_version', 'string', ['length' => 120]);
        $table->addColumn('face_blur_requested', 'boolean', ['default' => false]);
        $table->addColumn('processing_status', 'string', ['length' => 40, 'default' => 'pending']);
        $table->addColumn('processing_started_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('processing_completed_at', 'datetime_immutable', ['notnull' => false]);
        $table->addColumn('processing_error', 'text', ['notnull' => false]);
        $this->uuid($table, 'thumbnail_storage_file_uuid', false);
        $this->uuid($table, 'pose_video_storage_file_uuid', false);
        $table->addColumn('blurred_storage_file_uuid', 'string', ['length' => 36, 'notnull' => false, 'fixed' => true]);
        $table->addColumn('faces_blurred', 'boolean', ['default' => false]);
        $table->addColumn('processing_confidence', 'decimal', ['precision' => 6, 'scale' => 4, 'notnull' => false]);
        $this->createdAt($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'assessment_videos_uuid_unique');
        $table->addIndex(['assessment_id', 'processing_status'], 'assessment_videos_assessment_status_idx');
        $table->addForeignKeyConstraint('assessments', ['assessment_id'], ['id'], ['onDelete' => 'CASCADE'], 'assessment_videos_assessment_fk');
    }

    private function buildVideoProcessingResults(Schema $schema): void
    {
        if ($schema->hasTable('assessment_video_processing_results')) {
            return;
        }

        $table = $schema->createTable('assessment_video_processing_results');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'assessment_uuid');
        $this->uuid($table, 'assessment_video_uuid');
        $table->addColumn('video_sha256', 'string', ['length' => 64]);
        $table->addColumn('processing_profile_hash', 'string', ['length' => 64]);
        $table->addColumn('metrics_json', 'text');
        $table->addColumn('timeline_json', 'text');
        $table->addColumn('risky_windows_json', 'text');
        $this->uuid($table, 'pose_video_storage_file_uuid', false);
        $this->uuid($table, 'thumbnail_storage_file_uuid', false);
        $table->addColumn('blurred_storage_file_uuid', 'string', ['length' => 36, 'notnull' => false, 'fixed' => true]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addIndex(['assessment_uuid'], 'assessment_video_results_assessment_idx');
        $table->addIndex(['assessment_video_uuid'], 'assessment_video_results_video_idx');
        $table->addIndex(['video_sha256', 'processing_profile_hash'], 'assessment_video_results_reuse_idx');
    }

    private function buildComparisonReports(Schema $schema): void
    {
        if ($schema->hasTable('comparison_reports')) {
            return;
        }

        $table = $schema->createTable('comparison_reports');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $this->uuid($table, 'organization_uuid');
        $this->uuid($table, 'baseline_assessment_uuid');
        $this->uuid($table, 'follow_up_assessment_uuid');
        $this->uuid($table, 'corrective_action_uuid', false);
        $table->addColumn('model', 'string', ['length' => 40]);
        $table->addColumn('baseline_score_json', 'text');
        $table->addColumn('follow_up_score_json', 'text');
        $table->addColumn('score_diff_json', 'text');
        $table->addColumn('risk_reduction_percent', 'decimal', ['precision' => 8, 'scale' => 2, 'default' => '0.00']);
        $table->addColumn('direction', 'string', ['length' => 40, 'default' => 'unchanged']);
        $table->addColumn('body_regions_improved_json', 'text');
        $table->addColumn('body_regions_worsened_json', 'text');
        $table->addColumn('evidence_chain_json', 'text');
        $table->addColumn('status', 'string', ['length' => 40, 'default' => 'generated']);
        $table->addColumn('generated_by', 'integer');
        $table->addColumn('generated_at', 'datetime_immutable');
        $table->addColumn('locked_at', 'datetime_immutable', ['notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'comparison_reports_uuid_unique');
        $table->addUniqueIndex(['baseline_assessment_uuid', 'follow_up_assessment_uuid'], 'comparison_reports_pair_unique');
        $table->addIndex(['organization_id', 'generated_at'], 'comparison_reports_org_generated_idx');
        $table->addIndex(['corrective_action_uuid'], 'comparison_reports_corrective_action_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'comparison_reports_org_fk');
    }

    private function buildAiScoreOutputs(Schema $schema): void
    {
        if ($schema->hasTable('ai_score_outputs')) {
            return;
        }

        $table = $schema->createTable('ai_score_outputs');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $this->uuid($table, 'assessment_uuid');
        $this->uuid($table, 'assessment_video_uuid', false);
        $table->addColumn('score_model', 'string', ['length' => 40]);
        $table->addColumn('score_source', 'string', ['length' => 40, 'default' => 'ai_estimated']);
        $table->addColumn('model_version', 'string', ['length' => 160]);
        $table->addColumn('confidence', 'decimal', ['precision' => 6, 'scale' => 4, 'notnull' => false]);
        $table->addColumn('metrics_json', 'text');
        $table->addColumn('score_json', 'text');
        $table->addColumn('timeline_json', 'text');
        $table->addColumn('flags_json', 'text');
        $table->addColumn('metadata_json', 'text');
        $table->addColumn('created_by_worker', 'string', ['length' => 120, 'notnull' => false]);
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'ai_score_outputs_uuid_unique');
        $table->addIndex(['assessment_uuid', 'created_at'], 'ai_score_outputs_assessment_created_idx');
        $table->addIndex(['assessment_video_uuid'], 'ai_score_outputs_video_idx');
    }

    private function buildValidationReviews(Schema $schema): void
    {
        if ($schema->hasTable('validation_reviews')) {
            return;
        }

        $table = $schema->createTable('validation_reviews');
        $table->addColumn('id', 'integer', ['autoincrement' => true]);
        $this->uuid($table, 'uuid');
        $table->addColumn('organization_id', 'integer');
        $this->uuid($table, 'organization_uuid');
        $this->uuid($table, 'assessment_uuid');
        $table->addColumn('assessment_version', 'string', ['length' => 64]);
        $table->addColumn('reviewer_user_id', 'integer');
        $table->addColumn('reviewer_name', 'string', ['length' => 180]);
        $table->addColumn('reviewer_credentials', 'string', ['length' => 180, 'notnull' => false]);
        $table->addColumn('review_round', 'integer', ['default' => 1]);
        $table->addColumn('score_json', 'text');
        $table->addColumn('risk_level', 'string', ['length' => 80]);
        $table->addColumn('body_regions_json', 'text');
        $table->addColumn('risk_factors_json', 'text');
        $table->addColumn('notes', 'text', ['notnull' => false]);
        $table->addColumn('is_primary', 'boolean', ['default' => false]);
        $table->addColumn('is_final', 'boolean', ['default' => true]);
        $table->addColumn('submitted_at', 'datetime_immutable');
        $this->timestamps($table);
        $table->setPrimaryKey(['id']);
        $table->addUniqueIndex(['uuid'], 'validation_reviews_uuid_unique');
        $table->addIndex(['assessment_uuid', 'review_round'], 'validation_reviews_assessment_round_idx');
        $table->addIndex(['organization_id', 'submitted_at'], 'validation_reviews_org_submitted_idx');
        $table->addForeignKeyConstraint('organizations', ['organization_id'], ['id'], ['onDelete' => 'CASCADE'], 'validation_reviews_org_fk');
    }
}
