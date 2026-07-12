<?php

declare(strict_types=1);

namespace WorkEddy\Modules\Billing\Application\UseCases;

use WorkEddy\Modules\Billing\Domain\Contracts\IInvoiceRepository;
use WorkEddy\Modules\Billing\Domain\Contracts\IQuotationRepository;
use WorkEddy\Modules\Organization\Domain\Contracts\IOrganizationRepository;
use WorkEddy\Modules\Storage\Domain\Contracts\IStorageService;
use WorkEddy\Modules\Storage\Application\DTOs\StoreUploadedFileRequest;
use WorkEddy\Platform\Settings\SettingsService;
use WorkEddy\Shared\Exceptions\NotFoundException;
use Dompdf\Dompdf;
use Dompdf\Options;

final class GeneratePdf
{
    public function __construct(
        private readonly IQuotationRepository $quotations,
        private readonly IInvoiceRepository $invoices,
        private readonly IStorageService $storage,
        private readonly SettingsService $settings,
        private readonly IOrganizationRepository $organizations,
    ) {}

    public function generateQuotationPdf(string $uuid): string
    {
        $quotation = $this->quotations->findByUuid($uuid);
        if (!$quotation) {
            throw new NotFoundException('Quotation not found');
        }

        $data = $this->enrichData($quotation->toArray());
        $html = $this->renderQuotationHtml($data);

        $pdfContent = $this->createPdf($html);
        $fileName = 'quotation_' . $quotation->quotationNumber . '.pdf';

        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        file_put_contents($tempPath, $pdfContent);

        $request = new StoreUploadedFileRequest(
            file: [
                'tmp_name' => $tempPath,
                'name' => $fileName,
                'type' => 'application/pdf',
                'size' => filesize($tempPath),
                'error' => UPLOAD_ERR_OK,
            ],
            ownerType: 'quotation',
            ownerUuid: $quotation->uuid,
            fieldName: 'pdf',
            visibility: 'private',
            actorId: null // We could pass actorId if we modify the use case signature
        );

        $storedFile = $this->storage->storeUploadedFile($request);
        unlink($tempPath);

        if (!$storedFile) {
            throw new \RuntimeException('Failed to store generated PDF.');
        }

        return $storedFile->uuid;
    }

    public function generateInvoicePdf(string $uuid): string
    {
        $invoice = $this->invoices->findByUuid($uuid);
        if (!$invoice) {
            throw new NotFoundException('Invoice not found');
        }

        $data = $this->enrichData($invoice->toArray());
        $html = $this->renderInvoiceHtml($data);

        $pdfContent = $this->createPdf($html);
        $fileName = 'invoice_' . $invoice->invoiceNumber . '.pdf';

        $tempPath = sys_get_temp_dir() . '/' . $fileName;
        file_put_contents($tempPath, $pdfContent);

        $request = new StoreUploadedFileRequest(
            file: [
                'tmp_name' => $tempPath,
                'name' => $fileName,
                'type' => 'application/pdf',
                'size' => filesize($tempPath),
                'error' => UPLOAD_ERR_OK,
            ],
            ownerType: 'invoice',
            ownerUuid: $invoice->uuid,
            fieldName: 'pdf',
            visibility: 'private',
            actorId: null
        );

        $storedFile = $this->storage->storeUploadedFile($request);
        unlink($tempPath);

        if (!$storedFile) {
            throw new \RuntimeException('Failed to store generated PDF.');
        }

        return $storedFile->uuid;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function enrichData(array $data): array
    {
        $data['org'] = [
            'name' => (string) $this->settings->get('billing.org_name', ''),
            'address' => (string) $this->settings->get('billing.org_address', ''),
            'phone' => (string) $this->settings->get('billing.org_phone', ''),
            'email' => (string) $this->settings->get('billing.org_email', ''),
            'tax_id' => (string) $this->settings->get('billing.org_tax_id', ''),
        ];

        $organizationId = (int) ($data['organization_id'] ?? 0);
        if ($organizationId > 0) {
            $organization = $this->organizations->findById($organizationId);
            if ($organization !== null) {
                $data['customer'] = [
                    'name' => $organization->getName(),
                    'email' => $organization->getContactEmail(),
                    'phone' => $organization->getPhone(),
                ];
            }
        }

        return $data;
    }

    private function createPdf(string $html): string
    {
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output() ?? '';
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderQuotationHtml(array $data): string
    {
        ob_start();
        require __DIR__ . '/../../Presentation/Templates/quotation.php';
        return (string) ob_get_clean();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function renderInvoiceHtml(array $data): string
    {
        ob_start();
        require __DIR__ . '/../../Presentation/Templates/invoice.php';
        return (string) ob_get_clean();
    }
}
