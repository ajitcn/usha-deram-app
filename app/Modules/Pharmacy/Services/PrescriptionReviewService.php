<?php

namespace Pharmacy\Services;

use DateTimeImmutable;

final class PrescriptionReviewService
{
    private const MAX_DISPENSING_QUANTITY = 100000;

    private const REVIEW_STATES = ['pending' => ['draft', 'submitted'], 'clarification' => ['clarification_requested'], 'final' => ['approved', 'rejected', 'cancelled']];

    public function __construct(private readonly PharmacyScope $scope)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function list(array $input): array
    {
        $clinicId = $this->scope->clinicId();
        $pageSize = (int) ($input['page_size'] ?? 15);
        $pageSize = in_array($pageSize, [10, 15, 25, 50], true) ? $pageSize : 15;
        $page = max(1, (int) ($input['page'] ?? 1));
        $requestedView = (string) ($input['view'] ?? 'list');
        $view = in_array($requestedView, ['list', 'grid'], true) ? $requestedView : 'list';
        $search = trim((string) ($input['search'] ?? ''));
        $status = trim((string) ($input['status'] ?? ''));
        $reviewState = trim((string) ($input['review_state'] ?? ''));
        $doctorId = (int) ($input['doctor_id'] ?? 0);
        $from = $this->toIsoDate((string) ($input['from'] ?? ''));
        $to = $this->toIsoDate((string) ($input['to'] ?? ''));

        if ($clinicId === null) {
            return $this->emptyResult($view, $pageSize);
        }

        $statusCounts = $this->statusCounts($clinicId);

        $query = db_connect()->table('prescriptions')
            ->select('prescriptions.id, prescriptions.prescription_number, prescriptions.prescription_date, prescriptions.medicine_duration, prescriptions.notes, prescriptions.status, prescriptions.created_at, prescriptions.updated_at, prescriptions.patient_id, prescriptions.doctor_id, medicine_orders.id AS order_id, patients.registration_number, patients.first_name AS patient_first_name, patients.middle_name AS patient_middle_name, patients.last_name AS patient_last_name, patients.mobile_number AS patient_mobile_number, patients.country_code AS patient_country_code, doctors.first_name AS doctor_first_name, doctors.last_name AS doctor_last_name, doctors.doctor_code')
            ->join('patients', 'patients.id = prescriptions.patient_id')
            ->join('doctors', 'doctors.id = prescriptions.doctor_id')
            ->join('medicine_orders', 'medicine_orders.prescription_id = prescriptions.id AND medicine_orders.clinic_id = prescriptions.clinic_id', 'left', false)
            ->where('prescriptions.clinic_id', $clinicId)
            ->where('patients.clinic_id', $clinicId)
            ->where('doctors.clinic_id', $clinicId)
            ->whereIn('prescriptions.status', array_keys(TransitionContract::PRESCRIPTION_REVIEW));

        if ($status !== '' && in_array($status, array_keys(TransitionContract::PRESCRIPTION_REVIEW), true)) {
            $query->where('prescriptions.status', $status);
        }

        if (isset(self::REVIEW_STATES[$reviewState])) {
            $query->whereIn('prescriptions.status', self::REVIEW_STATES[$reviewState]);
        }

        if ($doctorId > 0) {
            $query->where('prescriptions.doctor_id', $doctorId);
        }

        if ($from !== null) {
            $query->where('prescriptions.prescription_date >=', $from);
        }

        if ($to !== null) {
            $query->where('prescriptions.prescription_date <=', $to);
        }

        if ($search !== '') {
            $query->groupStart()->like('prescriptions.prescription_number', $search)->orLike('patients.registration_number', $search)->orLike('patients.first_name', $search)->orLike('patients.middle_name', $search)->orLike('patients.last_name', $search)->orLike('patients.mobile_number', $search)->orLike('patients.mobile_normalized', $search)->orLike('doctors.first_name', $search)->orLike('doctors.last_name', $search)->orLike('prescriptions.status', $search)->groupEnd();
        }
        $total = (clone $query)->countAllResults();
        $pages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $pages);
        $records = $query->orderBy('prescriptions.prescription_date', 'DESC')->orderBy('prescriptions.id', 'DESC')->get($pageSize, ($page - 1) * $pageSize)->getResultArray();
        $items = [];
        $ids = array_map(static fn (array $record): int => (int) $record['id'], $records);

