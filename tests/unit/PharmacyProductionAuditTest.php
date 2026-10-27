<?php

use CodeIgniter\Test\CIUnitTestCase;

final class PharmacyProductionAuditTest extends CIUnitTestCase
{
    public function testPharmacyPaymentLedgerAndInvoiceRoutesAreProtected(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        self::assertStringContainsString("'billing/payment', 'Billing::payment'", $routes);
        self::assertStringContainsString("permission:pharmacy.billing.payment.update", $routes);
        self::assertStringContainsString("'billing/invoice/(:num)/pdf', 'Billing::invoicePdf/$1'", $routes);
        self::assertStringContainsString("permission:pharmacy.billing.view", $routes);
    }

    public function testPaymentLedgerPreservesIdempotencyAndServerValidation(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-25-120000_CreatePharmacyPaymentTransactions.php');
        foreach (['recordPayment', 'idempotency_key', 'Payment exceeds the remaining balance.', 'pharmacy.payment_created', 'transactionTotals'] as $text) {
            self::assertStringContainsString($text, $service);
        }
        foreach (['reference_number', 'recorded_by', 'paid_at', 'idempotency_key', 'addUniqueKey'] as $field) {
            self::assertStringContainsString($field, $migration);
        }
    }

    public function testOrderKpisUseScopedDatabaseCountsAndVisiblePackagingLabel(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/OrderFulfilmentService.php');
        $toolbar = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/orders/_toolbar.php');
        $billingList = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/list.php');
        self::assertStringContainsString("where('clinic_id', \$clinicId)", $service);
        self::assertStringContainsString("'orderKpis'", $service);
        self::assertStringContainsString('Order and payment summary', $toolbar);
        self::assertStringContainsString('Packaging charges:', $billingList);
        self::assertStringNotContainsString('Tax:', $billingList);
    }
}
