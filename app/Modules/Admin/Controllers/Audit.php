<?php

namespace Admin\Controllers;

use App\Controllers\BaseAdminController;
use Throwable;

class Audit extends BaseAdminController
{
    public function index(): string
    {
        $query = db_connect()->table('audit_logs')->select('audit_logs.*')->orderBy('id', 'DESC');
        $clinicId = (int) ($this->user['clinic_id'] ?? session('clinic_id') ?? 0);
        if ($clinicId > 0) {
            $query->where('clinic_id', $clinicId);
        }
        $events = $this->presentEvents($query->limit(100)->get()->getResultArray(), $clinicId);
        $summary = [
            'count' => count($events),
            'security' => count(array_filter($events, static fn (array $event): bool => str_contains(strtolower((string) ($event['action'] ?? '')), 'security') || str_contains(strtolower((string) ($event['action'] ?? '')), 'auth'))),
            'today' => count(array_filter($events, static fn (array $event): bool => str_starts_with((string) ($event['occurred_at'] ?? ''), date('Y-m-d')))),
        ];
        return view(ADMIN_VIEW . 'pages/audit/index', ['events' => $events, 'summary' => $summary]);
    }

    private function presentEvents(array $events, int $clinicId): array
    {
        $ids = array_fill_keys(['user', 'doctor', 'patient', 'appointment', 'prescription', 'order', 'invoice', 'clinic', 'medicine', 'lead', 'followup', 'provider', 'notification'], []);
        foreach ($events as $event) {
            $actorId = (int) ($event['actor_user_id'] ?? 0);
            if ($actorId > 0) {
                $ids['user'][$actorId] = $actorId;
            }
            $this->collectIds($this->decodeContext($event['context'] ?? null), $ids);
        }
        $lookups = [
            'user' => $this->loadRows('users', array_keys($ids['user']), 'id, username, email, doctor_id'),
            'doctor' => $this->loadRows('doctors', array_keys($ids['doctor']), 'id, doctor_code, first_name, last_name, registration_number, clinic_id', $clinicId),
            'patient' => $this->loadRows('patients', array_keys($ids['patient']), 'id, registration_number, first_name, middle_name, last_name, clinic_id', $clinicId),
            'appointment' => $this->loadRows('appointments', array_keys($ids['appointment']), 'id, appointment_number, patient_id, doctor_id, clinic_id', $clinicId),
            'prescription' => $this->loadRows('prescriptions', array_keys($ids['prescription']), 'id, prescription_number, patient_id, doctor_id, clinic_id', $clinicId),
            'order' => $this->loadRows('medicine_orders', array_keys($ids['order']), 'id, order_number, patient_id, clinic_id', $clinicId),
            'invoice' => $this->loadRows('invoices', array_keys($ids['invoice']), 'id, invoice_number, patient_id, clinic_id', $clinicId),
            'clinic' => $this->loadRows('clinics', array_keys($ids['clinic']), 'id, clinic_name, clinic_code'),
            'medicine' => $this->loadRows('medicines', array_keys($ids['medicine']), 'id, medicine_name, medicine_code'),
            'lead' => $this->loadRows('crm_leads', array_keys($ids['lead']), 'id, lead_number, full_name, clinic_id', $clinicId),
            'followup' => $this->loadRows('crm_lead_followups', array_keys($ids['followup']), 'id, follow_up_number, lead_id, clinic_id', $clinicId),
            'provider' => $this->loadRows('delivery_providers', array_keys($ids['provider']), 'id, provider_name, provider_code'),
            'notification' => $this->loadRows('patient_notifications', array_keys($ids['notification']), 'id, title, status, patient_id', $clinicId),
        ];
        $lookups['notification'] += $this->loadRows('pharmacy_notifications', array_keys($ids['notification']), 'id, event_key, type', $clinicId);
        foreach ($lookups['user'] as $user) {
            $doctorId = (int) ($user['doctor_id'] ?? 0);
            if ($doctorId > 0) {
                $lookups['user'][$user['id']]['doctor'] = $lookups['doctor'][$doctorId] ?? null;
            }
        }
        foreach ($events as &$event) {
            $context = $this->decodeContext($event['context'] ?? null);
            $event['display_action'] = $this->readableAction((string) ($event['action'] ?? ''));
            $event['actor_id'] = (int) ($event['actor_user_id'] ?? 0);
            $event['actor_display'] = $this->userLabel($event['actor_id'], $lookups);
            $event['actor_email'] = $this->userEmail($event['actor_id'], $lookups);
            $event['clinic_display'] = $this->clinicLabel((int) ($event['clinic_id'] ?? 0), $lookups);
            $entity = $this->entityInfo($context, $lookups);
            $event['entity_type_display'] = $entity['type'];
            $event['entity_name_display'] = $entity['name'];
            $event['entity_display'] = $entity['type'] . ': ' . $entity['name'];
            $event['occurred_at_display'] = $this->formatDateTime($event['occurred_at'] ?? null);
            $event['detail_payload'] = $this->detailPayload($event, $context, $lookups);
        }
        unset($event);
        return $events;
    }

