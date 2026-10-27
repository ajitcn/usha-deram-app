<?php

namespace Admin\Controllers;

use App\Controllers\BaseAdminController;
use DateTimeImmutable;
use DateTimeZone;

final class Dashboard extends BaseAdminController
{
    public function index(): string
    {
        $query = $this->request->getGet();
        $range = $this->range(is_array($query) ? $query : []);
        $scope = (int) ($this->user['clinic_id'] ?? session('clinic_id') ?? 0);
        return view(ADMIN_VIEW . 'pages/dashboard', ['range' => $range, 'scopeClinicId' => $scope, 'metrics' => $this->metrics($range, $scope), 'dashboardPermissions' => $this->permissions]);
    }

    private function metrics(array $range, int $scope): array
    {
        $db = db_connect();
        $patients = $this->scoped($db->table('patients')->where('status', 1), 'clinic_id', $scope);
        $doctors = $this->scoped($db->table('doctors')->where('status', 1), 'clinic_id', $scope);
        $allDoctors = $this->scoped($db->table('doctors'), 'clinic_id', $scope);
        $clinics = $this->scoped($db->table('clinics')->where('status', 1), 'id', $scope);
        // Query builders reset their predicates after get(). Build a fresh query for
        // every aggregate so no breakdown or trend can escape the selected range.
        $appointments = fn () => $this->dateScoped($this->scoped($db->table('appointments'), 'clinic_id', $scope), 'appointment_date', $range);
        $consultations = fn () => $this->dateScoped($this->scoped($db->table('consultations'), 'clinic_id', $scope), 'consultation_date', $range);
        $prescriptions = fn () => $this->dateScoped($this->scoped($db->table('prescriptions'), 'clinic_id', $scope), 'prescription_date', $range);
        $orders = fn () => $this->dateScoped($this->scoped($db->table('medicine_orders'), 'clinic_id', $scope), 'created_at', $range);
        $invoices = fn () => $this->dateScoped($this->scoped($db->table('invoices'), 'clinic_id', $scope), 'created_at', $range);
        $totalRevenue = (float) ($invoices()->selectSum('total')->get()->getRow('total') ?? 0);
        $paidAmount = 0.0;
        if ($db->tableExists('pharmacy_payment_transactions')) {
            $payments = fn () => $this->dateScoped($this->scoped($db->table('pharmacy_payment_transactions pt')->join('medicine_orders mo', 'mo.id = pt.order_id'), 'mo.clinic_id', $scope), 'mo.created_at', $range);
            $paidAmount = (float) ($payments()->selectSum('pt.amount')->where('pt.status', 'succeeded')->where('pt.transaction_type', 'payment')->get()->getRow('amount') ?? 0);
            $paidAmount -= (float) ($payments()->selectSum('pt.amount')->where('pt.status', 'succeeded')->where('pt.transaction_type', 'refund')->get()->getRow('amount') ?? 0);
        } else {
            $paidAmount = (float) ($this->dateScoped($this->scoped($db->table('invoices')->where('payment_status', 'paid'), 'clinic_id', $scope), 'created_at', $range)->selectSum('total')->get()->getRow('total') ?? 0);
        }
        $breakdowns = ['appointments' => $this->grouped($appointments(), 'status'), 'consultations' => $this->grouped($consultations(), 'status'), 'prescriptions' => $this->grouped($prescriptions(), 'status'), 'orders' => $this->grouped($orders(), 'status'), 'payments' => $this->grouped($orders(), 'payment_status')];
        $reviews = ($breakdowns['prescriptions']['submitted'] ?? 0) + ($breakdowns['prescriptions']['clarification_requested'] ?? 0);
        $pendingConsultations = array_sum(array_diff_key($breakdowns['consultations'], array_flip(['completed', 'cancelled'])));
        $orderStatuses = $breakdowns['orders'];
        $paymentStatuses = $breakdowns['payments'];
        return [
            'kpis' => ['total_patients' => $patients->countAllResults(), 'new_patients' => $this->dateScoped($this->scoped($db->table('patients'), 'clinic_id', $scope), 'created_at', $range)->countAllResults(), 'total_doctors' => $allDoctors->countAllResults(), 'active_doctors' => $doctors->countAllResults(), 'total_pharmacies' => $clinics->countAllResults(), 'total_appointments' => array_sum($breakdowns['appointments']), 'completed_consultations' => $breakdowns['consultations']['completed'] ?? 0, 'pending_consultations' => $pendingConsultations, 'total_prescriptions' => array_sum($breakdowns['prescriptions']), 'pending_prescriptions' => $breakdowns['prescriptions']['submitted'] ?? 0, 'pending_reviews' => $reviews, 'approved_prescriptions' => $breakdowns['prescriptions']['approved'] ?? 0, 'total_orders' => array_sum($orderStatuses), 'pending_orders' => $orderStatuses['pending'] ?? 0, 'processing_orders' => $orderStatuses['processing'] ?? 0, 'completed_orders' => ($orderStatuses['fulfilled'] ?? 0) + ($orderStatuses['completed'] ?? 0), 'unpaid_orders' => ($paymentStatuses['unpaid'] ?? 0) + ($paymentStatuses['pending'] ?? 0), 'partially_paid_orders' => $paymentStatuses['partially_paid'] ?? 0, 'paid_orders' => $paymentStatuses['paid'] ?? 0, 'total_revenue' => $totalRevenue, 'paid_amount' => max(0, $paidAmount), 'outstanding_balance' => max(0, $totalRevenue - $paidAmount)],
            'breakdowns' => $breakdowns,
            'trends' => ['patients' => $this->daily($this->dateScoped($this->scoped($db->table('patients'), 'clinic_id', $scope), 'created_at', $range), 'created_at'), 'appointments' => $this->daily($appointments(), 'appointment_date'), 'orders' => $this->daily($orders(), 'created_at')],
            'recent' => ['appointments' => $this->recentAppointments($range, $scope), 'prescriptions' => $this->recentPrescriptions($range, $scope), 'orders' => $this->recentOrders($range, $scope)],
            'pending_actions' => [['label' => 'Pending pharmacy reviews', 'count' => $reviews, 'url' => 'admin/prescriptions'], ['label' => 'Appointments awaiting completion', 'count' => ($breakdowns['appointments']['in_queue'] ?? 0) + ($breakdowns['appointments']['checked_in'] ?? 0), 'url' => 'admin/appointments'], ['label' => 'Orders awaiting fulfilment', 'count' => ($orderStatuses['pending'] ?? 0) + ($orderStatuses['processing'] ?? 0), 'url' => 'pharmacy/orders'], ['label' => 'Unpaid or pending orders', 'count' => ($paymentStatuses['unpaid'] ?? 0) + ($paymentStatuses['pending'] ?? 0), 'url' => 'pharmacy/billing']],
            'audit_alerts' => $this->auditAlerts($range, $scope),
        ];
    }

