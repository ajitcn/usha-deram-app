<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PharmacyInvoiceShareTest extends TestCase
{
    public function testInvoiceShareUsesHashedExpiringTokenAndProtectedPdfRoutes(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Billing.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');

        self::assertIsString($service);
        self::assertIsString($controller);
        self::assertIsString($routes);
        self::assertStringContainsString('random_bytes(32)', $service);
        self::assertStringContainsString("hash('sha256', " . '$token)', $service);
        self::assertStringContainsString("expires_at >=', date('Y-m-d H:i:s')", $service);
        self::assertStringContainsString('sharedInvoicePdf', $controller);
        self::assertStringContainsString('billing/invoice/shared/(:segment)/pdf', $routes);
        self::assertStringNotContainsString("base_url('pharmacy/billing/invoice/shared/' . " . '$orderId)', $controller);
    }

    public function testAllInvoiceHtmlSurfacesUseTheCanonicalInvoiceContentView(): void
    {
        $page = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/invoice.php');
        $content = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_invoice_content.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/billing.js');

        self::assertIsString($page);
        self::assertIsString($content);
        self::assertIsString($script);
        self::assertStringContainsString("pages/billing/_invoice_content", $page);
        self::assertStringContainsString('pharmacy-invoice-table', $content);
        self::assertStringContainsString('pharmacy-invoice-totals', $content);
        self::assertStringContainsString("invoiceUrl + '/preview'", $script);
    }
}