        if ($ids !== []) {
            foreach (db_connect()->table('prescription_items')->select('prescription_items.*')->whereIn('prescription_id', $ids)->orderBy('id', 'ASC')->get()->getResultArray() as $item) {
                $items[(int) $item['prescription_id']][] = $this->normalizeItem($item);
            }
        }
        $rows = array_map(function (array $record) use ($items): array {
            $row = $this->normalize($record, $items[(int) $record['id']] ?? []);
            $row['medicine_duration'] = $record['medicine_duration'] === null || trim((string) ($record['medicine_duration'] ?? '')) === '' ? null : trim((string) $record['medicine_duration']);
            return $row;
        }, $records);
        if ($ids !== []) {
            $history = [];
            foreach (db_connect()->table('prescription_clarifications')
                ->whereIn('prescription_id', $ids)
                ->orderBy('requested_at', 'DESC')
                ->orderBy('id', 'DESC')
                ->get()->getResultArray() as $clarification) {
                $history[(int) $clarification['prescription_id']][] = $this->normalizeClarification($clarification);
            }
            $rows = array_map(static function (array $row) use ($history): array {
                $row['clarification_history'] = $history[$row['id']] ?? [];
                $latest = $row['clarification_history'][0] ?? null;
                if (is_array($latest)) {
                    $row['clarification_status'] = $latest['status'];
                    $row['clarification_status_label'] = $latest['status_label'];
                    if ($row['status'] === 'clarification_requested') {
                        $row['review_state'] = 'Clarification requested';
                    } elseif (in_array($latest['status'], ['doctor_responded', 'prescription_updated'], true)) {
                        $row['review_state'] = $latest['status_label'];
                    }
                }
                return $row;
            }, $rows);
        }
        $doctors = db_connect()->table('doctors')->select('id, first_name, last_name, doctor_code')->where('clinic_id', $clinicId)->orderBy('first_name', 'ASC')->orderBy('last_name', 'ASC')->get()->getResultArray();