    private function grouped($query, string $column): array
    {
        $result = [];
        foreach ($query->select($column . ', COUNT(*) AS total')->groupBy($column)->get()->getResultArray() as $row) $result[trim((string) ($row[$column] ?? '')) ?: 'unknown'] = (int) $row['total'];
        arsort($result);
        return $result;
    }

    private function daily($query, string $column): array
    {
        $expression = $column === 'created_at' ? 'DATE(' . $column . ')' : $column;
        return $query->select($expression . ' AS day, COUNT(*) AS total', false)->groupBy($expression)->orderBy('day', 'ASC')->get()->getResultArray();
    }

    private function recentAppointments(array $range, int $scope): array
    {
        $q = $this->dateScoped($this->scoped(db_connect()->table('appointments a')->select('a.appointment_number,a.appointment_date,a.appointment_time,a.status,p.registration_number,p.first_name,p.last_name,d.first_name AS doctor_first_name,d.last_name AS doctor_last_name')->join('patients p', 'p.id = a.patient_id')->join('doctors d', 'd.id = a.doctor_id'), 'a.clinic_id', $scope), 'a.appointment_date', $range);
        return $q->orderBy('a.appointment_date', 'DESC')->orderBy('a.appointment_time', 'DESC')->limit(6)->get()->getResultArray();
    }

