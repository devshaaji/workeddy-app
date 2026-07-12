<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Presentation;

use WorkEddy\Modules\Billing\Application\UseCases\AcceptQuotation;
use WorkEddy\Modules\Billing\Application\UseCases\GenerateInvoice;
use WorkEddy\Modules\Billing\Application\UseCases\GenerateQuotation;
use WorkEddy\Modules\Billing\Application\UseCases\GeneratePdf;
use WorkEddy\Modules\Billing\Domain\Contracts\IInvoiceRepository;
use WorkEddy\Modules\Billing\Domain\Contracts\IQuotationRepository;
use WorkEddy\Modules\Billing\Domain\Entities\InvoiceStatus;
use WorkEddy\Modules\Billing\Domain\Entities\QuotationStatus;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Platform\Http\Request;
use WorkEddy\Platform\Http\Response;
use WorkEddy\Platform\Session\ISessionService;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Exceptions\ForbiddenException;
use WorkEddy\Platform\Clock\IClock;

final class BillingApiController
{
    public function __construct(
        private readonly IQuotationRepository $quotations,
        private readonly IInvoiceRepository $invoices,
        private readonly IClock $clock,
        private readonly GenerateQuotation $generateQuotation,
        private readonly AcceptQuotation $acceptQuotation,
        private readonly GenerateInvoice $generateInvoice,
        private readonly GeneratePdf $generatePdf,
        private readonly ISessionService $session,
        private readonly IStorageService $storage,
        private readonly SettingsService $settings,
    ) {}

    public function listQuotations(Request $request): Response
    {
        $filters = array_merge($request->query, $request->body, $request->json);

        $quotations = $this->quotations->list([
            'organization_id' => $filters['organization_id'] ?? null,
            'lead_id' => $filters['lead_id'] ?? null,
            'status' => $filters['status'] ?? null,
        ]);

        $data = array_map(static fn($q) => $q->toArray(), $quotations);

        return Response::success($data);
    }

    public function generateQuotation(Request $request): Response
    {
        $actorId = $this->userId();
        $body = array_merge($request->query, $request->body, $request->json);

        $organizationId = (int) ($body['organization_id'] ?? 0);
        $leadId = isset($body['lead_id']) ? (int) $body['lead_id'] : null;
        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        $currency = (string) ($body['currency'] ?? 'USD');
        $daysUntilExpiry = isset($body['days_until_expiry']) ? (int) $body['days_until_expiry'] : 30;

        $quotation = $this->generateQuotation->execute($organizationId, $leadId, $items, $currency, $daysUntilExpiry, $actorId);

        return Response::success($quotation->toArray(), 'Quotation generated successfully', 201);
    }

    public function viewQuotation(Request $request): Response
    {
        $quotation = $this->quotations->findByUuid((string) $request->routeParam('uuid'));
        if ($quotation === null) {
            return Response::error('Quotation not found', 404);
        }

        return Response::success($quotation->toArray());
    }

    public function acceptQuotation(Request $request): Response
    {
        $actorId = $this->userId();
        $uuid = $request->routeParam('uuid');

        $quotation = $this->acceptQuotation->execute($uuid, $actorId);

        return Response::success($quotation->toArray(), 'Quotation accepted');
    }

    public function updateQuotationStatus(Request $request): Response
    {
        $body = array_merge($request->body, $request->json);
        $status = QuotationStatus::tryFrom((string) ($body['status'] ?? ''));
        if ($status === null) {
            return Response::error('Invalid quotation status', 422, ['status' => 'Choose a valid quotation status.']);
        }

        $quotation = $this->quotations->findByUuid((string) $request->routeParam('uuid'));
        if ($quotation === null) {
            return Response::error('Quotation not found', 404);
        }

        $updated = $this->quotations->update($quotation->id, [
            'status' => $status,
            'updated_at' => $this->clock->now(),
        ]);

        return Response::success($updated->toArray(), 'Quotation status updated');
    }

