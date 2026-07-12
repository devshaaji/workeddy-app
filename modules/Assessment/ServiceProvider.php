<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment;

use Doctrine\DBAL\Connection;
use Psr\Container\ContainerInterface;
use WorkEddy\Modules\Assessment\Application\AttachAssessmentVideoUseCase;
use WorkEddy\Modules\Assessment\Application\CreateManualAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\CreateVideoAssessmentForProcessingUseCase;
use WorkEddy\Modules\Assessment\Application\GenerateComparisonReportUseCase;
use WorkEddy\Modules\Assessment\Application\GetAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\GetComparisonReportUseCase;
use WorkEddy\Modules\Assessment\Application\ClaimAssessmentVideoJobUseCase;
use WorkEddy\Modules\Assessment\Application\CompleteAssessmentVideoJobUseCase;
use WorkEddy\Modules\Assessment\Application\EnqueueAssessmentVideoProcessingUseCase;
use WorkEddy\Modules\Assessment\Application\FailAssessmentVideoJobUseCase;
use WorkEddy\Modules\Assessment\Application\ListAssessmentsUseCase;
use WorkEddy\Modules\Assessment\Application\ListComparisonReportsUseCase;
use WorkEddy\Modules\Assessment\Application\ListValidationReviewsUseCase;
use WorkEddy\Modules\Assessment\Application\LockComparisonReportUseCase;
use WorkEddy\Modules\Assessment\Application\MarkAssessmentBaselineUseCase;
use WorkEddy\Modules\Assessment\Application\Processing\AssessmentVideoProcessingProfileResolver;
use WorkEddy\Modules\Assessment\Application\Processing\SubscriptionAssessmentVideoProcessingProfileResolver;
use WorkEddy\Modules\Assessment\Application\ReviewAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\Services\AssessmentComparisonService;
use WorkEddy\Modules\Assessment\Application\Services\ImprovementProofService;
use WorkEddy\Modules\Assessment\Application\Services\ValidationAgreementService;
use WorkEddy\Modules\Assessment\Application\SubmitAssessmentForReviewUseCase;
use WorkEddy\Modules\Assessment\Application\SubmitValidationReviewUseCase;
use WorkEddy\Modules\Assessment\Application\UpdateAssessmentUseCase;
use WorkEddy\Modules\Assessment\Application\UploadAssessmentVideoForProcessingUseCase;
use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissionDefinitionProvider;
use WorkEddy\Modules\Assessment\Domain\Contracts\IAssessmentRepository;
use WorkEddy\Modules\Assessment\Domain\Contracts\IValidationReviewRepository;
use WorkEddy\Modules\Assessment\Infrastructure\AssessmentRepository;
use WorkEddy\Modules\Assessment\Infrastructure\ValidationReviewRepository;
use WorkEddy\Modules\Assessment\Presentation\AssessmentWorkerController;
use WorkEddy\Modules\Assessment\Settings\AssessmentSettings;
use WorkEddy\Modules\Assessment\Settings\AssessmentSettingsProvider;
use WorkEddy\Modules\CorrectiveAction\Domain\Contracts\ICorrectiveActionRepository;
use WorkEddy\Modules\Ergonomics\Domain\Services\AssessmentEngine;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Privacy\Application\RecordVideoConsentUseCase;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionLimitGuard;
use WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionUsageRecorder;
use WorkEddy\Modules\Task\Domain\Contracts\ITaskRepository;
use WorkEddy\Platform\Audit\IAuditService;
use WorkEddy\Platform\Authorization\IPermissionDefinitionProvider;
use WorkEddy\Platform\Clock\IClock;
use WorkEddy\Platform\Module\ModuleServiceProviderInterface;
use WorkEddy\Platform\Queue\IQueueService;
use WorkEddy\Platform\Settings\IModuleSettingsProvider;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Platform\Transaction\TransactionManagerInterface;

final class ServiceProvider implements ModuleServiceProviderInterface
{
    public function getName(): string
    {
        return 'assessment';
    }

