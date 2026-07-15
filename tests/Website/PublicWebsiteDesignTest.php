<?php

declare(strict_types=1);

namespace WorkEddy\Tests\Website;

use PHPUnit\Framework\TestCase;
use WorkEddy\Modules\Subscription\Domain\Entities\SubscriptionPlan;

final class PublicWebsiteDesignTest extends TestCase
{
    public function testPublicWebsiteViewsAvoidLegacyTerminalTreatment(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = glob($root . '/modules/Website/Presentation/Views/Public/*.php');

        self::assertNotFalse($paths);
        self::assertNotSame([], $paths);

        $blockedPatterns = [
            'grid-bg',
            'tech-panel',
            'font-mono',
            'btn-tech',
            'btn-outline-tech',
            '<script',
            '<style',
            'onclick=',
        ];

        foreach ($paths as $path) {
            $contents = file_get_contents($path);
            self::assertIsString($contents);

            foreach ($blockedPatterns as $pattern) {
                self::assertStringNotContainsString(
                    $pattern,
                    $contents,
                    sprintf('%s should not contain legacy public-site pattern "%s".', basename($path), $pattern),
                );
            }
        }
    }

    public function testHomeHeroUsesLocalThreeJsProductVisual(): void
    {
        $root = dirname(__DIR__, 2);

        $home = file_get_contents($root . '/modules/Website/Presentation/Views/Public/home.php');
        self::assertIsString($home);
        self::assertStringContainsString('id="three-pose-container"', $home);
        self::assertStringContainsString('hero-3d-wrap', $home);
        self::assertStringContainsString('hero-marquee', $home);
        self::assertStringContainsString('Before intervention', $home);
        self::assertStringContainsString('After adjustment', $home);
        self::assertStringNotContainsString('product-preview hero-3d-preview', $home);
        self::assertStringNotContainsString('preview-toolbar', $home);
        self::assertStringNotContainsString('hero-risk-summary', $home);
        self::assertStringNotContainsString('hero-risk-panel', $home);
        self::assertStringNotContainsString('hero-action-card', $home);

        $controller = file_get_contents($root . '/modules/Website/Presentation/Controllers/PageController.php');
        self::assertIsString($controller);
        self::assertStringContainsString('/assets/vendor/three/three.min.js', $controller);
        self::assertStringContainsString('/assets/vendor/three/OrbitControls.js', $controller);
        self::assertStringContainsString('/assets/js/home.js', $controller);

        self::assertFileExists($root . '/public/assets/vendor/three/three.min.js');
        self::assertFileExists($root . '/public/assets/vendor/three/OrbitControls.js');
    }

    public function testAboutDropdownExposesRequiredCompanyPages(): void
    {
        $root = dirname(__DIR__, 2);

        $navbar = file_get_contents($root . '/shared/Views/Partials/navbar_public.php');
        self::assertIsString($navbar);

        foreach ([
            '/about-us' => 'Our Company',
            '/founder-message' => 'Founder\'s Message',
            '/why-us' => 'Why Us',
            '/contact-us' => 'Contact Us',
        ] as $href => $label) {
            self::assertStringContainsString('href="' . $href . '"', $navbar);
            self::assertStringContainsString($label, $navbar);
        }

        self::assertStringContainsString('dropdown-toggle', $navbar);
        self::assertStringContainsString('aria-expanded="false"', $navbar);
    }

    public function testAboutDropdownPagesAreRoutedAndRenderedByDedicatedViews(): void
    {
        $root = dirname(__DIR__, 2);

        $routes = file_get_contents($root . '/modules/Website/Presentation/routes.php');
        self::assertIsString($routes);

        foreach (['/about-us', '/founder-message', '/why-us', '/contact-us'] as $route) {
            self::assertStringContainsString($route, $routes);
        }

        $controller = file_get_contents($root . '/modules/Website/Presentation/Controllers/PageController.php');
        self::assertIsString($controller);

        foreach (['founderMessage', 'whyUs', 'contactUs'] as $method) {
            self::assertStringContainsString('function ' . $method, $controller);
        }

        foreach (['about.php', 'founder-message.php', 'why-us.php', 'contact-us.php'] as $view) {
            self::assertFileExists($root . '/modules/Website/Presentation/Views/Public/' . $view);
        }
    }