    public function bulkQuotationStatus(Request $request): Response
    {
        $body = array_merge($request->body, $request->json);
        $status = QuotationStatus::tryFrom((string) ($body['status'] ?? ''));
        if ($status === null) {
            return Response::error('Invalid quotation status', 422, ['status' => 'Choose a valid quotation status.']);
        }

        $updated = 0;
        foreach ($this->uuidList($body) as $uuid) {
            $quotation = $this->quotations->findByUuid($uuid);
            if ($quotation === null) {
                continue;
            }

            $this->quotations->update($quotation->id, [
                'status' => $status,
                'updated_at' => $this->clock->now(),
            ]);
            $updated++;
        }

        return Response::success(['updated' => $updated], 'Quotation statuses updated');
    }

    public function archiveQuotation(Request $request): Response
    {
        $quotation = $this->quotations->findByUuid((string) $request->routeParam('uuid'));
        if ($quotation === null) {
            return Response::error('Quotation not found', 404);
        }

        $this->quotations->archive($quotation);

        return Response::success(['archived' => 1], 'Quotation archived');
    }

    public function bulkArchiveQuotations(Request $request): Response
    {
        $body = array_merge($request->body, $request->json);
        $archived = 0;
        foreach ($this->uuidList($body) as $uuid) {
            $quotation = $this->quotations->findByUuid($uuid);
            if ($quotation === null) {
                continue;
            }

            $this->quotations->archive($quotation);
            $archived++;
        }

        return Response::success(['archived' => $archived], 'Quotations archived');
    }

    public function downloadQuotationPdf(Request $request): Response
    {
        $uuid = $request->routeParam('uuid');

        $fileUuid = $this->generatePdf->generateQuotationPdf($uuid);
        $file = $this->storage->findByUuid($fileUuid);
        $content = $this->storage->read($fileUuid);

        return Response::stream(
            function () use ($content) {
                echo $content;
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file->originalName . '"',
                'Content-Length' => (string) strlen($content),
            ]
        );
    }

    public function listInvoices(Request $request): Response
    {
        $filters = array_merge($request->query, $request->body, $request->json);

        $invoices = $this->invoices->list([
            'organization_id' => $filters['organization_id'] ?? null,
            'quotation_id' => $filters['quotation_id'] ?? null,
            'status' => $filters['status'] ?? null,
        ]);

        $data = array_map(static fn($i) => $i->toArray(), $invoices);

        return Response::success($data);
    }

    public function generateInvoice(Request $request): Response
    {
        $actorId = $this->userId();
        $body = array_merge($request->query, $request->body, $request->json);

        $organizationId = (int) ($body['organization_id'] ?? 0);
        $quotationUuid = isset($body['quotation_uuid']) ? (string) $body['quotation_uuid'] : null;
        $items = is_array($body['items'] ?? null) ? $body['items'] : [];
        $currency = (string) ($body['currency'] ?? 'USD');
        $daysUntilDue = isset($body['days_until_due']) ? (int) $body['days_until_due'] : 14;

        $invoice = $this->generateInvoice->execute($organizationId, $quotationUuid, $items, $currency, $daysUntilDue, $actorId);

        return Response::success($invoice->toArray(), 'Invoice generated successfully', 201);
    }

    public function viewInvoice(Request $request): Response
    {
        $invoice = $this->invoices->findByUuid((string) $request->routeParam('uuid'));
        if ($invoice === null) {
            return Response::error('Invoice not found', 404);
        }

        return Response::success($invoice->toArray());
    }

