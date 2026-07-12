<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Ergonomics;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Ergonomics\Application\ScoreErgonomicAssessmentUseCase;
use WorkEddy\Modules\Ergonomics\Authorization\ErgonomicsPermissions;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\Ergonomics\Domain\Services\NioshService;
use WorkEddy\Modules\Ergonomics\Domain\Services\RebaService;
use WorkEddy\Modules\Ergonomics\Domain\Services\RulaService;
use WorkEddy\Modules\Ergonomics\Settings\ErgonomicsSettings;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\ModuleSettings;

final class ErgonomicsModuleTest extends TestCase
{
    public function test_service_provider_exposes_settings_and_permissions(): void
    {
        $provider = new \WorkEddy\Modules\Ergonomics\ServiceProvider();

        self::assertSame('ergonomics', $provider->getName());
        self::assertNotNull($provider->getSettingsProvider());
        self::assertSame('ergonomics', $provider->getSettingsProvider()?->getModuleName());
        self::assertNotSame([], $provider->getSettingsProvider()?->getDefinitions());
        self::assertNotNull($provider->getPermissionDefinitionProvider());
        self::assertSame('ergonomics', $provider->getPermissionDefinitionProvider()?->module());
        self::assertTrue(is_subclass_of(ErgonomicsSettings::class, ModuleSettings::class));
    }

    public function test_assessment_engine_scores_reba_rula_and_niosh(): void
    {
        $engine = new AssessmentEngine([
            new RebaService(),
            new RulaService(),
            new NioshService(),
        ]);

        self::assertSame(['reba', 'rula', 'niosh'], $engine->availableModels());

        $reba = $engine->assess('reba', [
            'trunk_angle' => 70,
            'neck_angle' => 30,
            'upper_arm_angle' => 100,
            'lower_arm_angle' => 40,
            'wrist_angle' => 30,
            'leg_score' => 2,
            'load_weight' => 12,
            'coupling' => 'poor',
            'static_posture' => true,
            'repetitive' => true,
            'rapid_change' => true,
        ]);
        self::assertSame(13, $reba['score']);
        self::assertSame('high', $reba['risk_category']);
        self::assertSame('reba_official_v1', $reba['algorithm_version']);

        $rula = $engine->assess('rula', [
            'upper_arm_angle' => 100,
            'lower_arm_angle' => 40,
            'wrist_angle' => 30,
            'neck_angle' => 25,
            'trunk_angle' => 70,
            'leg_score' => 2,
            'load_weight' => 12,
            'static_posture' => true,
            'repetitive' => true,
        ]);
        self::assertSame(7, $rula['score']);
        self::assertSame('high', $rula['risk_category']);
        self::assertSame('rula_official_v1', $rula['algorithm_version']);

        $niosh = $engine->assess('niosh', [
            'load_weight' => 25,
            'horizontal_distance' => 40,
            'vertical_start' => 50,
            'vertical_travel' => 60,
            'twist_angle' => 45,
            'frequency' => 4,
            'coupling' => 'poor',
        ]);
        self::assertArrayHasKey('rwl', $niosh);
        self::assertArrayHasKey('lifting_index', $niosh);
        self::assertSame('niosh_official_v1', $niosh['algorithm_version']);
    }

    public function test_score_use_case_enforces_permission_and_returns_normalized_shape(): void
    {
        $useCase = new ScoreErgonomicAssessmentUseCase(
            new AssessmentEngine([
                new RebaService(),
                new RulaService(),
                new NioshService(),
            ]),
            new AllowAllErgonomicsPermissionService(),
        );

        $result = $useCase->execute(
            model: 'rula',
            inputType: 'manual',
            metrics: [
                'upper_arm_angle' => 10,
                'lower_arm_angle' => 90,
                'wrist_angle' => 0,
                'neck_angle' => 5,
                'trunk_angle' => 0,
            ],
            actor: new UserContext(
                userId: 7,
                roleType: 'staff',
                privileges: [ErgonomicsPermissions::SCORE],
            ),
        );

        self::assertSame('rula', $result['model']);
        self::assertSame('manual', $result['inputType']);
        self::assertSame('manual', $result['scoreSource']);
        self::assertSame(1.0, $result['score']['raw']);
        self::assertSame('rula_official_v1', $result['algorithmVersion']);
    }
}

final class AllowAllErgonomicsPermissionService implements IPermissionService
{
    public function requirePrivilege(UserContext $ctx, string $privilege): void
    {
        if (!in_array($privilege, $ctx->privileges, true)) {
            throw new \RuntimeException('Missing expected privilege in test context: ' . $privilege);
        }
    }
}
