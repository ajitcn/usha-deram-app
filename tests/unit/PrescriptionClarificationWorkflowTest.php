<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PrescriptionClarificationWorkflowTest extends TestCase
{
    public function testWorkflowHasExplicitRoutesAndPermissionFilters(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');

        self::assertIsString($routes);
        self::assertStringContainsString("prescription-clarifications', 'PrescriptionClarification::index'", $routes);
        self::assertStringContainsString("permission:prescription.view", $routes);
        self::assertStringContainsString("permission:prescription.edit", $routes);
    }

    public function testServiceDefinesSafeStatusesAndActions(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Services/PrescriptionClarificationService.php');

        self::assertIsString($service);
        foreach (['REQUESTED', 'DOCTOR_RESPONDED', 'PRESCRIPTION_UPDATED', 'CONFIRMED', 'REJECTED', 'CLOSED'] as $status) {
            self::assertStringContainsString('public const ' . $status, $service);
        }
        foreach (['request(', 'respond(', 'doctorList(', 'doctorFind('] as $method) {
            self::assertStringContainsString($method, $service);
        }
        self::assertStringContainsString('dedupe_key', $service);
        self::assertStringContainsString('transBegin()', $service);
        self::assertStringContainsString('service(\'audit\')->record', $service);
    }

    public function testDoctorAndPharmacyViewsExposeHumanReadableWorkflow(): void
    {
        $doctorView = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescription_clarifications.php');
        $pharmacyModal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/prescriptions/_modal.php');

        self::assertIsString($doctorView);
        self::assertIsString($pharmacyModal);
        self::assertStringContainsString('Review Clarification', $doctorView);
        self::assertStringContainsString('Prescription changes', $doctorView);
        self::assertStringContainsString('Clarification history', $pharmacyModal);
        self::assertStringContainsString('Request clarification', $pharmacyModal);
    }
}