    private function recentPrescriptions(array $range, int $scope): array
    {
        $q = $this->dateScoped($this->scoped(db_connect()->table('prescriptions p')->select('p.prescription_number,p.prescription_date,p.status,pa.registration_number,pa.first_name,pa.last_name'), 'p.clinic_id', $scope)->join('patients pa', 'pa.id = p.patient_id'), 'p.prescription_date', $range);
        return $q->orderBy('p.prescription_date', 'DESC')->limit(6)->get()->getResultArray();
    }

    private function recentOrders(array $range, int $scope): array
    {
        $q = $this->dateScoped($this->scoped(db_connect()->table('medicine_orders o')->select('o.order_number,o.created_at,o.status,o.payment_status,i.total,pa.registration_number,pa.first_name,pa.last_name')->join('patients pa', 'pa.id = o.patient_id')->join('invoices i', 'i.order_id = o.id AND i.clinic_id = o.clinic_id', 'left'), 'o.clinic_id', $scope), 'o.created_at', $range);
        return $q->orderBy('o.created_at', 'DESC')->limit(6)->get()->getResultArray();
    }

    private function auditAlerts(array $range, int $scope): int
    {
        $q = $this->dateScoped($this->scoped(db_connect()->table('audit_logs'), 'clinic_id', $scope), 'occurred_at', $range);
        return (int) $q->groupStart()->like('action', 'failed')->orLike('action', 'denied')->groupEnd()->countAllResults();
    }

    private function scoped($query, string $column, int $scope)
    {
        return $scope > 0 ? $query->where($column, $scope) : $query;
    }

    private function dateScoped($query, string $column, array $range)
    {
        $time = in_array($column, ['created_at', 'occurred_at', 'mo.created_at'], true);
        return $query->where($column . ' >=', $range['from'] . ($time ? ' 00:00:00' : ''))->where($column . ' <=', $range['to'] . ($time ? ' 23:59:59' : ''));
    }

    private function range(array $input): array
    {
        $today = new DateTimeImmutable('today', new DateTimeZone((string) (config('App')->appTimezone ?: date_default_timezone_get())));
        $requestedPreset = $this->inputString($input, 'range');
        $allowedPresets = ['today', 'week', 'month', 'custom'];
        $preset = in_array($requestedPreset, $allowedPresets, true) ? $requestedPreset : 'month';
        $from = $today;
        $to = $today;
        if ($preset === 'week') $from = $today->modify('monday this week');
        if ($preset === 'month') $from = $today->modify('first day of this month');
        if ($preset === 'custom') { $from = $this->parseDate($this->inputString($input, 'from')) ?? $today->modify('first day of this month'); $to = $this->parseDate($this->inputString($input, 'to')) ?? $today; }
        if ($to < $from) [$from, $to] = [$to, $from];
        return ['preset' => $preset, 'from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d'), 'from_display' => $from->format('d-m-Y'), 'to_display' => $to->format('d-m-Y')];
    }

    private function inputString(array $input, string $key): string
    {
        $value = $input[$key] ?? null;

        return is_scalar($value) ? trim((string) $value) : '';
    }

    private function parseDate(string $value): ?DateTimeImmutable
    {
        foreach (['!Y-m-d', '!d-m-Y'] as $format) { $date = DateTimeImmutable::createFromFormat($format, trim($value)); if ($date !== false && $date->format($format === '!Y-m-d' ? 'Y-m-d' : 'd-m-Y') === trim($value)) return $date; }
        return null;
    }
}