        return ['prescriptions' => $rows, 'doctors' => $doctors, 'view' => $view, 'status_counts' => $statusCounts, 'filters' => ['search' => $search, 'status' => $status, 'review_state' => $reviewState, 'doctor_id' => $doctorId, 'from' => $this->toDisplayDate($from), 'to' => $this->toDisplayDate($to), 'page_size' => $pageSize], 'pagination' => ['page' => $page, 'pages' => $pages, 'page_size' => $pageSize, 'total' => $total]];
    }

    /**
     * Count the reviewable prescription statuses for the assigned clinic.
     * Pending review is the existing UI grouping of draft and submitted.
     *
     * @return array<string, int>
     */
    private function statusCounts(int $clinicId): array
    {
        $counts = array_fill_keys(array_keys(TransitionContract::PRESCRIPTION_REVIEW), 0);
        $rows = db_connect()->table('prescriptions')
            ->select('prescriptions.status, COUNT(DISTINCT prescriptions.id) AS status_count', false)
            ->join('patients', 'patients.id = prescriptions.patient_id')
            ->join('doctors', 'doctors.id = prescriptions.doctor_id')
            ->where('prescriptions.clinic_id', $clinicId)
            ->where('patients.clinic_id', $clinicId)
            ->where('doctors.clinic_id', $clinicId)
            ->whereIn('prescriptions.status', array_keys($counts))
            ->groupBy('prescriptions.status')
            ->get()->getResultArray();

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');

            if (array_key_exists($status, $counts)) {
                $counts[$status] = (int) ($row['status_count'] ?? 0);
            }
        }

        return [
            'total' => array_sum($counts),
            'pending' => $counts['draft'] + $counts['submitted'],
            'approved' => $counts['approved'],
            'clarification_requested' => $counts['clarification_requested'],
            'rejected' => $counts['rejected'],
            'cancelled' => $counts['cancelled'],
        ];
    }

    /**
     * Persist pharmacist dispensing quantities without changing clinical prescription fields.
     *
     * @param array<int|string, mixed> $submittedItems
     * @return array<string, mixed>
     */
    public function saveDispensingQuantities(int $prescriptionId, array $submittedItems): array
    {
        $clinicId = $this->scope->clinicId();

        if ($clinicId === null) {
            return ['http' => 403, 'body' => ['status' => false, 'msg' => 'Pharmacy clinic scope is not assigned.']];
        }
        $prescription = $this->scope->prescription($prescriptionId);

        if ($prescription === null) {
            return $this->result(404, 'Prescription not found in the assigned clinic.');
        }
        if (! in_array((string) ($prescription['status'] ?? ''), ['draft', 'submitted', 'approved'], true)) {
            return $this->result(422, 'Dispensing quantities cannot be changed for this prescription.', ['items' => 'The prescription is not editable.']);
        }
        if (db_connect()->table('medicine_orders')->where('prescription_id', $prescriptionId)->where('clinic_id', $clinicId)->countAllResults() > 0) {
            return $this->result(409, 'Dispensing quantities cannot be changed after the medicine order is created.', ['items' => 'The medicine order already exists.']);
        }

        $items = db_connect()->table('prescription_items')->select('prescription_items.*')->where('prescription_id', $prescriptionId)->orderBy('id', 'ASC')->get()->getResultArray();
        if ($items === []) {
            return $this->result(422, 'At least one prescribed medicine is required.', ['items' => 'At least one prescribed medicine is required.']);
        }
        [$quantities, $errors] = $this->validatedQuantities($items, $submittedItems);

        if ($errors !== []) {
            return $this->result(422, 'Enter a valid dispensing quantity for every medicine.', $errors);
        }
        $now = date('Y-m-d H:i:s');
        $userId = (int) (($this->scope->user() ?? [])['id'] ?? 0);
        $db = db_connect();
        $db->transBegin();
        $changes = [];

        try {
            foreach ($items as $item) {
                $itemId = (int) $item['id'];
                $old = $item['dispensing_quantity'] === null ? null : (int) $item['dispensing_quantity'];
                $new = $quantities[$itemId];
                $changes[] = ['prescription_item_id' => $itemId, 'previous_quantity' => $old, 'new_quantity' => $new];
                $updated = $db->table('prescription_items')->where('id', $itemId)->where('prescription_id', $prescriptionId)->update(['dispensing_quantity' => $new, 'dispensing_quantity_updated_by' => $userId > 0 ? $userId : null, 'dispensing_quantity_updated_at' => $now, 'updated_at' => $now]);

                if ($updated === false) {
                    throw new \RuntimeException('Dispensing quantity update failed.');
                }
            }
            if (! $db->transStatus()) {
                throw new \RuntimeException('Dispensing quantity transaction failed.');
            }
            $db->transCommit();
            $this->scope->audit('pharmacy.prescription_dispensing_quantity_updated', ['prescription_id' => $prescriptionId, 'actor_user_id' => $userId, 'changes' => $changes]);

            return ['http' => 200, 'body' => ['status' => true, 'msg' => 'Dispensing quantities saved.', 'data' => ['prescription_id' => $prescriptionId, 'updated_at' => $now]]];
        } catch (\Throwable $exception) {
            $db->transRollback();
            log_message('error', 'Pharmacy dispensing quantity update failed prescription_id=' . $prescriptionId . ' clinic_id=' . $clinicId . ' error=' . $exception->getMessage());

            return $this->result(500, 'Dispensing quantities could not be saved safely.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function normalize(array $record, array $items): array
    {
        $patientName = trim(implode(' ', array_filter([$record['patient_first_name'] ?? '', $record['patient_middle_name'] ?? '', $record['patient_last_name'] ?? ''])));
        $doctorName = trim(implode(' ', array_filter([$record['doctor_first_name'] ?? '', $record['doctor_last_name'] ?? ''])));
        $status = (string) ($record['status'] ?? 'draft');
        $reviewState = in_array($status, self::REVIEW_STATES['pending'], true) ? 'Pending review' : ($status === 'clarification_requested' ? 'Clarification requested' : 'Final');
        $reviewDate = $this->toDisplayDate(substr((string) ($record['updated_at'] ?? ''), 0, 10));
        if ($reviewDate === '') {
            $reviewDate = $this->toDisplayDate(substr((string) ($record['created_at'] ?? ''), 0, 10));
        }
        $record['medicine_duration'] = $record['medicine_duration'] === null || trim((string) ($record['medicine_duration'] ?? '')) === '' ? null : trim((string) $record['medicine_duration']);
        $reviewDateLabel = match ($status) {
            'approved' => 'Approved',
            'rejected' => 'Rejected',
            'cancelled' => 'Cancelled',
            'submitted' => 'Submitted',
            default => 'Last updated',
        };

        return ['id' => (int) $record['id'], 'prescription_number' => trim((string) ($record['prescription_number'] ?? '')) ?: 'Not recorded', 'prescription_date' => $this->toDisplayDate((string) ($record['prescription_date'] ?? '')), 'notes' => (string) ($record['notes'] ?? ''), 'status' => $status, 'status_label' => ucwords(str_replace('_', ' ', $status)), 'review_state' => $reviewState, 'review_date_label' => $reviewDateLabel, 'review_date' => $reviewDate ?: 'Not recorded', 'patient_id' => (int) ($record['patient_id'] ?? 0), 'patient_name' => $patientName ?: 'Patient not recorded', 'patient_number' => trim((string) ($record['registration_number'] ?? '')) ?: 'Not recorded', 'patient_mobile' => trim(implode(' ', array_filter([(string) ($record['patient_country_code'] ?? ''), (string) ($record['patient_mobile_number'] ?? '')]))) ?: 'Not recorded', 'doctor_id' => (int) ($record['doctor_id'] ?? 0), 'doctor_name' => $doctorName ?: 'Doctor not recorded', 'doctor_code' => trim((string) ($record['doctor_code'] ?? '')) ?: 'Not recorded', 'medicine_count' => count($items), 'items' => $items, 'clarification_history' => [], 'created_at' => (string) ($record['created_at'] ?? ''), 'updated_at' => (string) ($record['updated_at'] ?? ''), 'can_review' => in_array($status, array_merge(self::REVIEW_STATES['pending'], self::REVIEW_STATES['clarification']), true), 'can_edit_dispensing_quantity' => $status === 'approved' && empty($record['order_id'])];
    }

    private function normalizeClarification(array $row): array
    {
        $status = (string) ($row['status'] ?? 'requested');
        $labels = [
            'requested' => 'Clarification requested',
            'doctor_responded' => 'Doctor responded',
            'prescription_updated' => 'Prescription updated',
            'confirmed' => 'Confirmed',
            'rejected' => 'Rejected',
            'closed' => 'Closed',
        ];
        return [
            'id' => (int) ($row['id'] ?? 0),
            'status' => $status,
            'status_label' => $labels[$status] ?? ucwords(str_replace('_', ' ', $status)),
            'pharmacy_message' => trim((string) ($row['pharmacy_message'] ?? '')),
            'doctor_response' => trim((string) ($row['doctor_response'] ?? '')),
            'doctor_action' => trim((string) ($row['doctor_action'] ?? '')),
            'requested_at' => (string) ($row['requested_at'] ?? ''),
            'responded_at' => (string) ($row['responded_at'] ?? ''),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeItem(array $item): array
    {
        $duration = $item['duration'] ?? null;
        return ['id' => (int) ($item['id'] ?? 0), 'prescription_item_id' => (int) ($item['id'] ?? 0), 'medicine_id' => $item['medicine_id'] === null || $item['medicine_id'] === '' ? null : (int) $item['medicine_id'], 'medicine_name' => trim((string) ($item['medicine_name'] ?? '')) ?: 'Unnamed medicine', 'duration' => $duration === null || trim((string) $duration) === '' ? null : trim((string) $duration), 'duration_unit' => trim((string) ($item['duration_unit'] ?? '')), 'frequency' => trim((string) ($item['frequency'] ?? '')) ?: 'Not recorded', 'dose' => trim((string) ($item['dosage'] ?? '')) ?: 'Not recorded', 'intake' => trim((string) ($item['instructions'] ?? '')) ?: 'Not recorded', 'note' => trim((string) ($item['notes'] ?? '')) ?: 'Not recorded', 'prescription_quantity' => $item['quantity'] === null ? null : (float) $item['quantity'], 'dispensing_quantity' => $item['dispensing_quantity'] === null ? null : (int) $item['dispensing_quantity'], 'dispensing_quantity_updated_by' => (int) ($item['dispensing_quantity_updated_by'] ?? 0), 'dispensing_quantity_updated_at' => (string) ($item['dispensing_quantity_updated_at'] ?? '')];
    }

    /** @return array{0: array<int, int>, 1: array<string, string>} */
    private function validatedQuantities(array $items, array $submittedItems): array
    {
        $quantities = [];
        $errors = [];
        $knownIds = [];

        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            $knownIds[$itemId] = true;
        }

        $seenIds = [];
        foreach ($submittedItems as $index => $entry) {
            $postedItemId = is_array($entry) ? (int) ($entry['prescription_item_id'] ?? 0) : 0;
            $raw = is_array($entry) ? trim((string) ($entry['quantity'] ?? '')) : '';

            if (! isset($knownIds[$postedItemId])) {
                $errors['items.' . $index . '.quantity'] = 'The selected medicine does not match the prescription.';
                continue;
            }
            if (isset($seenIds[$postedItemId])) {
                $errors['items.' . $index . '.quantity'] = 'The prescription medicine was submitted more than once.';
                continue;
            }
            $seenIds[$postedItemId] = true;
            if (! preg_match('/^[1-9][0-9]*$/', $raw)) {
                $errors['items.' . $index . '.quantity'] = 'Dispensing quantity must be a positive whole number.';
                continue;
            }
            $quantity = (int) $raw;
            if ($quantity > self::MAX_DISPENSING_QUANTITY) {
                $errors['items.' . $index . '.quantity'] = 'Dispensing quantity cannot exceed ' . self::MAX_DISPENSING_QUANTITY . '.';
                continue;
            }
            $quantities[$postedItemId] = $quantity;
        }

        foreach ($knownIds as $itemId => $_known) {
            if (! isset($seenIds[$itemId])) {
                $errors['items.' . count($seenIds) . '.quantity'] = 'A dispensing quantity is required for every prescribed medicine.';
                break;
            }
        }

        return [$quantities, $errors];
    }

    private function formatDuration(mixed $value, mixed $unit): string
    {
        $duration = trim((string) ($value ?? ''));
        $durationUnit = trim((string) ($unit ?? ''));

        if ($duration === '') {
            return 'Not recorded';
        }
        if ($durationUnit === '') {
            return $duration;
        }
        if (! is_numeric($duration)) {
            return strcasecmp($duration, $durationUnit) === 0 || str_ends_with(strtolower($duration), strtolower($durationUnit))
                ? $duration
                : trim($duration . ' ' . $durationUnit);
        }
        $number = (float) $duration;
        $singular = strtolower(rtrim($durationUnit, 's'));
        $label = $number === 1.0
            ? $singular
            : (in_array($singular, ['day', 'week', 'month'], true) ? $singular . 's' : $durationUnit);

        return $duration . ' ' . $label;
    }

    private function toIsoDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }
        $date = DateTimeImmutable::createFromFormat('!d-m-Y', $value);

        return $date !== false && $date->format('d-m-Y') === $value ? $date->format('Y-m-d') : null;
    }

    private function toDisplayDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->format('Y-m-d') === $value ? $date->format('d-m-Y') : '';
    }

    /**
     * @return array<string, mixed>
     */
    private function emptyResult(string $view, int $pageSize): array
    {
        return ['prescriptions' => [], 'doctors' => [], 'view' => $view, 'status_counts' => ['total' => 0, 'pending' => 0, 'approved' => 0, 'clarification_requested' => 0, 'rejected' => 0, 'cancelled' => 0], 'filters' => ['search' => '', 'status' => '', 'review_state' => '', 'doctor_id' => 0, 'from' => '', 'to' => '', 'page_size' => $pageSize], 'pagination' => ['page' => 1, 'pages' => 1, 'page_size' => $pageSize, 'total' => 0]];
    }

    private function result(int $http, string $msg, array $errors = []): array
    {
        return ['http' => $http, 'body' => ['status' => false, 'msg' => $msg] + ($errors === [] ? [] : ['errors' => $errors])];
    }
}
