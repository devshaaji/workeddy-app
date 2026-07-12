<?php

declare(strict_types=1);

use WorkEddy\Modules\Billing\Authorization\BillingPermissions;
use WorkEddy\Modules\Billing\Presentation\BillingApiController;
use WorkEddy\Modules\Billing\Presentation\BillingPageController;
use WorkEddy\Platform\Http\RouteRegistrar;

return function (RouteRegistrar $routes): void {
    $uuidPattern = '[0-9A-Fa-f]{8}-[0-9A-Fa-f]{4}-[1-5][0-9A-Fa-f]{3}-[89ABab][0-9A-Fa-f]{3}-[0-9A-Fa-f]{12}';

    $routes->group('/billing', function (RouteRegistrar $page) use ($uuidPattern): void {
        $page->add('GET', '/quotations', [BillingPageController::class, 'quotations'], ['permission:' . BillingPermissions::VIEW_BILLING]);
        $page->add('GET', '/quotations/new', [BillingPageController::class, 'createQuotation'], ['permission:' . BillingPermissions::MANAGE_QUOTATIONS]);
        $page->add('GET', '/quotations/{uuid:' . $uuidPattern . '}', [BillingPageController::class, 'quotationDetail'], ['permission:' . BillingPermissions::VIEW_BILLING]);
        $page->add('GET', '/invoices', [BillingPageController::class, 'invoices'], ['permission:' . BillingPermissions::VIEW_BILLING]);
        $page->add('GET', '/invoices/new', [BillingPageController::class, 'createInvoice'], ['permission:' . BillingPermissions::MANAGE_INVOICES]);
        $page->add('GET', '/invoices/{uuid:' . $uuidPattern . '}', [BillingPageController::class, 'invoiceDetail'], ['permission:' . BillingPermissions::VIEW_BILLING]);
        $page->add('GET', '/settings', [BillingPageController::class, 'settings'], ['permission:' . BillingPermissions::VIEW_BILLING]);
    });

    $routes->group('/api/v1/billing', function (RouteRegistrar $api) use ($uuidPattern): void {
        $api->add('GET', '/quotations', [BillingApiController::class, 'listQuotations'], ['permission:' . BillingPermissions::VIEW_BILLING]);
        $api->add('POST', '/quotations', [BillingApiController::class, 'generateQuotation'], ['permission:' . BillingPermissions::MANAGE_QUOTATIONS]);
        $api->add('GET', '/quotations/{uuid:' . $uuidPattern . '}', [BillingApiController::class, 'viewQuotation'], ['permission:' . BillingPermissions::VIEW_BILLING]);
        $api->add('PATCH', '/quotations/{uuid:' . $uuidPattern . '}/status', [BillingApiController::class, 'updateQuotationStatus'], ['permission:' . BillingPermissions::MANAGE_QUOTATIONS]);
        $api->add('POST', '/quotations/bulk-status', [BillingApiController::class, 'bulkQuotationStatus'], ['permission:' . BillingPermissions::MANAGE_QUOTATIONS]);
        $api->add('DELETE', '/quotations/{uuid:' . $uuidPattern . '}', [BillingApiController::class, 'archiveQuotation'], ['permission:' . BillingPermissions::MANAGE_QUOTATIONS]);
        $api->add('POST', '/quotations/bulk-archive', [BillingApiController::class, 'bulkArchiveQuotations'], ['permission:' . BillingPermissions::MANAGE_QUOTATIONS]);
        $api->add('POST', '/quotations/{uuid:' . $uuidPattern . '}/accept', [BillingApiController::class, 'acceptQuotation'], ['permission:' . BillingPermissions::MANAGE_QUOTATIONS]);
        $api->add('GET', '/quotations/{uuid:' . $uuidPattern . '}/pdf', [BillingApiController::class, 'downloadQuotationPdf'], ['permission:' . BillingPermissions::VIEW_BILLING]);

        $api->add('GET', '/invoices', [BillingApiController::class, 'listInvoices'], ['permission:' . BillingPermissions::VIEW_BILLING]);
        $api->add('POST', '/invoices', [BillingApiController::class, 'generateInvoice'], ['permission:' . BillingPermissions::MANAGE_INVOICES]);
        $api->add('GET', '/invoices/{uuid:' . $uuidPattern . '}', [BillingApiController::class, 'viewInvoice'], ['permission:' . BillingPermissions::VIEW_BILLING]);
        $api->add('PATCH', '/invoices/{uuid:' . $uuidPattern . '}/status', [BillingApiController::class, 'updateInvoiceStatus'], ['permission:' . BillingPermissions::MANAGE_INVOICES]);
        $api->add('POST', '/invoices/bulk-status', [BillingApiController::class, 'bulkInvoiceStatus'], ['permission:' . BillingPermissions::MANAGE_INVOICES]);
        $api->add('DELETE', '/invoices/{uuid:' . $uuidPattern . '}', [BillingApiController::class, 'archiveInvoice'], ['permission:' . BillingPermissions::MANAGE_INVOICES]);
        $api->add('POST', '/invoices/bulk-archive', [BillingApiController::class, 'bulkArchiveInvoices'], ['permission:' . BillingPermissions::MANAGE_INVOICES]);
        $api->add('GET', '/invoices/{uuid:' . $uuidPattern . '}/pdf', [BillingApiController::class, 'downloadInvoicePdf'], ['permission:' . BillingPermissions::VIEW_BILLING]);
        $api->add('GET', '/settings', [BillingApiController::class, 'getSettings'], ['permission:' . BillingPermissions::VIEW_BILLING]);
        $api->add('PUT', '/settings', [BillingApiController::class, 'updateSettings'], ['permission:' . BillingPermissions::MANAGE_INVOICES]);
    });
};
