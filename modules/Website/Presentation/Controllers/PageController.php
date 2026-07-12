<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Website\Presentation\Controllers;

use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Modules\Website\Presentation\WebsitePageData;
use WorkEddy\Shared\Presentation\ViewRenderer;
use WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface;

final class PageController
{
    public function __construct(
        private readonly ViewRenderer $views,
        private readonly WebsitePageData $pageData,
        private readonly NotificationServiceInterface $notifications,
    ) {}

    public function home(Request $request): Response
    {
        return $this->render('Public/home.php', array_merge($this->pageData->home(), [
            'page' => 'home',
            'pageJs' => [
                '/assets/vendor/three/three.min.js',
                '/assets/vendor/three/OrbitControls.js',
                '/assets/js/home.js',
            ],
        ]));
    }

    public function about(Request $request): Response
    {
        return $this->render('Public/about.php', [
            'page' => 'about',
            'pageTitle' => 'Our Company - WorkEddy',
        ]);
    }

    public function founderMessage(Request $request): Response
    {
        return $this->render('Public/founder-message.php', [
            'page' => 'founder-message',
            'pageTitle' => 'Founder\'s Message - WorkEddy',
        ]);
    }

    public function whyUs(Request $request): Response
    {
        return $this->render('Public/why-us.php', [
            'page' => 'why-us',
            'pageTitle' => 'Why WorkEddy - WorkEddy',
        ]);
    }

    public function contactUs(Request $request): Response
    {
        return $this->render('Public/contact-us.php', [
            'page' => 'contact-us',
            'pageTitle' => 'Contact WorkEddy',
            'pageJs' => [
                '/assets/js/app.js',
                '/assets/js/contact-us.js',
            ],
        ]);
    }

    public function submitContactForm(Request $request): Response
    {
        $body = array_replace($request->body, $request->json);
        $errors = [];

        // Validate name
        $name = trim((string) ($body['name'] ?? ''));
        if ($name === '') {
            $errors['name'] = 'Name is required.';
        } elseif (mb_strlen($name) < 2) {
            $errors['name'] = 'Name must be at least 2 characters.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Name must not exceed 100 characters.';
        }

        // Validate organization
        $organization = trim((string) ($body['organization'] ?? ''));
        if ($organization === '') {
            $errors['organization'] = 'Organization is required.';
        } elseif (mb_strlen($organization) < 2) {
            $errors['organization'] = 'Organization must be at least 2 characters.';
        } elseif (mb_strlen($organization) > 100) {
            $errors['organization'] = 'Organization must not exceed 100 characters.';
        }

        // Validate email
        $email = trim((string) ($body['email'] ?? ''));
        if ($email === '') {
            $errors['email'] = 'Email is required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Please enter a valid email address.';
        }

        // Validate role (optional)
        $role = trim((string) ($body['role'] ?? ''));
        if ($role !== '' && mb_strlen($role) > 100) {
            $errors['role'] = 'Role must not exceed 100 characters.';
        }

        // Validate industry (optional)
        $industry = trim((string) ($body['industry'] ?? ''));
        if ($industry !== '' && mb_strlen($industry) > 100) {
            $errors['industry'] = 'Industry must not exceed 100 characters.';
        }

        // Validate reason
        $reason = trim((string) ($body['reason'] ?? ''));
        $validReasons = [
            'Request a Pilot',
            'Schedule a Demo',
            'Research Collaboration',
            'Employer Inquiry',
            'Privacy or Worker Trust Question',
            'Partnership Opportunity',
            'General Question',
        ];
        if ($reason === '') {
            $errors['reason'] = 'Please select a reason for contact.';
        } elseif (!in_array($reason, $validReasons, true)) {
            $errors['reason'] = 'Invalid reason selected.';
        }

        // Validate message
        $message = trim((string) ($body['message'] ?? ''));
        if ($message === '') {
            $errors['message'] = 'Message is required.';
        } elseif (mb_strlen($message) < 10) {
            $errors['message'] = 'Message must be at least 10 characters.';
        } elseif (mb_strlen($message) > 2000) {
            $errors['message'] = 'Message must not exceed 2000 characters.';
        }

        if (!empty($errors)) {
            return Response::error('Validation failed.', 422, $errors);
        }

        try {
            $recipient = new \WorkEddy\Modules\Notification\Domain\NotificationRecipient(
                recipientId: 'admin',
                recipientType: 'system',
                name: 'WorkEddy Admin',
                email: 'hello@workeddy.com'
            );

            $notificationRequest = new \WorkEddy\Modules\Notification\Domain\NotificationRequest(
                type: new \WorkEddy\Modules\Notification\Domain\NotificationType('website.contact_submission'),
                recipient: $recipient,
                data: [
                    'name' => $name,
                    'organization' => $organization,
                    'email' => $email,
                    'role' => $role === '' ? null : $role,
                    'industry' => $industry === '' ? null : $industry,
                    'reason' => $reason,
                    'message' => $message,
                ],
                metadata: [
                    'type' => 'website.contact_submission',
                    'sourceModule' => 'Website',
                ]
            );

            $this->notifications->send($notificationRequest);

            // Send acknowledgement notification to the submitter
            $submitterRecipient = new \WorkEddy\Modules\Notification\Domain\NotificationRecipient(
                recipientId: 'guest',
                recipientType: 'user',
                name: $name,
                email: $email
            );

            $ackRequest = new \WorkEddy\Modules\Notification\Domain\NotificationRequest(
                type: new \WorkEddy\Modules\Notification\Domain\NotificationType('website.contact_acknowledgement'),
                recipient: $submitterRecipient,
                data: [
                    'name' => $name,
                    'organization' => $organization,
                    'email' => $email,
                    'role' => $role === '' ? null : $role,
                    'industry' => $industry === '' ? null : $industry,
                    'reason' => $reason,
                    'message' => $message,
                ],
                metadata: [
                    'type' => 'website.contact_acknowledgement',
                    'sourceModule' => 'Website',
                ]
            );

            $this->notifications->send($ackRequest);

            return Response::success([], 'Thank you! Your message has been sent successfully.');
        } catch (\Throwable $e) {
            return Response::error('An unexpected error occurred while processing your request. Please try again.', 500);
        }
    }

    public function plans(Request $request): Response
    {
        return $this->render('Public/plans.php', array_merge($this->pageData->plans(), ['page' => 'plans']));
    }

    public function privacyPolicy(Request $request): Response
    {
        return $this->render('Public/privacy-policy.php', ['page' => 'privacy-policy']);
    }

    public function termsOfService(Request $request): Response
    {
        return $this->render('Public/terms-of-service.php', ['page' => 'terms-of-service']);
    }

    private function render(string $view, array $vars): Response
    {
        $commonData = $this->pageData->common();

        if (($commonData['maintenance_mode'] ?? false) === true) {
            return Response::html('<h1>Site Under Maintenance</h1><p>We will be back shortly.</p>', 503);
        }

        return $this->views->renderPublic(
            'modules/Website/Presentation/Views/' . $view,
            'Website',
            array_merge($commonData, $vars),
        );
    }
}
