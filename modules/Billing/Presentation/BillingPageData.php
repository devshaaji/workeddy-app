<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Presentation;

use WorkEddy\Modules\Billing\Domain\Contracts\IInvoiceRepository;
use WorkEddy\Modules\Billing\Domain\Contracts\IQuotationRepository;
use WorkEddy\Modules\Billing\Domain\Entities\InvoiceStatus;
use WorkEddy\Modules\Billing\Domain\Entities\QuotationStatus;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Platform\Session\UserContext;
use WorkEddy\Platform\Settings\SettingsService;

final class BillingPageData
{
    public function __construct(
        private readonly IQuotationRepository $quotations,
        private readonly IInvoiceRepository $invoices,
        private readonly SettingsService $settings,
        private readonly IOrganizationRepository $organizations,
    ) {}

    public function common(UserContext $ctx): array
    {
        return [
            'user' => [
                'id' => $ctx->userId,
                'permissions' => $ctx->permissions,
            ],
            'moduleSettings' => [
                'defaultCurrency' => $this->settings->get('billing.default_currency'),
                'defaultTaxRate' => $this->settings->get('billing.default_tax_rate'),
                'quotationExpiryDays' => $this->settings->get('billing.quotation_expiry_days'),
                'invoiceDueDays' => $this->settings->get('billing.invoice_due_days'),
            ],
            'quotationStatuses' => $this->quotationStatuses(),
            'invoiceStatuses' => $this->invoiceStatuses(),
            'organizations' => array_map(
                static fn($organization): array => [
                    'id' => $organization->getId(),
                    'uuid' => $organization->getUuid(),
                    'name' => $organization->getName(),
                ],
                $this->organizations->findAll(200, 0),
            ),
        ];
    }

    public function quotations(UserContext $ctx): array
    {
        $activeQuotations = $this->quotations->list();

        return [
            'pageTitle' => 'Quotations',
            'quotations' => array_map(fn($q) => $q->toArray(), $activeQuotations)
        ];
    }

    public function createQuotation(UserContext $ctx): array
    {
        return [
            'pageTitle' => 'Create Quotation',
            'quotation' => null,
            'organizations' => array_map(
                static fn($organization): array => [
                    'id' => $organization->getId(),
                    'uuid' => $organization->getUuid(),
                    'name' => $organization->getName(),
                ],
                $this->organizations->findAll(200, 0),
            ),
        ];
    }

    public function quotationDetail(UserContext $ctx, string $uuid): array
    {
        $quotation = $this->quotations->findByUuid($uuid);

        return [
            'pageTitle' => 'Quotation Detail',
            'quotation' => $quotation?->toArray(),
        ];
    }

    public function invoices(UserContext $ctx): array
    {
        $activeInvoices = $this->invoices->list();

        return [
            'pageTitle' => 'Invoices',
            'invoices' => array_map(fn($i) => $i->toArray(), $activeInvoices)
        ];
    }

    public function createInvoice(UserContext $ctx): array
    {
        return [
            'pageTitle' => 'Create Invoice',
            'invoice' => null,
            'organizations' => array_map(
                static fn($organization): array => [
                    'id' => $organization->getId(),
                    'uuid' => $organization->getUuid(),
                    'name' => $organization->getName(),
                ],
                $this->organizations->findAll(200, 0),
            ),
            'quotations' => array_map(fn($q) => $q->toArray(), $this->quotations->list(['status' => QuotationStatus::ACCEPTED->value])),
        ];
    }

    public function invoiceDetail(UserContext $ctx, string $uuid): array
    {
        $invoice = $this->invoices->findByUuid($uuid);

        return [
            'pageTitle' => 'Invoice Detail',
            'invoice' => $invoice?->toArray(),
        ];
    }

    public function settings(UserContext $ctx): array
    {
        return [
            'pageTitle' => 'Billing Settings',
            'settings' => $this->settings->getAllForModule('billing'),
        ];
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function quotationStatuses(): array
    {
        return array_map(static fn(QuotationStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ], QuotationStatus::cases());
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    private function invoiceStatuses(): array
    {
        return array_map(static fn(InvoiceStatus $status): array => [
            'value' => $status->value,
            'label' => $status->label(),
        ], InvoiceStatus::cases());
    }
}
