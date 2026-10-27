<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

class ConsultationFeePaymentService
{
    private const METHODS = ['cash', 'card', 'upi', 'bank_transfer', 'online', 'manual'];

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? db_connect();
    }

    public function summary(int $appointmentId, ?int $clinicId = null, ?int $doctorId = null): ?array
    {
        $query = $this->appointmentQuery($appointmentId, $clinicId, $doctorId);
        $appointment = $query->get()->getRowArray();
        if ($appointment === null) {
            return null;
        }

        return $this->withPaymentSummary($appointment);
    }

    public function collect(int $appointmentId, array $input, int $recordedBy, ?int $clinicId = null, ?int $doctorId = null): array
    {
        $amount = round((float) ($input['amount'] ?? 0), 2);
        $method = strtolower(trim((string) ($input['payment_method'] ?? '')));
        $reference = trim((string) ($input['reference_number'] ?? ''));
        $notes = trim((string) ($input['notes'] ?? ''));
        $idempotency = trim((string) ($input['idempotency_key'] ?? ''));
        $date = trim((string) ($input['payment_date'] ?? ''));
        $time = trim((string) ($input['payment_time'] ?? ''));
        $errors = [];
        if ($amount <= 0 || $amount > 99999999.99) $errors['amount'] = 'Enter a valid positive amount.';
        if (!in_array($method, self::METHODS, true)) $errors['payment_method'] = 'Choose a valid payment method.';
        if ($reference !== '' && strlen($reference) > 150) $errors['reference_number'] = 'Reference number is too long.';
        if (strlen($notes) > 1000) $errors['notes'] = 'Notes are too long.';
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) $errors['payment_date'] = 'Enter a valid payment date.';
        if (!preg_match('/^\d{2}:\d{2}$/', $time)) $errors['payment_time'] = 'Enter a valid payment time.';
        if ($date !== '' && $time !== '' && strtotime($date . ' ' . $time) > time()) $errors['payment_date'] = 'Payment date and time cannot be in the future.';
        if ($idempotency === '' || strlen($idempotency) > 100) $errors['idempotency_key'] = 'A payment submission key is required.';
        if ($errors !== []) return ['ok' => false, 'http' => 422, 'message' => reset($errors), 'errors' => $errors];

        $summary = $this->summary($appointmentId, $clinicId, $doctorId);
        if ($summary === null) return ['ok' => false, 'http' => 404, 'message' => 'Appointment not found in the permitted scope.'];
        if (in_array((string) $summary['status'], ['cancelled', 'no_show'], true)) return ['ok' => false, 'http' => 409, 'message' => 'A fee cannot be collected for a cancelled or no-show appointment.'];
        if ((float) $summary['remaining_amount'] <= 0) return ['ok' => false, 'http' => 409, 'message' => 'The consultation fee is already fully paid.'];
        if ($amount > (float) $summary['remaining_amount']) return ['ok' => false, 'http' => 422, 'message' => 'Amount cannot exceed the remaining consultation fee.', 'errors' => ['amount' => 'Amount cannot exceed the remaining balance.']];

        $existing = $this->db->table('consultation_fee_payments')->where('clinic_id', (int) $summary['clinic_id'])->where('idempotency_key', $idempotency)->get()->getRowArray();
        if ($existing !== null) return ['ok' => true, 'http' => 200, 'message' => 'This payment was already recorded.', 'duplicate' => true, 'data' => $this->summary($appointmentId, $clinicId, $doctorId)];
        if ($reference !== '' && $this->db->table('consultation_fee_payments')->where('appointment_id', $appointmentId)->where('clinic_id', (int) $summary['clinic_id'])->where('reference_number', $reference)->where('status', 'succeeded')->countAllResults() > 0) return ['ok' => false, 'http' => 409, 'message' => 'This reference number is already recorded for the appointment.'];

        $paidAt = $date . ' ' . $time . ':00';
        $this->db->transBegin();
        try {
            $locked = $this->db->query('SELECT id FROM appointments WHERE id = ? AND clinic_id = ?' . ($doctorId !== null ? ' AND doctor_id = ?' : '') . ' FOR UPDATE', $doctorId !== null ? [$appointmentId, (int) $summary['clinic_id'], $doctorId] : [$appointmentId, (int) $summary['clinic_id']])->getRowArray();
            if ($locked === null) throw new \RuntimeException('Appointment scope changed.');
            $row = ['appointment_id' => $appointmentId, 'patient_id' => (int) $summary['patient_id'], 'doctor_id' => (int) $summary['doctor_id'], 'clinic_id' => (int) $summary['clinic_id'], 'receipt_number' => 'CFP-' . date('YmdHis') . '-' . strtoupper(bin2hex(random_bytes(3))), 'amount' => $amount, 'currency' => 'INR', 'transaction_type' => 'payment', 'status' => 'succeeded', 'payment_method' => $method, 'reference_number' => $reference !== '' ? $reference : null, 'notes' => $notes !== '' ? $notes : null, 'paid_at' => $paidAt, 'recorded_by' => $recordedBy > 0 ? $recordedBy : null, 'idempotency_key' => $idempotency, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')];
            if (!$this->db->table('consultation_fee_payments')->insert($row) || $this->db->transStatus() === false) throw new \RuntimeException('Payment could not be recorded.');
            $this->db->transCommit();
            service('audit')->record('appointment.consultation_fee_collected', ['appointment_id' => $appointmentId, 'payment_id' => (int) $this->db->insertID(), 'clinic_id' => (int) $summary['clinic_id'], 'patient_id' => (int) $summary['patient_id'], 'amount' => $amount, 'payment_method' => $method, 'reference_number' => $reference]);
            return ['ok' => true, 'http' => 200, 'message' => 'Consultation fee recorded successfully.', 'data' => $this->summary($appointmentId, $clinicId, $doctorId)];
        } catch (Throwable $e) {
            $this->db->transRollback();
            log_message('error', 'Consultation fee collection failed: ' . $e->getMessage());
            return ['ok' => false, 'http' => 500, 'message' => 'Consultation fee could not be recorded safely.'];
        }
    }

    private function appointmentQuery(int $appointmentId, ?int $clinicId, ?int $doctorId)
    {
        $query = $this->db->table('appointments')->select('appointments.*, patients.first_name AS patient_first_name, patients.last_name AS patient_last_name, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, clinics.clinic_name')->join('patients', 'patients.id = appointments.patient_id')->join('doctors', 'doctors.id = appointments.doctor_id')->join('clinics', 'clinics.id = appointments.clinic_id')->where('appointments.id', $appointmentId)->where('patients.clinic_id = appointments.clinic_id', null, false);
        if ($clinicId !== null) $query->where('appointments.clinic_id', $clinicId);
        if ($doctorId !== null) $query->where('appointments.doctor_id', $doctorId);
        return $query;
    }

    private function withPaymentSummary(array $appointment): array
    {
        $fee = (float) ($appointment['consultation_fee'] ?? 0);
        if ($fee <= 0) $fee = $this->resolveFee((int) $appointment['doctor_id'], (string) ($appointment['appointment_type'] ?? ''), (string) ($appointment['appointment_date'] ?? ''));
        $paid = (float) ($this->db->table('consultation_fee_payments')->selectSum('amount')->where('appointment_id', (int) $appointment['id'])->where('clinic_id', (int) $appointment['clinic_id'])->where('transaction_type', 'payment')->where('status', 'succeeded')->get()->getRow('amount') ?? 0);
        $refunded = (float) ($this->db->table('consultation_fee_payments')->selectSum('amount')->where('appointment_id', (int) $appointment['id'])->where('clinic_id', (int) $appointment['clinic_id'])->where('transaction_type', 'refund')->where('status', 'succeeded')->get()->getRow('amount') ?? 0);
        $paid = max(0, round($paid - $refunded, 2));
        $remaining = max(0, round($fee - $paid, 2));
        $latest = $this->db->table('consultation_fee_payments')->where('appointment_id', (int) $appointment['id'])->where('clinic_id', (int) $appointment['clinic_id'])->where('transaction_type', 'payment')->where('status', 'succeeded')->orderBy('id', 'DESC')->get(1)->getRowArray() ?? [];
        $appointment['consultation_fee'] = $fee;
        $appointment['paid_amount'] = $paid;
        $appointment['remaining_amount'] = $remaining;
        $appointment['payment_status'] = $fee <= 0 ? 'not_applicable' : ($paid <= 0 ? 'unpaid' : ($remaining <= 0 ? 'paid' : 'partially_paid'));
        $appointment['payment_date'] = $latest['paid_at'] ?? null;
        $appointment['payment_method'] = $latest['payment_method'] ?? null;
        $appointment['receipt_number'] = $latest['receipt_number'] ?? null;
        $appointment['reference_number'] = $latest['reference_number'] ?? null;
        return $appointment;
    }

    private function resolveFee(int $doctorId, string $appointmentType, string $date): float
    {
        $mode = in_array(strtolower($appointmentType), ['video', 'phone'], true) ? 'ONLINE' : 'OFFLINE';
        $row = $this->db->table('doctor_consultation_fees')->where('doctor_id', $doctorId)->where('consultation_mode', $mode)->where('status', 1)->where('effective_from <=', $date ?: date('Y-m-d'))->groupStart()->where('effective_to IS NULL', null, false)->orWhere('effective_to >=', $date ?: date('Y-m-d'))->groupEnd()->groupStart()->where('consultation_type', 'ALL')->orWhere('consultation_type', 'NEW')->groupEnd()->orderBy('effective_from', 'DESC')->orderBy('id', 'DESC')->get(1)->getRowArray();
        return round((float) ($row['fee'] ?? 0), 2);
    }
}
