<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Assessment\Presentation;

use WorkEddy\Modules\Assessment\Application\Processing\SubscriptionAssessmentVideoProcessingProfileResolver;
use WorkEddy\Modules\Assessment\Authorization\AssessmentPermissions;
use WorkEddy\Modules\Assessment\Settings\AssessmentSettings;
use WorkEddy\Modules\IAM\Domain\Contracts\IPermissionService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Shared\Exceptions\AuthenticationException;
use WorkEddy\Shared\Presentation\ViewRenderer;

final class AssessmentPageController
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly ISessionService $session,
        private readonly IPermissionService $permissions,
        private readonly AssessmentPageData $pageData,
        private readonly ComparisonPageData $comparisonPageData,
        private readonly AssessmentSettings $settings,
        private readonly SubscriptionAssessmentVideoProcessingProfileResolver $videoProfiles,
    ) {}

    public function index(Request $request): Response
    {
        return $this->render(
            'index.php',
            $request,
            $this->requirePrivilege(AssessmentPermissions::VIEW),
            'Assessments',
            ['pageScripts' => ['js/modules/assessment.js']],
        );
    }

    public function manualForm(Request $request): Response
    {
        return $this->render(
            'manual_form.php',
            $request,
            $this->requirePrivilege(AssessmentPermissions::CREATE),
            'New Manual Assessment',
            [
                'pageCss' => ['css/modules/assessment-manual-form.css'],
                'pageScripts' => ['js/modules/assessment.js'],
            ],
        );
    }

    public function videoCapture(Request $request): Response
    {
        $ctx = $this->requirePrivilege(AssessmentPermissions::VIDEO_UPLOAD);
        $profile = $this->videoProfiles->resolveForOrganization($ctx->organizationId);

        return $this->render(
            'video_capture.php',
            $request,
            $ctx,
            'Task Video Capture',
            [
                'videoCaptureConfig' => [
                    'consentVersion' => 'workeddy-video-consent-v1',
                    'privacyStatement' => 'WorkEddy is designed for ergonomic risk prevention and safety improvement, not worker discipline or productivity surveillance.',
                    'limits' => [
                        'maxVideoSizeBytes' => $this->settings->maxVideoSizeBytes(),
                        'maxDurationSeconds' => $profile->maxDurationSeconds,
                        'allowedFormats' => [
                            ['label' => 'MP4', 'mime' => 'video/mp4', 'extension' => 'mp4'],
                            ['label' => 'MOV', 'mime' => 'video/quicktime', 'extension' => 'mov'],
                            ['label' => 'WebM', 'mime' => 'video/webm', 'extension' => 'webm'],
                        ],
                    ],
                    'capabilities' => [
                        'uploadAllowed' => true,
                        'recordingAllowed' => true,
                        'consentRequired' => $this->settings->requireVideoConsent(),
                        'faceBlurMode' => 'optional',
                        'faceBlurDefault' => true,
                    ],
                ],
                'pageCss' => ['css/modules/assessment-video-capture.css'],
                'pageScripts' => ['js/modules/assessment-video-capture.js'],
            ],
        );
    }

    public function reviewerQueue(Request $request): Response
    {
        return $this->render(
            'reviewer_queue.php',
            $request,
            $this->requirePrivilege(AssessmentPermissions::REVIEW),
            'Reviewer Queue',
            ['pageScripts' => ['js/modules/assessment.js']],
        );
    }

    public function show(Request $request): Response
    {
        return $this->render(
            'show.php',
            $request,
            $this->requirePrivilege(AssessmentPermissions::VIEW),
            'Assessment Detail',
            ['pageScripts' => ['js/modules/assessment-detail.js']],
        );
    }

    public function review(Request $request): Response
    {
        return $this->render(
            'review.php',
            $request,
            $this->requirePrivilege(AssessmentPermissions::REVIEW),
            'Reviewer Validation',
            ['pageScripts' => ['js/modules/assessment-review.js']],
        );
    }

    public function validationReviews(Request $request): Response
    {
        return $this->render(
            'validation_reviews.php',
            $request,
            $this->requirePrivilege(AssessmentPermissions::REVIEW),
            'Validation Reviews',
            ['pageScripts' => ['js/modules/assessment-validation-reviews.js']],
        );
    }

    public function heatmap(Request $request): Response
    {
        return $this->render(
            'heatmap.php',
            $request,
            $this->requirePrivilege(AssessmentPermissions::VIEW),
            'Body Region Heat Map',
            ['pageScripts' => ['js/modules/assessment-heatmap.js']],
        );
    }

    public function videoEvidence(Request $request): Response
    {
        return $this->render(
            'video_evidence.php',
            $request,
            $this->requirePrivilege(AssessmentPermissions::VIEW),
            'Assessment Video Evidence',
            ['pageScripts' => ['js/modules/assessment-video-evidence.js']],
        );
    }

    public function comparisons(Request $request): Response
    {
        return $this->render(
            'comparisons.php',
            $request,
            $this->requirePrivilege(AssessmentPermissions::VIEW_COMPARISON),
            'Comparison Reports',
            ['pageScripts' => ['js/modules/assessment-comparisons.js']],
        );
    }

    public function comparisonCreate(Request $request): Response
    {
        return $this->render(
            'comparison_create.php',
            $request,
            $this->requirePrivilege(AssessmentPermissions::GENERATE_COMPARISON),
            'Generate Comparison Report',
            ['pageScripts' => ['js/modules/assessment-comparison-create.js']],
        );
    }

    public function comparisonShow(Request $request): Response
    {
        $ctx = $this->requirePrivilege(AssessmentPermissions::VIEW_COMPARISON);
        $comparisonContext = $this->comparisonPageData->common($ctx, 'Comparison Report Detail');

        return $this->render(
            'comparison_show.php',
            $request,
            $ctx,
            'Comparison Report Detail',
            array_replace(
                $comparisonContext,
                $this->comparisonPageData->show($ctx, (string) ($request->routeParams['comparisonId'] ?? '')),
            ),
        );
    }

    private function render(string $view, Request $request, UserContext $ctx, string $title, array $extra = []): Response
    {
        return $this->views->render(
            'modules/Assessment/Presentation/Views/' . $view,
            'Assessment',
            array_replace($this->pageData->common($ctx, $title), ['routeParams' => $request->routeParams, 'query' => $request->query], $extra),
        );
    }

    private function requirePrivilege(string $privilege): UserContext
    {
        $ctx = $this->context();
        $this->permissions->requirePrivilege($ctx, $privilege);

        return $ctx;
    }

    private function context(): UserContext
    {
        $ctx = $this->session->getUserContext();
        if ($ctx === null) {
            throw new AuthenticationException('Unauthenticated');
        }

        return $ctx;
    }
}
