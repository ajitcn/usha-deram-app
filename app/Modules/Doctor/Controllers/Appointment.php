<?php

namespace Doctor\Controllers;

use Admin\Models\Doctor\DoctorModel;
use Admin\Models\User;
use App\Controllers\BaseController;
use App\Services\ConsultationFeePaymentService;
use CodeIgniter\HTTP\RedirectResponse;

class Appointment extends BaseController
{
    public function view(int $appointmentId): string|RedirectResponse
    {
        if (!in_array('Doctor', session('roles') ?? [], true)) {
            return redirect()->to(base_url('permission-denied'));
        }
        $sessionUser = session('user') ?? [];
        $userId = (int) ($sessionUser['id'] ?? 0);

        if ($userId <= 0 || $appointmentId <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $user = (new User())->select('id, doctor_id')->where('id', $userId)->where('status', 1)->first();
        $doctorId = (int) ($user['doctor_id'] ?? 0);

        if ($doctorId <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $doctor = (new DoctorModel())->select('id, clinic_id, first_name, last_name, doctor_code')->where('id', $doctorId)->where('status', 1)->first();

        if ($doctor === null || (int) $doctor['clinic_id'] <= 0) {
            return redirect()->to(base_url('permission-denied'));
        }
        $clinicId = (int) $doctor['clinic_id'];
        $db = db_connect();
        $appointment = $db->table('appointments')->select('appointments.id, appointments.patient_id, appointments.appointment_number, appointments.appointment_date, appointments.appointment_time, appointments.duration_minutes, appointments.appointment_type, appointments.service_name, appointments.status, appointments.reason_for_visit, patients.registration_number, patients.first_name AS patient_first_name, patients.middle_name AS patient_middle_name, patients.last_name AS patient_last_name, patients.mobile_number AS patient_mobile_number, patients.mobile_normalized AS patient_mobile_normalized, patients.country_code AS patient_legacy_country_code, patients.source_type AS patient_source_type, telephone_country_codes.normalized_code AS patient_telephone_country_code')->join('patients', 'patients.id = appointments.patient_id')->join('telephone_country_codes', 'telephone_country_codes.id = patients.telephone_country_code_id', 'left')->where('appointments.id', $appointmentId)->where('appointments.doctor_id', $doctorId)->where('appointments.clinic_id', $clinicId)->where('patients.clinic_id', $clinicId)->get()->getRowArray();

        if ($appointment === null) {
            return redirect()->to(base_url('permission-denied'));
        }
        $consultation = $db->table('consultations')->select('id, consultation_number, consultation_date, status')->where('appointment_id', $appointmentId)->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->get()->getRowArray();
        $prescription = $db->table('prescriptions')->select('prescription_number, prescription_date, status')->where('consultation_id', (int) ($consultation['id'] ?? 0))->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->get()->getRowArray();
        service('audit')->record('doctor.appointment_view', ['appointment_id' => $appointmentId, 'doctor_id' => $doctorId, 'clinic_id' => $clinicId]);

        return view(DOCTOR_VIEW . 'pages/appointment', ['doctor' => $doctor, 'appointment' => $appointment, 'consultation' => $consultation, 'prescription' => $prescription, 'feeSummary' => (new ConsultationFeePaymentService())->summary($appointmentId, $clinicId, $doctorId), 'canCollectFee' => in_array('billing.edit', session('permissions') ?? [], true)]);
    }

    public function collectFee(int $appointmentId)
    {
        if (!$this->request->isAJAX() || $this->request->getMethod(true) !== 'POST') return $this->response->setStatusCode(405)->setJSON(['status' => false, 'msg' => 'Invalid request.']);
        $user = (new User())->select('id, doctor_id')->where('id', (int) (session('user')['id'] ?? 0))->where('status', 1)->first();
        $doctor = $user === null ? null : (new DoctorModel())->select('id, clinic_id')->where('id', (int) ($user['doctor_id'] ?? 0))->where('status', 1)->first();
        if ($doctor === null) return $this->response->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Doctor scope is invalid.']);
        $result = (new ConsultationFeePaymentService())->collect($appointmentId, $this->request->getPost(), (int) ($user['id'] ?? 0), (int) $doctor['clinic_id'], (int) $doctor['id']);
        return $this->response->setStatusCode((int) $result['http'])->setJSON(['status' => (bool) $result['ok'], 'msg' => $result['message'], 'errors' => $result['errors'] ?? null, 'data' => $result['data'] ?? null]);
    }
}