    public function getDefinitions(): array
    {
        return [
            AssessmentRepository::class => static fn(ContainerInterface $c): AssessmentRepository => new AssessmentRepository(
                $c->get(Connection::class),
                $c->get(IClock::class),
            ),
            IAssessmentRepository::class => static fn(ContainerInterface $c): IAssessmentRepository => $c->get(AssessmentRepository::class),
            ValidationReviewRepository::class => static fn(ContainerInterface $c): ValidationReviewRepository => new ValidationReviewRepository($c->get(Connection::class)),
            IValidationReviewRepository::class => static fn(ContainerInterface $c): IValidationReviewRepository => $c->get(ValidationReviewRepository::class),
            AssessmentSettings::class => static fn(ContainerInterface $c): AssessmentSettings => new AssessmentSettings($c->get(SettingsService::class)),
            AssessmentVideoProcessingProfileResolver::class => static fn(): AssessmentVideoProcessingProfileResolver => new AssessmentVideoProcessingProfileResolver(),
            SubscriptionAssessmentVideoProcessingProfileResolver::class => static fn(ContainerInterface $c): SubscriptionAssessmentVideoProcessingProfileResolver => new SubscriptionAssessmentVideoProcessingProfileResolver(
                $c->get(\WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionRepository::class),
                $c->get(\WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository::class),
                $c->get(AssessmentVideoProcessingProfileResolver::class),
            ),
            ImprovementProofService::class => static fn(): ImprovementProofService => new ImprovementProofService(),
            AssessmentComparisonService::class => static fn(ContainerInterface $c): AssessmentComparisonService => new AssessmentComparisonService($c->get(ImprovementProofService::class)),
            ValidationAgreementService::class => static fn(): ValidationAgreementService => new ValidationAgreementService(),
            CreateManualAssessmentUseCase::class => static fn(ContainerInterface $c): CreateManualAssessmentUseCase => new CreateManualAssessmentUseCase(
                $c->get(IOrganizationRepository::class),
                $c->get(ITaskRepository::class),
                $c->get(IAssessmentRepository::class),
                $c->get(AssessmentEngine::class),
                $c->get(IPermissionService::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(IAuditService::class),
                $c->get(ISubscriptionLimitGuard::class),
                $c->get(ISubscriptionUsageRecorder::class),
            ),
            CreateVideoAssessmentForProcessingUseCase::class => static fn(ContainerInterface $c): CreateVideoAssessmentForProcessingUseCase => new CreateVideoAssessmentForProcessingUseCase(
                $c->get(IOrganizationRepository::class),
                $c->get(ITaskRepository::class),
                $c->get(IAssessmentRepository::class),
                $c->get(AssessmentEngine::class),
                $c->get(IPermissionService::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(IAuditService::class),
                $c->get(ISubscriptionLimitGuard::class),
                $c->get(ISubscriptionUsageRecorder::class),
                $c->get(UploadAssessmentVideoForProcessingUseCase::class),
            ),
            SubmitAssessmentForReviewUseCase::class => static fn(ContainerInterface $c): SubmitAssessmentForReviewUseCase => new SubmitAssessmentForReviewUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IPermissionService::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(IAuditService::class),
            ),
            SubmitValidationReviewUseCase::class => static fn(ContainerInterface $c): SubmitValidationReviewUseCase => new SubmitValidationReviewUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IValidationReviewRepository::class),
                $c->get(IPermissionService::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(IAuditService::class),
            ),
            AttachAssessmentVideoUseCase::class => static fn(ContainerInterface $c): AttachAssessmentVideoUseCase => new AttachAssessmentVideoUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IPermissionService::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(IAuditService::class),
            ),
            ReviewAssessmentUseCase::class => static fn(ContainerInterface $c): ReviewAssessmentUseCase => new ReviewAssessmentUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IPermissionService::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(IAuditService::class),
            ),
            GetAssessmentUseCase::class => static fn(ContainerInterface $c): GetAssessmentUseCase => new GetAssessmentUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IPermissionService::class),
                $c->get(IValidationReviewRepository::class),
                $c->get(ValidationAgreementService::class),
            ),
            ListAssessmentsUseCase::class => static fn(ContainerInterface $c): ListAssessmentsUseCase => new ListAssessmentsUseCase(
                $c->get(IOrganizationRepository::class),
                $c->get(IAssessmentRepository::class),
                $c->get(IPermissionService::class),
            ),
            GenerateComparisonReportUseCase::class => static fn(ContainerInterface $c): GenerateComparisonReportUseCase => new GenerateComparisonReportUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(AssessmentComparisonService::class),
                $c->get(IPermissionService::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(IAuditService::class),
                $c->get(ICorrectiveActionRepository::class),
            ),
            GetComparisonReportUseCase::class => static fn(ContainerInterface $c): GetComparisonReportUseCase => new GetComparisonReportUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IPermissionService::class),
            ),
            ListComparisonReportsUseCase::class => static fn(ContainerInterface $c): ListComparisonReportsUseCase => new ListComparisonReportsUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IPermissionService::class),
            ),
            ListValidationReviewsUseCase::class => static fn(ContainerInterface $c): ListValidationReviewsUseCase => new ListValidationReviewsUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IValidationReviewRepository::class),
                $c->get(IPermissionService::class),
            ),
            LockComparisonReportUseCase::class => static fn(ContainerInterface $c): LockComparisonReportUseCase => new LockComparisonReportUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IPermissionService::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(IAuditService::class),
            ),
            UpdateAssessmentUseCase::class => static fn(ContainerInterface $c): UpdateAssessmentUseCase => new UpdateAssessmentUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(AssessmentEngine::class),
                $c->get(IPermissionService::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(IAuditService::class),
            ),
            MarkAssessmentBaselineUseCase::class => static fn(ContainerInterface $c): MarkAssessmentBaselineUseCase => new MarkAssessmentBaselineUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IPermissionService::class),
                $c->get(TransactionManagerInterface::class),
                $c->get(IAuditService::class),
            ),
            EnqueueAssessmentVideoProcessingUseCase::class => static fn(ContainerInterface $c): EnqueueAssessmentVideoProcessingUseCase => new EnqueueAssessmentVideoProcessingUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IQueueService::class),
                $c->get(IAuditService::class),
                $c->get(AssessmentVideoProcessingProfileResolver::class),
                $c->get(SubscriptionAssessmentVideoProcessingProfileResolver::class),
            ),
            ClaimAssessmentVideoJobUseCase::class => static fn(ContainerInterface $c): ClaimAssessmentVideoJobUseCase => new ClaimAssessmentVideoJobUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IQueueService::class),
            ),
            CompleteAssessmentVideoJobUseCase::class => static fn(ContainerInterface $c): CompleteAssessmentVideoJobUseCase => new CompleteAssessmentVideoJobUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IQueueService::class),
                $c->get(AssessmentEngine::class),
                $c->get(IAuditService::class),
                $c->get(IStorageService::class),
            ),
            FailAssessmentVideoJobUseCase::class => static fn(ContainerInterface $c): FailAssessmentVideoJobUseCase => new FailAssessmentVideoJobUseCase(
                $c->get(IAssessmentRepository::class),
                $c->get(IQueueService::class),
                $c->get(IAuditService::class),
            ),
            UploadAssessmentVideoForProcessingUseCase::class => static fn(ContainerInterface $c): UploadAssessmentVideoForProcessingUseCase => new UploadAssessmentVideoForProcessingUseCase(
                $c->get(IStorageService::class),
                $c->get(RecordVideoConsentUseCase::class),
                $c->get(AttachAssessmentVideoUseCase::class),
                $c->get(EnqueueAssessmentVideoProcessingUseCase::class),
                $c->get(ISubscriptionLimitGuard::class),
                $c->get(ISubscriptionUsageRecorder::class),
                $c->get(IOrganizationRepository::class),
                $c->get(AssessmentVideoProcessingProfileResolver::class),
                $c->get(SubscriptionAssessmentVideoProcessingProfileResolver::class),
                $c->get(AssessmentSettings::class),
            ),
            AssessmentWorkerController::class => static fn(ContainerInterface $c): AssessmentWorkerController => new AssessmentWorkerController(
                $c->get(ClaimAssessmentVideoJobUseCase::class),
                $c->get(CompleteAssessmentVideoJobUseCase::class),
                $c->get(FailAssessmentVideoJobUseCase::class),
            ),
        ];
    }

    public function getRouteFile(): ?string
    {
        return __DIR__ . '/Presentation/routes.php';
    }

    public function getEventListeners(): array
    {
        return [];
    }

    public function getJobHandlers(): array
    {
        return [];
    }

    public function getPermissionDefinitionProvider(): ?IPermissionDefinitionProvider
    {
        return new AssessmentPermissionDefinitionProvider();
    }

    public function getSettingsProvider(): ?IModuleSettingsProvider
    {
        return new AssessmentSettingsProvider();
    }

    public function getConsoleCommandProvider(): mixed
    {
        return null;
    }

    public function boot(ContainerInterface $container): void
    {
        unset($container);
    }
}
