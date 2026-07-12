<?php

declare(strict_types=1);

use WorkEddy\Modules\Audit\ServiceProvider as AuditServiceProvider;
use WorkEddy\Modules\IAM\ServiceProvider as IAMServiceProvider;
use WorkEddy\Modules\Notification\ServiceProvider as NotificationServiceProvider;
use WorkEddy\Modules\Organization\ServiceProvider as OrganizationServiceProvider;
use WorkEddy\Modules\Export\ServiceProvider as ExportServiceProvider;
use WorkEddy\Modules\Website\ServiceProvider as WebsiteServiceProvider;
use WorkEddy\Platform\PlatformServiceProvider;
use WorkEddy\Modules\Storage\ServiceProvider as StorageServiceProvider;
use WorkEddy\Modules\Task\ServiceProvider as TaskServiceProvider;
use WorkEddy\Modules\Billing\ServiceProvider as BillingServiceProvider;
use WorkEddy\Modules\Ergonomics\ServiceProvider as ErgonomicsServiceProvider;
use WorkEddy\Modules\Assessment\ServiceProvider as AssessmentServiceProvider;
use WorkEddy\Modules\Privacy\ServiceProvider as PrivacyServiceProvider;
use WorkEddy\Modules\CorrectiveAction\ServiceProvider as CorrectiveActionServiceProvider;
use WorkEddy\Modules\Payment\ServiceProvider as PaymentServiceProvider;
use WorkEddy\Modules\Subscription\ServiceProvider as SubscriptionServiceProvider;
use WorkEddy\Modules\WorkerVoice\ServiceProvider as WorkerVoiceServiceProvider;
use WorkEddy\Modules\Finance\ServiceProvider as FinanceServiceProvider;
use WorkEddy\Modules\Reporting\ServiceProvider as ReportingServiceProvider;
use WorkEddy\Modules\Content\ServiceProvider as ContentServiceProvider;

return [
    PlatformServiceProvider::class,
    AuditServiceProvider::class,
    IAMServiceProvider::class,
    OrganizationServiceProvider::class,
    NotificationServiceProvider::class,
    TaskServiceProvider::class,
    ErgonomicsServiceProvider::class,
    AssessmentServiceProvider::class,
    PrivacyServiceProvider::class,
    CorrectiveActionServiceProvider::class,
    WebsiteServiceProvider::class,
    StorageServiceProvider::class,
    BillingServiceProvider::class,
    FinanceServiceProvider::class,
    ContentServiceProvider::class,
    PaymentServiceProvider::class,
    SubscriptionServiceProvider::class,
    WorkerVoiceServiceProvider::class,
    ExportServiceProvider::class,
    ReportingServiceProvider::class,
];