    public function testAboutDropdownPagesUseContentSpecificPresentationAndPublicAssets(): void
    {
        $root = dirname(__DIR__, 2);
        $viewRoot = $root . '/modules/Website/Presentation/Views/Public/';

        $about = file_get_contents($viewRoot . 'about.php');
        $founder = file_get_contents($viewRoot . 'founder-message.php');
        $whyUs = file_get_contents($viewRoot . 'why-us.php');
        $contact = file_get_contents($viewRoot . 'contact-us.php');

        self::assertIsString($about);
        self::assertIsString($founder);
        self::assertIsString($whyUs);
        self::assertIsString($contact);

        self::assertStringContainsString('prevention-evidence-workflow.png', $about);
        self::assertStringContainsString('founder-letter', $founder);
        self::assertStringContainsString('before-and-after-proof.png', $whyUs);
        self::assertStringContainsString('hello@workeddy.com', $contact);

        foreach ([
            'prevention-evidence-workflow.png',
            'privacy-first-ergonomic-assessment.png',
            'before-and-after-proof.png',
            'contact-collaboration-pathways.png',
        ] as $asset) {
            self::assertFileExists($root . '/public/assets/img/about/' . $asset);
        }
    }

    public function testFooterIncludesStandaloneCompanyDestinations(): void
    {
        $root = dirname(__DIR__, 2);

        $layout = file_get_contents($root . '/shared/Views/Layouts/public.php');
        self::assertIsString($layout);

        foreach (['/about-us', '/founder-message', '/why-us', '/contact-us'] as $href) {
            self::assertStringContainsString('href="' . $href . '"', $layout);
        }
    }

    public function testPlansPageAvoidsHardcodedQuotaComparisonTable(): void
    {
        $root = dirname(__DIR__, 2);

        $plans = file_get_contents($root . '/modules/Website/Presentation/Views/Public/plans.php');
        self::assertIsString($plans);
        self::assertStringNotContainsString('Compare Plans in Detail', $plans);
        self::assertStringNotContainsString('500 / month', $plans);
        self::assertStringNotContainsString('30 days', $plans);
        self::assertStringContainsString('Request detailed comparison', $plans);
    }

    public function testWebsitePageDataUsesPlanMarketingMetadataFromSubscriptionPlan(): void
    {
        $settingsService = new \WorkEddy\Platform\Settings\SettingsService([
            'website.site_name' => 'WorkEddy',
            'website.contact_email' => 'hello@workeddy.com',
            'website.support_phone' => '123-456-7890',
            'website.maintenance_mode' => false,
        ]);
        $websiteSettings = new \WorkEddy\Modules\Website\Settings\WebsiteSettings($settingsService);

        $planRepository = $this->createMock(\WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository::class);
        $planRepository->method('listActive')->willReturn([
            new SubscriptionPlan(
                id: 1,
                code: 'professional',
                name: 'Professional',
                description: 'For organizations managing ongoing ergonomic assessment and corrective-action workflows.',
                billingCycle: 'monthly',
                price: 299.0,
                currency: 'USD',
                features: [
                    'max_worksites' => 5,
                    'marketing' => [
                        'summary' => 'Backend-defined public summary.',
                        'highlights' => ['Backend-defined highlight'],
                        'cta_label' => 'Backend CTA',
                        'cta_href' => '/contact-us',
                        'featured' => true,
                        'custom_pricing' => false,
                    ],
                ],
                isActive: true,
                displayOrder: 2,
                createdAt: new \DateTimeImmutable('2026-07-15 00:00:00'),
                updatedAt: new \DateTimeImmutable('2026-07-15 00:00:00'),
            ),
        ]);

        $pageData = new \WorkEddy\Modules\Website\Presentation\WebsitePageData($websiteSettings, $planRepository);
        $payload = $pageData->plans();

        self::assertCount(1, $payload['plans']);
        self::assertSame('Backend-defined public summary.', $payload['plans'][0]['summary']);
        self::assertSame(['Backend-defined highlight'], $payload['plans'][0]['features']);
        self::assertSame('Backend CTA', $payload['plans'][0]['cta_label']);
        self::assertSame('/contact-us', $payload['plans'][0]['cta_href']);
        self::assertTrue($payload['plans'][0]['is_featured']);
    }