    public function updateInvoiceStatus(Request $request): Response
    {
        $body = array_merge($request->body, $request->json);
        $status = InvoiceStatus::tryFrom((string) ($body['status'] ?? ''));
        if ($status === null) {
            return Response::error('Invalid invoice status', 422, ['status' => 'Choose a valid invoice status.']);
        }

        $invoice = $this->invoices->findByUuid((string) $request->routeParam('uuid'));
        if ($invoice === null) {
            return Response::error('Invoice not found', 404);
        }

        $data = [
            'status' => $status,
            'updated_at' => $this->clock->now(),
        ];
        if ($status === InvoiceStatus::PAID) {
            $data['amount_paid'] = $invoice->total;
        }

        $updated = $this->invoices->update($invoice->id, $data);

        return Response::success($updated->toArray(), 'Invoice status updated');
    }

    public function bulkInvoiceStatus(Request $request): Response
    {
        $body = array_merge($request->body, $request->json);
        $status = InvoiceStatus::tryFrom((string) ($body['status'] ?? ''));
        if ($status === null) {
            return Response::error('Invalid invoice status', 422, ['status' => 'Choose a valid invoice status.']);
        }

        $updated = 0;
        foreach ($this->uuidList($body) as $uuid) {
            $invoice = $this->invoices->findByUuid($uuid);
            if ($invoice === null) {
                continue;
            }

            $data = [
                'status' => $status,
                'updated_at' => $this->clock->now(),
            ];
            if ($status === InvoiceStatus::PAID) {
                $data['amount_paid'] = $invoice->total;
            }
            $this->invoices->update($invoice->id, $data);
            $updated++;
        }

        return Response::success(['updated' => $updated], 'Invoice statuses updated');
    }

    public function archiveInvoice(Request $request): Response
    {
        $invoice = $this->invoices->findByUuid((string) $request->routeParam('uuid'));
        if ($invoice === null) {
            return Response::error('Invoice not found', 404);
        }

        $this->invoices->archive($invoice);

        return Response::success(['archived' => 1], 'Invoice archived');
    }

    public function bulkArchiveInvoices(Request $request): Response
    {
        $body = array_merge($request->body, $request->json);
        $archived = 0;
        foreach ($this->uuidList($body) as $uuid) {
            $invoice = $this->invoices->findByUuid($uuid);
            if ($invoice === null) {
                continue;
            }

            $this->invoices->archive($invoice);
            $archived++;
        }

        return Response::success(['archived' => $archived], 'Invoices archived');
    }

    public function downloadInvoicePdf(Request $request): Response
    {
        $uuid = $request->routeParam('uuid');

        $fileUuid = $this->generatePdf->generateInvoicePdf($uuid);
        $file = $this->storage->findByUuid($fileUuid);
        $content = $this->storage->read($fileUuid);

        return Response::stream(
            function () use ($content) {
                echo $content;
            },
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $file->originalName . '"',
                'Content-Length' => (string) strlen($content),
            ]
        );
    }

    public function getSettings(Request $request): Response
    {
        return Response::success($this->settings->getAllForModule('billing'));
    }

    public function updateSettings(Request $request): Response
    {
        $body = array_merge($request->body, $request->json);
        $values = [];
        foreach (['default_currency', 'default_tax_rate', 'quotation_expiry_days', 'invoice_due_days', 'org_name', 'org_address', 'org_phone', 'org_email', 'org_tax_id'] as $key) {
            if (array_key_exists($key, $body)) {
                $values[$key] = $body[$key];
            }
        }

        $this->settings->setMany('billing', $values, (string) $this->userId());

        return Response::success($this->settings->getAllForModule('billing'), 'Billing settings updated');
    }

    private function userId(): int
    {
        $ctx = $this->session->getUserContext();
        if (!$ctx) {
            throw new ForbiddenException('Unauthenticated');
        }

        return (int) $ctx->userId;
    }

    /**
     * @param array<string, mixed> $body
     * @return array<int, string>
     */
    private function uuidList(array $body): array
    {
        $raw = $body['uuids'] ?? $body['ids'] ?? [];
        if (!is_array($raw)) {
            return [];
        }

        return array_values(array_filter(array_map(static fn($uuid): string => trim((string) $uuid), $raw)));
    }
}
