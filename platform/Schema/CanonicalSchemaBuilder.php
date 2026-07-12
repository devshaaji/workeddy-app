<?php

declare(strict_types=1);

namespace WorkEddy\Platform\Schema;

use WorkEddy\Platform\Schema\Contracts\ModuleSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Assessment\AssessmentSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Audit\AuditSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Billing\BillingSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Content\ContentSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\CorrectiveAction\CorrectiveActionSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Export\ExportSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Finance\FinanceSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Platform\PlatformSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Platform\TransportSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\IAM\IamSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Notification\NotificationSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Organization\OrganizationSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Payment\PaymentSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Privacy\PrivacySchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Reporting\ReportingSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Storage\StorageSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Subscription\SubscriptionSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\SupportTicket\SupportTicketSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Task\TaskSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\Website\WebsiteSchemaBuilder;
use WorkEddy\Platform\Schema\Modules\WorkerVoice\WorkerVoiceSchemaBuilder;
use Doctrine\DBAL\Schema\Schema;

final class CanonicalSchemaBuilder
{
    /** @var array<string, ModuleSchemaBuilder> */
    private array $builders = [];

    /**
     * @param list<ModuleSchemaBuilder>|null $builders
     */
    public function __construct(?array $builders = null)
    {
        $builders ??= [
            new PlatformSchemaBuilder(),
            new TransportSchemaBuilder(),
            new AuditSchemaBuilder(),
            new IamSchemaBuilder(),
            new OrganizationSchemaBuilder(),
            new TaskSchemaBuilder(),
            new AssessmentSchemaBuilder(),
            new PrivacySchemaBuilder(),
            new CorrectiveActionSchemaBuilder(),
            new NotificationSchemaBuilder(),
            new WebsiteSchemaBuilder(),
            new StorageSchemaBuilder(),
            new BillingSchemaBuilder(),
            new ContentSchemaBuilder(),
            new PaymentSchemaBuilder(),
            new FinanceSchemaBuilder(),
            new ReportingSchemaBuilder(),
            new SupportTicketSchemaBuilder(),
            new SubscriptionSchemaBuilder(),
            new WorkerVoiceSchemaBuilder(),
            new ExportSchemaBuilder(),
        ];

        foreach ($builders as $builder) {
            $this->builders[$builder->module()] = $builder;
        }
    }

    public function buildAll(): Schema
    {
        $schema = new Schema();
        $this->buildInto($schema);

        return $schema;
    }

    public function buildInto(Schema $schema): void
    {
        foreach ($this->builders as $builder) {
            $builder->build($schema);
        }
    }

    /**
     * @return list<string>
     */
    public function tables(): array
    {
        $tables = [];
        foreach ($this->builders as $builder) {
            array_push($tables, ...$builder->tables());
        }

        return $tables;
    }
}
