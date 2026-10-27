<?php

namespace App\Services;

use RuntimeException;

final class PrescriptionClarificationService
{
    public const REQUESTED = 'requested';
    public const DOCTOR_RESPONDED = 'doctor_responded';
    public const PRESCRIPTION_UPDATED = 'prescription_updated';
    public const CONFIRMED = 'confirmed';
    public const REJECTED = 'rejected';
    public const CLOSED = 'closed';

    private const DOCTOR_ACTIONS = ['respond', 'update', 'confirm', 'reject', 'close'];

    /**
     * Create one active clarification request for a clinic-scoped prescription.
     * The unique dedupe key also protects against concurrent duplicate requests.
     *
     * @return array{ok:bool,status:int,msg:string,id?:int}
     */
    public function request(int $prescriptionId, int $clinicId, int $pharmacyUserId, ?int $itemId, string $message): array
    {
        $message = trim($message);

        if ($message === '' || mb_strlen($message) < 3) {
            return ['ok' => false, 'status' => 422, 'msg' => 'Enter a meaningful clarification message.'];
        }
        if (mb_strlen($message) > 4000) {
            return ['ok' => false, 'status' => 422, 'msg' => 'Clarification message cannot exceed 4000 characters.'];
        }

        $db = db_connect();
        $prescription = $this->prescription($db, $prescriptionId, $clinicId);

        if ($prescription === null) {
            return ['ok' => false, 'status' => 404, 'msg' => 'Prescription is not available in the assigned clinic.'];
        }
        if (in_array((string) $prescription['status'], ['approved', 'rejected', 'cancelled'], true)) {
            return ['ok' => false, 'status' => 409, 'msg' => 'A clarification cannot be requested for a final prescription.'];
        }

        $item = null;

        if ($itemId !== null && $itemId > 0) {
            $item = $db->table('prescription_items')->where('id', $itemId)->where('prescription_id', $prescriptionId)->get()->getRowArray();

            if ($item === null) {
                return ['ok' => false, 'status' => 422, 'msg' => 'The selected medicine is not part of this prescription.'];
            }
        }

        $dedupeKey = 'prescription:' . $prescriptionId;
        $db->transBegin();

        try {
            $open = $db->table('prescription_clarifications')->where('dedupe_key', $dedupeKey)->get(1)->getRowArray();

            if ($open !== null) {
                $db->transRollback();

                return ['ok' => false, 'status' => 409, 'msg' => 'A clarification request is already awaiting a doctor response.'];
            }

            $now = date('Y-m-d H:i:s');
            $inserted = $db->table('prescription_clarifications')->insert([
                'prescription_id' => $prescriptionId,
                'prescription_item_id' => $itemId ?: null,
                'clinic_id' => $clinicId,
                'patient_id' => (int) $prescription['patient_id'],
                'doctor_id' => (int) $prescription['doctor_id'],
                'pharmacy_user_id' => $pharmacyUserId ?: null,
                'status' => self::REQUESTED,
                'dedupe_key' => $dedupeKey,
                'pharmacy_message' => $message,
                'requested_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            if (!$inserted) {
                throw new RuntimeException('Clarification request insert failed.');
            }
            $clarificationId = (int) $db->insertID();

            if (!$db->table('prescriptions')->where('id', $prescriptionId)->where('clinic_id', $clinicId)->whereIn('status', ['draft', 'submitted'])->update(['status' => 'clarification_requested', 'updated_at' => $now])) {
                throw new RuntimeException('Prescription status update failed.');
            }
            if (!$db->transCommit()) {
                throw new RuntimeException('Clarification transaction failed.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Prescription clarification request failed: ' . $e->getMessage());

            if (str_contains(strtolower($e->getMessage()), 'duplicate')) {
                return ['ok' => false, 'status' => 409, 'msg' => 'A clarification request is already awaiting a doctor response.'];
            }

            return ['ok' => false, 'status' => 500, 'msg' => 'Clarification request could not be saved safely.'];
        }

        $this->notifyDoctor($prescription, $clarificationId, $message);
        service('audit')->record('pharmacy.prescription_clarification_requested', [
            'clinic_id' => $clinicId,
            'prescription_id' => $prescriptionId,
            'clarification_id' => $clarificationId,
            'prescription_item_id' => $itemId,
            'doctor_id' => (int) $prescription['doctor_id'],
            'pharmacy_user_id' => $pharmacyUserId,
        ]);

        return ['ok' => true, 'status' => 200, 'msg' => 'Clarification request sent to the doctor.', 'id' => $clarificationId];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok:bool,status:int,msg:string,data?:array<string,mixed>}
     */
    public function respond(int $clarificationId, int $doctorId, int $clinicId, array $input): array
    {
        $action = strtolower(trim((string) ($input['action'] ?? '')));
        $message = trim((string) ($input['response_message'] ?? ''));

        if (!in_array($action, self::DOCTOR_ACTIONS, true)) {
            return ['ok' => false, 'status' => 422, 'msg' => 'Choose a valid clarification action.'];
        }
        if ($message === '' || mb_strlen($message) < 3) {
            return ['ok' => false, 'status' => 422, 'msg' => 'A response message is required.'];
        }
        if (mb_strlen($message) > 4000) {
            return ['ok' => false, 'status' => 422, 'msg' => 'Response message cannot exceed 4000 characters.'];
        }
        if (in_array($action, ['reject', 'close'], true) && mb_strlen($message) < 5) {
            return ['ok' => false, 'status' => 422, 'msg' => 'Explain why the clarification is being rejected or closed.'];
        }

        $db = db_connect();
        $clarification = $this->clarification($db, $clarificationId, $doctorId, $clinicId);

        if ($clarification === null) {
            return ['ok' => false, 'status' => 404, 'msg' => 'Clarification request was not found in your doctor scope.'];
        }
        if ((string) $clarification['status'] !== self::REQUESTED) {
            return ['ok' => false, 'status' => 409, 'msg' => 'This clarification request has already been resolved.'];
        }

        $itemId = (int) ($clarification['prescription_item_id'] ?? 0);
        $oldItem = null;
        $newItem = null;

        if ($action === 'update') {
            if ($itemId <= 0) {
                return ['ok' => false, 'status' => 422, 'msg' => 'Select a medicine-specific request before updating medicine details.'];
            }
            $oldItem = $db->table('prescription_items')->where('id', $itemId)->where('prescription_id', (int) $clarification['prescription_id'])->get()->getRowArray();

            if ($oldItem === null) {
                return ['ok' => false, 'status' => 409, 'msg' => 'The requested medicine is no longer available.'];
            }
            $newItem = $this->normalizedItem($input, $oldItem);

            if ($newItem['medicine_name'] === '') {
                return ['ok' => false, 'status' => 422, 'msg' => 'Medicine name is required when updating the prescription.'];
            }
        }

        $status = match ($action) {
            'respond' => self::DOCTOR_RESPONDED,
            'update' => self::PRESCRIPTION_UPDATED,
            'confirm' => self::CONFIRMED,
            'reject' => self::REJECTED,
            default => self::CLOSED,
        };
        $prescriptionStatus = in_array($action, ['confirm', 'reject'], true) ? ($action === 'confirm' ? 'approved' : 'rejected') : 'submitted';
        $now = date('Y-m-d H:i:s');
        $db->transBegin();

        try {
            if ($newItem !== null && !$db->table('prescription_items')->where('id', $itemId)->where('prescription_id', (int) $clarification['prescription_id'])->update($newItem + ['updated_at' => $now])) {
                throw new RuntimeException('Prescription item update failed.');
            }
            if (!$db->table('prescriptions')->where('id', (int) $clarification['prescription_id'])->where('clinic_id', $clinicId)->where('status', 'clarification_requested')->update(['status' => $prescriptionStatus, 'updated_at' => $now])) {
                throw new RuntimeException('Prescription status changed before response.');
            }
            $updated = $db->table('prescription_clarifications')->where('id', $clarificationId)->where('doctor_id', $doctorId)->where('status', self::REQUESTED)->update([
                'status' => $status,
                'dedupe_key' => null,
                'doctor_response' => $message,
                'doctor_action' => $action,
                'old_item_values' => $oldItem === null ? null : $this->encode($oldItem),
                'new_item_values' => $newItem === null ? null : $this->encode($newItem),
                'responded_at' => $now,
                'closed_at' => $now,
                'updated_at' => $now,
            ]);

            if (!$updated || !$db->transCommit()) {
                throw new RuntimeException('Clarification response transaction failed.');
            }
        } catch (\Throwable $e) {
            $db->transRollback();
            log_message('error', 'Prescription clarification response failed: ' . $e->getMessage());

            return ['ok' => false, 'status' => 409, 'msg' => 'Clarification could not be completed because the record changed. Refresh and try again.'];
        }

        $this->notifyPharmacy($clarification, $status, $prescriptionStatus);
        service('audit')->record('doctor.prescription_clarification_' . $action, [
            'clinic_id' => $clinicId,
            'prescription_id' => (int) $clarification['prescription_id'],
            'clarification_id' => $clarificationId,
            'prescription_item_id' => $itemId ?: null,
            'doctor_id' => $doctorId,
            'previous_status' => self::REQUESTED,
            'new_status' => $status,
            'prescription_status' => $prescriptionStatus,
        ]);

        return ['ok' => true, 'status' => 200, 'msg' => 'Clarification response recorded.', 'data' => ['status' => $status, 'prescription_status' => $prescriptionStatus]];
    }

    /** @return list<array<string,mixed>> */
    public function doctorList(int $doctorId, int $clinicId, bool $pendingOnly = false): array
    {
        $query = db_connect()->table('prescription_clarifications pc')
            ->select('pc.*, p.prescription_number, p.prescription_date, p.status AS prescription_status, pa.first_name AS patient_first_name, pa.middle_name AS patient_middle_name, pa.last_name AS patient_last_name, d.first_name AS doctor_first_name, d.last_name AS doctor_last_name, c.clinic_name, u.username AS pharmacy_username, u.email AS pharmacy_email, pi.medicine_name')
            ->join('prescriptions p', 'p.id = pc.prescription_id')
            ->join('patients pa', 'pa.id = pc.patient_id')
            ->join('doctors d', 'd.id = pc.doctor_id')
            ->join('clinics c', 'c.id = pc.clinic_id', 'left')
            ->join('users u', 'u.id = pc.pharmacy_user_id', 'left')
            ->join('prescription_items pi', 'pi.id = pc.prescription_item_id', 'left')
            ->where('pc.doctor_id', $doctorId)->where('pc.clinic_id', $clinicId);

        if ($pendingOnly) {
            $query->where('pc.status', self::REQUESTED);
        }

        return array_map([$this, 'displayRow'], $query->orderBy('pc.requested_at', 'DESC')->get()->getResultArray());
    }

    /** @return array<string,mixed>|null */
    public function doctorFind(int $id, int $doctorId, int $clinicId): ?array
    {
        $row = db_connect()->table('prescription_clarifications pc')
            ->select('pc.*, p.prescription_number, p.prescription_date, p.medicine_duration AS medicine_duration, p.status AS prescription_status, pa.first_name AS patient_first_name, pa.middle_name AS patient_middle_name, pa.last_name AS patient_last_name, d.first_name AS doctor_first_name, d.last_name AS doctor_last_name, c.clinic_name, u.username AS pharmacy_username, u.email AS pharmacy_email, pi.medicine_name, pi.dosage, pi.frequency, pi.duration AS item_duration, pi.duration_unit, pi.instructions, pi.notes AS item_notes')
            ->join('prescriptions p', 'p.id = pc.prescription_id')
            ->join('patients pa', 'pa.id = pc.patient_id')
            ->join('doctors d', 'd.id = pc.doctor_id')
            ->join('clinics c', 'c.id = pc.clinic_id', 'left')
            ->join('users u', 'u.id = pc.pharmacy_user_id', 'left')
            ->join('prescription_items pi', 'pi.id = pc.prescription_item_id', 'left')
            ->where('pc.id', $id)->where('pc.doctor_id', $doctorId)->where('pc.clinic_id', $clinicId)->get()->getRowArray();

        if (is_array($row)) {
            $row['duration'] = null;
        }

        return $row === null ? null : $this->displayRow($row);
    }

    /** @return array<string,mixed>|null */
    private function clarification($db, int $id, int $doctorId, int $clinicId): ?array
    {
        return $db->table('prescription_clarifications')->where('id', $id)->where('doctor_id', $doctorId)->where('clinic_id', $clinicId)->get(1)->getRowArray() ?: null;
    }

    /** @return array<string,mixed>|null */
    private function prescription($db, int $id, int $clinicId): ?array
    {
        return $db->table('prescriptions')->select('prescriptions.*, pa.email AS patient_email')->join('patients pa', 'pa.id = prescriptions.patient_id')->join('doctors d', 'd.id = prescriptions.doctor_id')->where('prescriptions.id', $id)->where('prescriptions.clinic_id', $clinicId)->where('pa.clinic_id', $clinicId)->where('d.clinic_id', $clinicId)->get()->getRowArray() ?: null;
    }

    /** @return array<string,mixed> */
    private function normalizedItem(array $input, array $old): array
    {
        return [
            'medicine_name' => mb_substr(trim((string) ($input['medicine_name'] ?? $old['medicine_name'] ?? '')), 0, 255),
            'dosage' => mb_substr(trim((string) ($input['dosage'] ?? $old['dosage'] ?? '')), 0, 100) ?: null,
            'frequency' => mb_substr(trim((string) ($input['frequency'] ?? $old['frequency'] ?? '')), 0, 100) ?: null,
            'duration' => mb_substr(trim((string) ($input['duration'] ?? $old['duration'] ?? '')), 0, 50) ?: null,
            'duration_unit' => mb_substr(trim((string) ($input['duration_unit'] ?? $old['duration_unit'] ?? '')), 0, 30) ?: null,
            'instructions' => trim((string) ($input['instructions'] ?? $old['instructions'] ?? '')) ?: null,
            'notes' => trim((string) ($input['notes'] ?? $old['notes'] ?? '')) ?: null,
        ];
    }

    /** @return array<string,mixed> */
    private function displayRow(array $row): array
    {
        $patient = trim(implode(' ', array_filter([$row['patient_first_name'] ?? '', $row['patient_middle_name'] ?? '', $row['patient_last_name'] ?? '']))) ?: 'Unknown patient';
        $doctor = trim(implode(' ', array_filter([$row['doctor_first_name'] ?? '', $row['doctor_last_name'] ?? '']))) ?: 'Unknown doctor';
        $requester = trim((string) ($row['pharmacy_username'] ?? '')) ?: trim((string) ($row['pharmacy_email'] ?? '')) ?: 'Pharmacy user unavailable';
        $pharmacy = trim((string) ($row['clinic_name'] ?? '')) ?: 'Pharmacy not recorded';
        $labels = [self::REQUESTED => 'Clarification requested', self::DOCTOR_RESPONDED => 'Doctor responded', self::PRESCRIPTION_UPDATED => 'Prescription updated', self::CONFIRMED => 'Confirmed', self::REJECTED => 'Rejected', self::CLOSED => 'Closed'];

        return $row + ['patient_name' => $patient, 'doctor_name' => $doctor, 'requester_name' => $requester, 'pharmacy_name' => $pharmacy, 'status_label' => $labels[(string) ($row['status'] ?? '')] ?? 'Clarification', 'requested_display' => !empty($row['requested_at']) ? date('d-m-Y H:i', strtotime((string) $row['requested_at'])) : 'Not recorded', 'responded_display' => !empty($row['responded_at']) ? date('d-m-Y H:i', strtotime((string) $row['responded_at'])) : 'Not recorded'];
    }

    private function notifyDoctor(array $prescription, int $clarificationId, string $message): void
    {
        $db = db_connect();
        if (!$db->tableExists('doctor_notifications')) {
            return;
        }
        $number = (string) ($prescription['prescription_number'] ?? ('#' . $prescription['id']));
        $eventKey = 'prescription-clarification:' . $clarificationId . ':requested';
        $db->table('doctor_notifications')->ignore(true)->insert(['doctor_id' => (int) $prescription['doctor_id'], 'clinic_id' => (int) $prescription['clinic_id'], 'title' => 'Prescription clarification requested', 'message' => 'Pharmacy requested clarification for ' . $number . '.', 'channel' => 'in_app', 'status' => 'unread', 'related_entity_type' => 'prescription_clarification', 'related_entity_id' => $clarificationId, 'event_key' => $eventKey, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function notifyPharmacy(array $clarification, string $status, string $prescriptionStatus): void
    {
        $db = db_connect();
        if (!$db->tableExists('pharmacy_notifications') || (int) ($clarification['pharmacy_user_id'] ?? 0) <= 0) {
            return;
        }
        $labels = [self::DOCTOR_RESPONDED => 'Doctor responded', self::PRESCRIPTION_UPDATED => 'Prescription updated', self::CONFIRMED => 'Prescription confirmed', self::REJECTED => 'Clarification rejected', self::CLOSED => 'Clarification closed'];
        $eventKey = 'prescription-clarification:' . (int) $clarification['id'] . ':' . $status;
        $prescriptionNumber = (string) ($db->table('prescriptions')->select('prescription_number')->where('id', (int) $clarification['prescription_id'])->get(1)->getRow('prescription_number'));
        $prescriptionLabel = trim($prescriptionNumber) !== '' ? $prescriptionNumber : 'prescription #' . (int) $clarification['prescription_id'];
        $db->table('pharmacy_notifications')->ignore(true)->insert(['clinic_id' => (int) $clarification['clinic_id'], 'recipient_user_id' => (int) $clarification['pharmacy_user_id'], 'recipient_role' => 'Pharmacy Assistant', 'type' => 'prescription_clarification_response', 'title' => $labels[$status] ?? 'Prescription clarification updated', 'message' => ($labels[$status] ?? 'Prescription clarification updated') . ' for ' . $prescriptionLabel . '.', 'related_entity_type' => 'prescription', 'related_entity_id' => (int) $clarification['prescription_id'], 'event_key' => $eventKey, 'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')]);
    }

    private function encode(array $values): string
    {
        return json_encode($values, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE) ?: '{}';
    }
}
