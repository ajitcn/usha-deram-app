<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PharmacyPaymentDateTimeTest extends TestCase
{
    public function testPaymentUsesSeparateDateAndTimeFieldsAcrossTheFullFlow(): void
    {
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/billing/_modal.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Billing.php');
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/BillingService.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/billing.js');

        self::assertIsString($modal);
        self::assertIsString($controller);
        self::assertIsString($service);
        self::assertIsString($script);
        self::assertStringContainsString('name="payment_date"', $modal);
        self::assertStringContainsString('name="payment_time"', $modal);
        self::assertStringContainsString("getPost('payment_date')", $controller);
        self::assertStringContainsString("getPost('payment_time')", $controller);
        self::assertStringContainsString('paymentTimestamp', $service);
        self::assertStringContainsString('cannot be in the future', $service);
        self::assertStringContainsString('paymentTimestampValid', $script);
    }
}
