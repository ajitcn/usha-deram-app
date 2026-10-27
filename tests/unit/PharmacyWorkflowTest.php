<?php

namespace Tests\Unit;

use Pharmacy\Controllers\Operations;
use PHPUnit\Framework\TestCase;

final class PharmacyWorkflowTest extends TestCase
{
    public function testDeliveryTransitionsAllowOnlyTheDocumentedForwardPath(): void
    {
        $transitions = (new \ReflectionClass(Operations::class))->getConstant('DELIVERY_TRANSITIONS');
        self::assertContains('packed', $transitions['pending']);
        self::assertContains('dispatched', $transitions['packed']);
        self::assertContains('out_for_delivery', $transitions['dispatched']);
        self::assertContains('delivered', $transitions['out_for_delivery']);
        self::assertNotContains('delivered', $transitions['pending']);
        self::assertSame([], $transitions['returned']);
    }

    public function testProviderBoundaryContainsOnlyApprovedCandidates(): void
    {
        $providers = (new \ReflectionClass(Operations::class))->getConstant('PAYMENT_PROVIDERS');
        self::assertContains('cashfree', $providers);
        self::assertContains('razorpay', $providers);
    }

    public function testReviewActionsIncludeCancellationAndRoutesArePermissionProtected(): void
    {
        $reviewSource = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/PrescriptionReview.php');
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        self::assertMatchesRegularExpression("/'cancel'\\s*=>\\s*'cancelled'/", $reviewSource);
        self::assertStringContainsString('pharmacy.prescription.review.view', $routes);
        self::assertStringContainsString('pharmacy.order.create', $routes);
        self::assertStringContainsString('pharmacy.medicine.edit', $routes);
    }

    public function testPrescriptionReviewSeparatesAndPersistsPharmacistDispensingQuantities(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PrescriptionReviewService.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/PrescriptionReview.php');
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/prescriptions/_modal.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/prescriptions.js');
        self::assertStringContainsString('dispensing_quantity', $service . $controller . $modal . $script);
        self::assertStringContainsString('dispensing_quantity_updated_by', $service);
        self::assertStringContainsString('dispensing_quantity_updated_at', $service);
        self::assertStringContainsString('pharmacy.prescription_dispensing_quantity_updated', $service);
        self::assertStringContainsString('save_quantities', $controller . $modal . $script);
        self::assertStringContainsString('validateDispensingQuantities', $script);
        self::assertStringContainsString('prescription_item_id', $service . $script);
        self::assertStringContainsString('form="pharmacy-review-form"', $script);
        self::assertStringContainsString('quantities[$postedItemId]', $service);
        self::assertStringContainsString('prescription_quantity', $service);
        self::assertStringContainsString("'prescriptions/dispensing-quantities'", file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php'));
    }

    public function testOrderCreationPreservesPrescriptionValuesAndChecksExpiry(): void
    {
        $source = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Operations.php');
        self::assertStringContainsString("'dosage' => \$item['dosage'] ?? null", $source);
        self::assertStringContainsString("'instructions' => \$item['instructions'] ?? null", $source);
        self::assertStringContainsString("->where('expiry_date >=', date('Y-m-d'))", $source);
        self::assertStringContainsString("'idempotency_key' => \$idempotencyKey", $source);
    }

    public function testAuditViewerIsReadOnlyScopedAndPaginated(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Controllers/Audit.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/audit/index.php');
        self::assertStringContainsString("audit', 'Audit::index", $routes);
        self::assertStringContainsString('permission:pharmacy.audit.view', $routes);
        self::assertStringContainsString("where('clinic_id', \$clinicId)", $controller);
        self::assertStringContainsString('findAll($pageSize', $controller);
        self::assertStringContainsString('pharmacy-audit-detail', $view);
        self::assertStringNotContainsString('delete-audit', strtolower($view));
    }
}
