<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use App\Services\PrescriptionClarificationService;
use App\Services\ConsultationFeePaymentService;
use CodeIgniter\HTTP\RedirectResponse;

class Dashboard extends BaseController
{
    public function index(): string|RedirectResponse
    {
        $sessionUser = session('user') ?? [];
        $userId = (int) ($sessionUser['id'] ?? 0);

        if ($userId <= 0) {
            return redirect()->to(base_url('system/login'));
        }

        if (!in_array('Doctor', session('roles') ?? [], true)) {
            return redirect()->to(base_url('permission-denied'));
        }
        $user = (new User())->select('id, doctor_id, status')->where('id', $userId)->where('status', 1)->first();
        $doctorId = (int) ($user['doctor_id'] ?? 0);

        if ($doctorId <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $doctor = (new DoctorModel())->select('id, clinic_id, first_name, last_name, doctor_code')->where('id', $doctorId)->where('status', 1)->first();

        if ($doctor === null || (int) $doctor['clinic_id'] <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $clinicId = (int) $doctor['clinic_id'];
        $today = date('Y-m-d');
        $db = db_connect();
        $appointments = $db->table('appointments')->select(['appointments.id', 'appointments.appointment_number', 'appointments.appointment_date', 'appointments.appointment_time', 'appointments.appointment_type', 'appointments.status', 'patients.registration_number', 'patients.first_name AS patient_first_name', 'patients.middle_name AS patient_middle_name', 'patients.last_name AS patient_last_name', 'patients.mobile_number AS patient_mobile_number', 'patients.mobile_normalized AS patient_mobile_normalized', 'patients.country_code AS patient_legacy_country_code', 'telephone_country_codes.normalized_code AS patient_telephone_country_code'])->join('patients', 'patients.id = appointments.patient_id')->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')->where('appointments.doctor_id', $doctorId)->where('appointments.clinic_id', $clinicId)->where('appointments.appointment_date >=', $today)->orderBy('appointments.appointment_date', 'ASC')->orderBy('appointments.appointment_time', 'ASC')->limit(25)->get()->getResultArray();
        $feeService = new ConsultationFeePaymentService();
        $appointments = array_map(static function (array $appointment) use ($feeService, $clinicId, $doctorId): array { $appointment['fee_summary'] = $feeService->summary((int) $appointment['id'], $clinicId, $doctorId); return $appointment; }, $appointments);
        $appointmentCount = $db->table('appointments')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('appointment_date', $today)->countAllResults();
        $consultationCount = $db->table('consultations')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->whereIn('status', ['draft', 'in_progress'])->countAllResults();
        $completedConsultationCount = $db->table('consultations')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('status', 'completed')->countAllResults();
        $prescriptionCount = $db->table('prescriptions')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->countAllResults();
        $prescriptionReviewCount = $db->table('prescriptions')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('status', 'submitted')->countAllResults();
        $prescriptionClarificationCount = $db->table('prescription_clarifications')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('status', PrescriptionClarificationService::REQUESTED)->countAllResults();
        $approvedPrescriptionCount = $db->table('prescriptions')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('status', 'approved')->countAllResults();
        $patientCount = $db->table('appointments')->select('patient_id')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->groupBy('patient_id')->countAllResults();
        $followUpCount = $db->table('consultations')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('follow_up_date >=', $today)->where('follow_up_date IS NOT NULL', null, false)->where('status !=', 'cancelled')->countAllResults();
        $billingCount = $db->table('invoices')->join('appointments', 'appointments.id = invoices.appointment_id')->where('appointments.doctor_id', $doctorId)->where('invoices.clinic_id', $clinicId)->countAllResults();

        $clarifications = (new PrescriptionClarificationService())->doctorList($doctorId, $clinicId, true);
        $clarifications = array_slice($clarifications, 0, 5);
        $recentConsultations = $db->table('consultations')
            ->select('consultations.id, consultations.consultation_number, consultations.consultation_date, consultations.consultation_time, consultations.status, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number')
            ->join('patients', 'patients.id = consultations.patient_id AND patients.clinic_id = consultations.clinic_id')
            ->where('consultations.doctor_id', $doctorId)->where('consultations.clinic_id', $clinicId)
            ->orderBy('consultations.consultation_date', 'DESC')->orderBy('consultations.consultation_time', 'DESC')->orderBy('consultations.id', 'DESC')->limit(5)->get()->getResultArray();
        $recentPrescriptions = $db->table('prescriptions')
            ->select('prescriptions.id, prescriptions.prescription_number, prescriptions.prescription_date, prescriptions.status, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number')
            ->join('patients', 'patients.id = prescriptions.patient_id AND patients.clinic_id = prescriptions.clinic_id')
            ->where('prescriptions.doctor_id', $doctorId)->where('prescriptions.clinic_id', $clinicId)
            ->orderBy('prescriptions.prescription_date', 'DESC')->orderBy('prescriptions.id', 'DESC')->limit(5)->get()->getResultArray();
        $followUps = $db->table('consultations')
            ->select('consultations.id, consultations.follow_up_date, consultations.follow_up_time, consultations.follow_up_type, consultations.follow_up_status_id, follow_up_statuses.name AS follow_up_status_name, follow_up_statuses.code AS follow_up_status_code, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, patients.registration_number')
            ->join('patients', 'patients.id = consultations.patient_id AND patients.clinic_id = consultations.clinic_id')
            ->join('follow_up_statuses', 'follow_up_statuses.id = consultations.follow_up_status_id', 'left')
            ->where('consultations.doctor_id', $doctorId)->where('consultations.clinic_id', $clinicId)
            ->where('consultations.follow_up_date >=', $today)->where('consultations.follow_up_date IS NOT NULL', null, false)->where('consultations.status !=', 'cancelled')
            ->orderBy('consultations.follow_up_date', 'ASC')->orderBy('consultations.follow_up_time', 'ASC')->limit(5)->get()->getResultArray();
        $pharmacyUpdates = $db->table('doctor_notifications')
            ->select('id, title, message, status, created_at')
            ->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)
            ->whereIn('related_entity_type', ['prescription_clarification', 'prescription'])
            ->orderBy('created_at', 'DESC')->limit(5)->get()->getResultArray();
        $notifications = $db->table('doctor_notifications')
            ->select('id, title, message, status, created_at')
            ->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)
            ->orderBy('created_at', 'DESC')->limit(5)->get()->getResultArray();
        $unreadNotificationCount = $db->table('doctor_notifications')->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->where('status', 'unread')->countAllResults();

        return view(DOCTOR_VIEW . 'pages/dashboard', [
            'doctor' => $doctor, 'appointments' => $appointments, 'appointmentCount' => $appointmentCount,
            'consultationCount' => $consultationCount, 'completedConsultationCount' => $completedConsultationCount,
            'prescriptionCount' => $prescriptionCount, 'prescriptionReviewCount' => $prescriptionReviewCount,
            'prescriptionClarificationCount' => $prescriptionClarificationCount, 'approvedPrescriptionCount' => $approvedPrescriptionCount,
            'patientCount' => $patientCount, 'followUpCount' => $followUpCount, 'billingCount' => $billingCount,
            'clarifications' => $clarifications, 'recentConsultations' => $recentConsultations,
            'recentPrescriptions' => $recentPrescriptions, 'followUps' => $followUps,
            'notifications' => $notifications, 'pharmacyUpdates' => $pharmacyUpdates,
            'unreadNotificationCount' => $unreadNotificationCount,
        ]);
    }
}