    private function loadRows(string $table, array $ids, string $select, int $clinicId = 0): array
    {
        if ($ids === []) {
            return [];
        }
        try {
            $builder = db_connect()->table($table)->select($select)->whereIn('id', array_map('intval', $ids));
            if ($clinicId > 0 && in_array($table, ['doctors', 'patients', 'appointments', 'prescriptions', 'medicine_orders', 'invoices', 'crm_leads', 'crm_lead_followups', 'patient_notifications', 'pharmacy_notifications'], true)) {
                $builder->where($table . '.clinic_id', $clinicId);
            }
            $rows = $builder->get()->getResultArray();
        } catch (Throwable $e) {
            log_message('debug', 'Audit presentation lookup skipped for {table}: {message}', ['table' => $table, 'message' => $e->getMessage()]);
            return [];
        }
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[(int) $row['id']] = $row;
        }
        return $indexed;
    }

    private function collectIds(array $context, array &$ids): void
    {
        foreach ($context as $key => $value) {
            if (is_array($value)) {
                $this->collectIds($value, $ids);
                continue;
            }
            if (!is_numeric($value)) {
                continue;
            }
            $type = $this->idType((string) $key);
            if ($type !== null && (int) $value > 0 && isset($ids[$type])) {
                $ids[$type][(int) $value] = (int) $value;
            }
        }
    }

    private function idType(string $key): ?string
    {
        return [
            'user_id' => 'user', 'user' => 'user', 'actor' => 'user', 'actor_id' => 'user', 'actor_user_id' => 'user', 'created_by' => 'user', 'updated_by' => 'user', 'assigned_user_id' => 'user',
            'doctor_id' => 'doctor', 'patient_id' => 'patient', 'appointment_id' => 'appointment', 'prescription_id' => 'prescription',
            'order_id' => 'order', 'medicine_order_id' => 'order', 'invoice_id' => 'invoice', 'clinic_id' => 'clinic',
            'medicine_id' => 'medicine', 'lead_id' => 'lead', 'follow_up_id' => 'followup', 'provider_id' => 'provider', 'delivery_provider_id' => 'provider', 'notification_id' => 'notification',
        ][strtolower($key)] ?? null;
    }

    private function decodeContext(?string $context): array
    {
        if (!$context) {
            return [];
        }
        $decoded = json_decode($context, true);
        return is_array($decoded) ? $decoded : ['value' => $context];
    }

    private function userLabel(int $id, array $lookups): string
    {
        if ($id <= 0) {
            return 'System';
        }
        $user = $lookups['user'][$id] ?? null;
        if (!$user) {
            return 'Unknown user';
        }
        $doctor = $user['doctor'] ?? null;
        $name = $doctor ? trim(implode(' ', array_filter([$doctor['first_name'] ?? '', $doctor['middle_name'] ?? '', $doctor['last_name'] ?? '']))) : '';
        return $name !== '' ? 'Dr. ' . $name : ((string) ($user['username'] ?? '') ?: 'Unknown user');
    }

    private function userEmail(int $id, array $lookups): string
    {
        return trim((string) ($lookups['user'][$id]['email'] ?? ''));
    }

    private function clinicLabel(int $id, array $lookups): string
    {
        if ($id <= 0) {
            return 'All permitted clinics';
        }
        $clinic = $lookups['clinic'][$id] ?? null;
        return $clinic ? trim((string) $clinic['clinic_name']) . (($clinic['clinic_code'] ?? '') !== '' ? ' (' . $clinic['clinic_code'] . ')' : '') : 'Clinic unavailable';
    }

    private function entityInfo(array $context, array $lookups): array
    {
        foreach (['patient', 'doctor', 'appointment', 'prescription', 'order', 'invoice', 'lead', 'followup', 'medicine', 'provider', 'notification', 'clinic'] as $type) {
            foreach ($context as $key => $value) {
                if ($this->idType((string) $key) === $type && is_numeric($value)) {
                    return ['type' => ucfirst($type === 'followup' ? 'follow-up' : $type), 'name' => $this->labelForId($type, (int) $value, $lookups, false)];
                }
            }
        }
        return ['type' => 'System', 'name' => 'General system event'];
    }

    private function labelForId(string $type, int $id, array $lookups, bool $includeType = true): string
    {
        $row = $lookups[$type][$id] ?? null;
        if (!$row) {
            return match ($type) { 'patient' => 'Deleted patient', 'doctor' => 'Deleted doctor', default => ucfirst($type) . ' unavailable' };
        }
        $name = match ($type) {
            'patient' => trim(implode(' ', array_filter([$row['first_name'] ?? '', $row['middle_name'] ?? '', $row['last_name'] ?? '']))) . (($row['registration_number'] ?? '') !== '' ? ' (' . $row['registration_number'] . ')' : ''),
            'doctor' => trim(implode(' ', array_filter([$row['first_name'] ?? '', $row['middle_name'] ?? '', $row['last_name'] ?? '']))) . (($row['doctor_code'] ?? '') !== '' ? ' (' . $row['doctor_code'] . ')' : ''),
            'appointment' => $row['appointment_number'] ?? ('Record unavailable'),
            'prescription' => $row['prescription_number'] ?? ('Record unavailable'),
            'order' => $row['order_number'] ?? ('Record unavailable'),
            'invoice' => $row['invoice_number'] ?? ('Record unavailable'),
            'lead' => trim((string) ($row['full_name'] ?? '')) . (($row['lead_number'] ?? '') !== '' ? ' (' . $row['lead_number'] . ')' : ''),
            'followup' => $row['follow_up_number'] ?? 'Follow-up record',
            'medicine' => trim((string) ($row['medicine_name'] ?? '')) . (($row['medicine_code'] ?? '') !== '' ? ' (' . $row['medicine_code'] . ')' : ''),
            'provider' => trim((string) ($row['provider_name'] ?? '')) . (($row['provider_code'] ?? '') !== '' ? ' (' . $row['provider_code'] . ')' : ''),
            'notification' => trim((string) ($row['title'] ?? $row['type'] ?? 'Notification event')),
            'clinic' => $row['clinic_name'] ?? ('Clinic unavailable'),
            default => 'Record unavailable',
        };
        return $includeType ? ucfirst($type) . ': ' . $name : $name;
    }

    private function detailPayload(array $event, array $context, array $lookups): array
    {
        $fields = [];
        $technical = ['route' => $event['route'] ?? null, 'correlation_id' => $event['correlation_id'] ?? null];
        $technicalKeys = ['ip', 'ip_address', 'user_agent', 'browser', 'device', 'route', 'request_id', 'session_id'];
        $changes = [];
        foreach ($context as $key => $value) {
            $normalized = strtolower((string) $key);
            if (in_array($normalized, $technicalKeys, true)) {
                $technical[$normalized] = $this->displayValue($value, $key, $lookups);
                continue;
            }
            if (in_array($normalized, ['old', 'new', 'before', 'after', 'previous', 'current'], true) && is_array($value)) {
                continue;
            }
            if (str_starts_with($normalized, 'old_') || str_starts_with($normalized, 'new_') || str_starts_with($normalized, 'previous_')) {
                continue;
            }
            $display = $normalized === 'related_entity_id' ? 'Related ' . $this->humanizeKey((string) ($context['related_entity_type'] ?? 'record')) . ' record' : $this->displayValue($value, (string) $key, $lookups);
            $fields[] = ['label' => $this->humanizeKey((string) $key), 'value' => $display];
        }
        $before = $context['old'] ?? $context['before'] ?? $context['previous'] ?? [];
        $after = $context['new'] ?? $context['after'] ?? $context['current'] ?? [];
        if (is_array($before) || is_array($after)) {
            foreach (array_unique(array_merge(array_keys((array) $before), array_keys((array) $after))) as $key) {
                $old = $before[$key] ?? null;
                $new = $after[$key] ?? null;
                if ($old !== $new) {
                    $changes[] = ['field' => $this->humanizeKey((string) $key), 'old' => $this->displayValue($old, (string) $key, $lookups), 'new' => $this->displayValue($new, (string) $key, $lookups)];
                }
            }
        }
        foreach ($context as $key => $value) {
            $normalized = strtolower((string) $key);
            if (str_starts_with($normalized, 'old_')) {
                $field = substr((string) $key, 4);
                $newKey = 'new_' . $field;
                if (array_key_exists($newKey, $context) && $value !== $context[$newKey]) {
                    $changes[] = ['field' => $this->humanizeKey($field), 'old' => $this->displayValue($value, $field, $lookups), 'new' => $this->displayValue($context[$newKey], $field, $lookups)];
                }
            }
        }
        return ['action' => $event['display_action'], 'actor' => $event['actor_display'], 'actor_email' => $event['actor_email'] ?? '', 'entity' => $event['entity_display'], 'entity_type' => $event['entity_type_display'], 'entity_name' => $event['entity_name_display'], 'time' => $event['occurred_at_display'], 'fields' => $fields, 'changes' => $changes, 'technical' => array_filter($technical, static fn ($value): bool => $value !== null && $value !== '')];
    }

    private function displayValue(mixed $value, string $key, array $lookups): string
    {
        if (preg_match('/(?:password|secret|token|api[_ ]?key|credential|cookie)/i', $key)) {
            return '[Protected]';
        }
        if ($value === null || $value === '') {
            return 'Not recorded';
        }
        $type = $this->idType($key);
        if ($type !== null && is_numeric($value)) {
            return $this->labelForId($type, (int) $value, $lookups, false);
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (is_array($value)) {
            $parts = [];
            foreach ($value as $childKey => $childValue) {
                $parts[] = $this->humanizeKey((string) $childKey) . ': ' . $this->displayValue($childValue, (string) $childKey, $lookups);
            }
            return implode('; ', $parts);
        }
        return (string) $value;
    }

    private function humanizeKey(string $key): string
    {
        $labels = ['user_id' => 'User', 'actor' => 'Actor', 'actor_id' => 'Actor', 'actor_user_id' => 'Actor', 'doctor_id' => 'Doctor', 'patient_id' => 'Patient', 'appointment_id' => 'Appointment', 'prescription_id' => 'Prescription', 'order_id' => 'Order', 'medicine_order_id' => 'Order', 'invoice_id' => 'Invoice', 'clinic_id' => 'Clinic', 'medicine_id' => 'Medicine', 'lead_id' => 'Lead', 'follow_up_id' => 'Follow-up', 'provider_id' => 'Provider', 'delivery_provider_id' => 'Delivery provider', 'notification_id' => 'Notification', 'related_entity_id' => 'Related record'];
        if (isset($labels[strtolower($key)])) {
            return $labels[strtolower($key)];
        }
        return ucfirst(str_replace('_', ' ', preg_replace('/\bid\b/i', 'ID', trim($key)) ?? $key));
    }

    private function readableAction(string $action): string
    {
        $known = ['doctor.patient_view' => 'Patient record viewed', 'doctor.appointment_view' => 'Appointment viewed', 'doctor.appointment_status' => 'Appointment status changed', 'payment_reconciled' => 'Payment reconciled', 'permission_denied' => 'Permission denied', 'credential_rotated' => 'Credential rotated'];
        if (isset($known[$action])) {
            return $known[$action];
        }
        $label = str_replace(['.', '-'], ' ', trim($action));
        $label = preg_replace('/\b(view|read)\b/i', 'viewed', $label) ?? $label;
        $label = preg_replace('/\b(create)\b/i', 'created', $label) ?? $label;
        $label = ucwords(str_replace('_', ' ', $label)) ?: 'Audit event';
        return str_replace(['Crm', 'Api'], ['CRM', 'API'], $label);
    }

    private function formatDateTime(?string $value): string
    {
        $timestamp = $value ? strtotime($value) : false;
        return $timestamp ? date('d M Y, h:i A', $timestamp) : 'Time unavailable';
    }
}
