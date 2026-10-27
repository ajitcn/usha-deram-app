<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

final class PrescriptionDurationPresentationTest extends TestCase
{
    public function testDoctorClinicalPastVisitCombinesDurationAndUnitPerMedicine(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');

        self::assertIsString($view);
        self::assertStringContainsString('clinical-past-visit-prescription-summary', $view);
        self::assertStringContainsString('Duration Days:', $view);
        self::assertStringNotContainsString('<th scope="col">Duration Days</th>', $view);
    }

    public function testDoctorClinicalPrescriptionFormDoesNotRenderTheSummaryBlock(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');

        self::assertIsString($view);
        self::assertStringContainsString('name="medicine_duration"', $view);
        self::assertStringContainsString('clinical-prescription-table', $view);
        self::assertStringNotContainsString('clinical-prescription-summary mt-2', $view);
    }

    public function testDoctorPrescriptionDetailsShowOnePrescriptionDurationSummary(): void
    {
        $view = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/prescriptions.php');

        self::assertIsString($view);
        self::assertStringContainsString('aria-label="Duration Days"', $view);
        self::assertStringContainsString('<b>Duration Days:</b>', $view);
        self::assertStringNotContainsString('aria-label="Prescription Summary"', $view);
        self::assertStringContainsString('data-label="Duration"', $view);
        self::assertStringContainsString('$prescriptionDuration', $view);
        self::assertStringContainsString('$item[\'medicine_duration\']', $view);
    }

    public function testPatientPharmacyAndPdfRepresentDuration(): void
    {
        $patient = file_get_contents(ROOTPATH . 'app/Modules/Patient/Views/pages/prescriptions.php');
        $pharmacy = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/prescriptions.js');
        $pdf = file_get_contents(ROOTPATH . 'app/Services/Common/PrescriptionPdfService.php');

        self::assertIsString($patient);
        self::assertIsString($pharmacy);
        self::assertIsString($pdf);
        self::assertStringContainsString('patient-prescription-summary', $patient);
        self::assertStringContainsString('item.medicine_duration', $patient);
        self::assertStringContainsString('record.medicine_duration', $pharmacy);
        self::assertStringContainsString('PRESCRIPTION SUMMARY', $pdf);
    }

    public function testPharmacyPrescriptionDetailsExposeAndRenderPrescriptionDuration(): void
    {
        $service = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PrescriptionReviewService.php');
        $modal = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Views/pages/prescriptions/_modal.php');
        $script = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/prescriptions.js');

        self::assertIsString($service);
        self::assertIsString($modal);
        self::assertIsString($script);
        self::assertStringContainsString("'duration' =>", $service);
        self::assertStringContainsString('duration_unit', $service);
        self::assertStringContainsString('id="review-medicines-heading"', $modal);
        self::assertStringContainsString('review-duration-summary', $modal);
        self::assertStringContainsString('durationSummary', $script);
        self::assertStringContainsString('const durationValue = item.duration', $script);
        self::assertStringContainsString("durationText = durationValue === '' ? 'Not specified'", $script);
        self::assertStringNotContainsString('record.medicine_duration : item.duration', $script);
    }

    public function testMedicineDurationAliasReachesDoctorAndPharmacyDetails(): void
    {
        $doctorRecords = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Records.php');
        $doctorClinical = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Views/pages/clinical.php');
        $pharmacy = file_get_contents(ROOTPATH . 'app/Modules/Pharmacy/Services/PrescriptionReviewService.php');
        $pharmacyScript = file_get_contents(ROOTPATH . 'public/assets/js/pharmacy/prescriptions.js');

        self::assertIsString($doctorRecords);
        self::assertIsString($doctorClinical);
        self::assertIsString($pharmacy);
        self::assertIsString($pharmacyScript);
        self::assertStringNotContainsString('duration AS medicine_duration', $doctorRecords);
        self::assertStringContainsString("'medicine_duration' =>", $doctorClinical);
        self::assertStringContainsString('$item[\'duration\']', $doctorClinical);
        self::assertStringContainsString('\'duration\' =>', $pharmacy);
        self::assertStringContainsString('record.medicine_duration', $pharmacyScript);
    }

    public function testPharmacyDurationFormattingUsesSingularForOneDay(): void
    {
        $service = (new \ReflectionClass(\Pharmacy\Services\PrescriptionReviewService::class))->newInstanceWithoutConstructor();
        $method = (new \ReflectionClass($service))->getMethod('formatDuration');
        $method->setAccessible(true);

        self::assertSame('1 day', $method->invoke($service, 1, 'days'));
        self::assertSame('5 days', $method->invoke($service, 5, 'days'));
        self::assertSame('As directed', $method->invoke($service, 'As directed', null));
        self::assertSame('Not recorded', $method->invoke($service, null, 'days'));
    }

    public function testPrescriptionAndMedicineDurationsRemainSeparateInDoctorSaveFlow(): void
    {
        $controller = file_get_contents(ROOTPATH . 'app/Modules/Doctor/Controllers/Clinical.php');
        $model = file_get_contents(ROOTPATH . 'app/Modules/Admin/Models/PrescriptionModel.php');

        self::assertIsString($controller);
        self::assertIsString($model);
        self::assertStringContainsString("'medicine_duration' => \$medicineDuration", $controller);
        self::assertStringContainsString("'duration' => \$duration === null ? null : (int) \$duration['code']", $controller);
        self::assertStringContainsString("protected \$allowedFields = ['prescription_number', 'consultation_id', 'patient_id', 'doctor_id', 'clinic_id', 'prescription_date', 'medicine_duration'", $model);
        self::assertStringNotContainsString('defaultDuration', $controller);
    }
}
