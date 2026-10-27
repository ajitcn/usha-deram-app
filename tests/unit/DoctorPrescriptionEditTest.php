<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class DoctorPrescriptionEditTest extends TestCase
{
    public function testDoctorPrescriptionEditRouteAndActionAreProtected(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Config/Routes.php');
        $actions = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions_actions.php');

        self::assertStringContainsString("prescriptions/(:num)/edit', 'Records::editPrescription/$1'", $routes);
        self::assertStringContainsString("prescriptions/(:num)/edit', 'Records::updatePrescription/$1'", $routes);
        self::assertStringContainsString("clinical/update-completed', 'Clinical::updateCompletedConsultation'", $routes);
        self::assertStringContainsString("permission:prescription.edit", $routes);
        self::assertStringContainsString('Edit Consultation &amp; Prescription', $actions);
        self::assertStringContainsString("doctor/clinical?appointment_id=", $actions);
        self::assertStringNotContainsString('doctor/prescriptions/\' . (int) $prescriptionId . \'/edit', $actions);
    }

    public function testCompletedConsultationEditModeKeepsMedicineAddControlsEnabled(): void
    {
        $clinical = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');

        self::assertStringContainsString('clinical-add-medicine-trigger', $clinical);
        self::assertStringContainsString('|| $clinicalReadOnly ? \'disabled\' : \'\'', $clinical);
        self::assertStringNotContainsString('|| $clinicalCompleted ? \'disabled\' : \'\'', $clinical);
        self::assertStringContainsString("$(document).on('click', '.clinical-add-medicine-trigger'", $clinical);
    }

    public function testEditUsesPrescriptionAndItemDurationsSeparatelyAndLocksFinalStates(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescription_edit.php');

        self::assertStringContainsString("'medicine_duration' => \$medicineDuration", $controller);
        self::assertStringContainsString("'duration' => isset(\$values['duration'])", $controller);
        self::assertStringContainsString("['draft', 'submitted', 'clarification_requested']", $controller);
        self::assertStringContainsString("medicine_orders", $controller);
        self::assertStringContainsString("FOR UPDATE", $controller);
        self::assertStringContainsString("old_values", $controller);
        self::assertStringContainsString("new_values", $controller);
        self::assertStringContainsString("name=\"medicine_duration\"", $view);
        self::assertStringContainsString('name="items[<?= (int) $index ?>][<?= esc($field) ?>]"', $view);
        self::assertStringContainsString("['duration', 'Duration', 'duration']", $view);
        self::assertStringContainsString('dispensing_quantity', $view);
    }

    public function testCompletedOpdPageLinksToPrescriptionEditorAndDisablesConsultationSave(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');

        self::assertStringContainsString('DoctorPrescriptionEditPolicy', $controller);
        self::assertStringContainsString('consultation_updated_at', $controller);
        self::assertStringContainsString('Edit Consultation &amp; Prescription', $view);
        self::assertStringContainsString('$clinicalCompleted', $view);
        self::assertStringContainsString('$clinicalReadOnly', $view);
    }

    public function testCompletedConsultationUsesSamePageEditModeAndDedicatedEndpoint(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-24-190000_AddCompletedAtToConsultations.php');

        self::assertStringContainsString('saveWorkspaceInternal(true)', $controller);
        self::assertStringContainsString('FOR UPDATE', $controller);
        self::assertStringContainsString('old_values', $controller);
        self::assertStringContainsString('update-completed', $view);
        self::assertStringContainsString('Edit Consultation &amp; Prescription', $view);
        self::assertStringContainsString('Save Changes', $view);
        self::assertStringContainsString('completed_at', $migration);
    }

    public function testPharmacyNotificationUsesCanonicalRoleAndPermissionColumns(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $rolesMigration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-07-27-132418_CreateRoles.php');

        self::assertStringContainsString("roles.role_code', 'PHARMACY_ASSISTANT'", $controller);
        self::assertStringContainsString("permissions.permission_key', 'pharmacy.prescription.review.view'", $controller);
        self::assertStringContainsString("users.deleted_at IS NULL", $controller);
        self::assertStringContainsString("roles.deleted_at IS NULL", $controller);
        self::assertStringNotContainsString('roles.name', $controller);
        self::assertStringContainsString("'role_name'", $rolesMigration);
        self::assertStringContainsString("'role_code'", $rolesMigration);
    }

    public function testPrescriptionListBuildsSamePageConsultationEditEligibility(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions.php');

        self::assertStringContainsString('appointment_id, status, completed_at, updated_at', $controller);
        self::assertStringContainsString('can_edit_consultation', $controller);
        self::assertStringContainsString('prescriptionEditPolicy->evaluate', $controller);
        self::assertStringContainsString('appointmentId', $view);
        self::assertStringContainsString('canEditConsultation', $view);
    }
}