    public function testSubmitContactFormValidatesInputs(): void
    {
        $config = new \WorkEddy\Platform\Config\ConfigLoader();
        $views = new \WorkEddy\Shared\Presentation\ViewRenderer($config);
        $settingsService = new \WorkEddy\Platform\Settings\SettingsService([
            'website.site_name' => 'WorkEddy',
            'website.contact_email' => 'hello@workeddy.com',
            'website.support_phone' => '123-456-7890',
            'website.maintenance_mode' => false,
        ]);
        $websiteSettings = new \WorkEddy\Modules\Website\Settings\WebsiteSettings($settingsService);
        $subscriptionPlanRepo = $this->createMock(\WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository::class);
        $pageData = new \WorkEddy\Modules\Website\Presentation\WebsitePageData($websiteSettings, $subscriptionPlanRepo);
        $notificationsMock = $this->createMock(\WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface::class);

        $controller = new \WorkEddy\Modules\Website\Presentation\Controllers\PageController(
            $views,
            $pageData,
            $notificationsMock
        );

        // Test missing fields
        $request = new \WorkEddy\Platform\Http\Request(
            method: 'POST',
            uri: '/contact-us/submit',
            body: [
                'name' => '',
                'organization' => '',
                'email' => '',
                'reason' => '',
                'message' => ''
            ]
        );

        $response = $controller->submitContactForm($request);
        self::assertSame(422, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        self::assertSame('UNPROCESSABLE_ENTITY', $data['code']);
        self::assertArrayHasKey('name', $data['errors']);
        self::assertArrayHasKey('organization', $data['errors']);
        self::assertArrayHasKey('email', $data['errors']);
        self::assertArrayHasKey('reason', $data['errors']);
        self::assertArrayHasKey('message', $data['errors']);
    }

    public function testSubmitContactFormSendsAdminAndAcknowledgementEmails(): void
    {
        $config = new \WorkEddy\Platform\Config\ConfigLoader();
        $views = new \WorkEddy\Shared\Presentation\ViewRenderer($config);
        $settingsService = new \WorkEddy\Platform\Settings\SettingsService([
            'website.site_name' => 'WorkEddy',
            'website.contact_email' => 'hello@workeddy.com',
            'website.support_phone' => '123-456-7890',
            'website.maintenance_mode' => false,
        ]);
        $websiteSettings = new \WorkEddy\Modules\Website\Settings\WebsiteSettings($settingsService);
        $subscriptionPlanRepo = $this->createMock(\WorkEddy\Modules\Subscription\Domain\Contracts\ISubscriptionPlanRepository::class);
        $pageData = new \WorkEddy\Modules\Website\Presentation\WebsitePageData($websiteSettings, $subscriptionPlanRepo);
        $notificationsMock = $this->createMock(\WorkEddy\Modules\Notification\Contracts\NotificationServiceInterface::class);

        $sentRequests = [];
        $notificationsMock->expects(self::exactly(2))
            ->method('send')
            ->willReturnCallback(function (\WorkEddy\Modules\Notification\Domain\NotificationRequest $request) use (&$sentRequests) {
                $sentRequests[] = $request;
                return new \WorkEddy\Modules\Notification\Domain\NotificationDispatchResult(true, 'sent');
            });

        $controller = new \WorkEddy\Modules\Website\Presentation\Controllers\PageController(
            $views,
            $pageData,
            $notificationsMock
        );

        $request = new \WorkEddy\Platform\Http\Request(
            method: 'POST',
            uri: '/contact-us/submit',
            body: [
                'name' => 'John Doe',
                'organization' => 'Test Corp',
                'email' => 'john@test.corp',
                'role' => 'Safety Manager',
                'industry' => 'Manufacturing',
                'reason' => 'Request a Pilot',
                'message' => 'I would like to explore prevention work with WorkEddy.'
            ]
        );

        $response = $controller->submitContactForm($request);
        self::assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getBody(), true);
        self::assertSame('ok', $data['status']);

        self::assertCount(2, $sentRequests);

        // 1st request should be the system admin notification
        $adminRequest = $sentRequests[0];
        self::assertSame('website.contact_submission', $adminRequest->type->value);
        self::assertSame('hello@workeddy.com', $adminRequest->recipient->email);
        self::assertSame('John Doe', $adminRequest->data['name']);
        self::assertSame('Test Corp', $adminRequest->data['organization']);

        // 2nd request should be the user acknowledgement notification
        $ackRequest = $sentRequests[1];
        self::assertSame('website.contact_acknowledgement', $ackRequest->type->value);
        self::assertSame('john@test.corp', $ackRequest->recipient->email);
        self::assertSame('John Doe', $ackRequest->data['name']);
        self::assertSame('Test Corp', $ackRequest->data['organization']);
    }
}
