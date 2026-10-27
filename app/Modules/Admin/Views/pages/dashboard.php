<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('styles') ?><link rel="stylesheet" href="<?= base_url('assets/css/admin/dashboard.css?v=20261025-dashboard3') ?>"><?= $this->endSection() ?>
<?= $this->section('main-content') ?>
<?php
$kpis = $metrics['kpis'] ?? [];
$breakdowns = $metrics['breakdowns'] ?? [];
$trends = $metrics['trends'] ?? [];
$recent = $metrics['recent'] ?? [];
$money = static fn (float $value): string => '₹' . number_format($value, 2);
$label = static fn (string $value): string => ucwords(str_replace('_', ' ', $value));
$permissions = $dashboardPermissions ?? session('permissions') ?? [];
$can = static fn (string $permission): bool => in_array($permission, $permissions, true);
$appointmentTitle = ($range['preset'] ?? '') === 'today' ? "Today's appointments" : 'Appointments in selected range';
$primaryKpis = [
    ['total_patients', 'Total patients', 'admin/patients', 'bi-people', 'neutral'],
    ['total_appointments', $appointmentTitle, 'admin/appointments', 'bi-calendar-check', 'neutral'],
    ['pending_consultations', 'Pending consultations', 'admin/consultations', 'bi-hourglass-split', 'pending'],
    ['pending_prescriptions', 'Pending prescriptions', 'admin/prescriptions', 'bi-prescription2', 'pending'],
    ['pending_reviews', 'Pending pharmacy reviews', 'admin/prescriptions', 'bi-clipboard2-pulse', 'pending'],
    ['pending_orders', 'Pending orders', 'pharmacy/orders', 'bi-box-seam', 'pending'],
    ['outstanding_balance', 'Outstanding payments', 'pharmacy/billing', 'bi-exclamation-circle', 'outstanding'],
    ['total_revenue', 'Total revenue', 'admin/billing', 'bi-graph-up-arrow', 'revenue'],
];
$secondaryKpiGroups = [
    'People and care' => [['new_patients', 'New patients', 'admin/patients', 'bi-person-plus'], ['total_doctors', 'Total doctors', 'admin/doctors', 'bi-person-badge'], ['active_doctors', 'Active doctors', 'admin/doctors', 'bi-person-check'], ['total_pharmacies', 'Total pharmacies', 'admin/clinics', 'bi-hospital']],
    'Clinical workflow' => [['completed_consultations', 'Completed consultations', 'admin/consultations', 'bi-check2-circle'], ['approved_prescriptions', 'Approved prescriptions', 'admin/prescriptions', 'bi-check2-square'], ['total_prescriptions', 'Total prescriptions', 'admin/prescriptions', 'bi-prescription2']],
    'Orders and payments' => [['total_orders', 'Total medicine orders', 'pharmacy/orders', 'bi-box-seam'], ['processing_orders', 'Processing orders', 'pharmacy/orders', 'bi-arrow-repeat'], ['completed_orders', 'Completed orders', 'pharmacy/orders', 'bi-check-circle'], ['unpaid_orders', 'Unpaid orders', 'pharmacy/billing', 'bi-exclamation-circle'], ['partially_paid_orders', 'Partially paid orders', 'pharmacy/billing', 'bi-circle-half'], ['paid_orders', 'Paid orders', 'pharmacy/billing', 'bi-cash-coin']],
];
?>
<main class="dashboard-container admin-analytics-dashboard" aria-labelledby="admin-dashboard-title">
    <header class="admin-dashboard-header">
        <div><p class="admin-dashboard-eyebrow mb-1">Administration / analytics</p><h1 id="admin-dashboard-title">Admin dashboard</h1><p class="admin-dashboard-muted mb-0">Focused operational metrics for the permitted scope.</p></div>
        <form class="admin-dashboard-filter" method="get" action="<?= base_url('admin') ?>" aria-label="Dashboard date range">
            <div class="admin-dashboard-filter-field"><label for="dashboard-range">Range</label><select id="dashboard-range" name="range" class="form-control form-control-sm"><option value="today" <?= ($range['preset'] ?? '') === 'today' ? 'selected' : '' ?>>Today</option><option value="week" <?= ($range['preset'] ?? '') === 'week' ? 'selected' : '' ?>>This week</option><option value="month" <?= ($range['preset'] ?? '') === 'month' ? 'selected' : '' ?>>This month</option><option value="custom" <?= ($range['preset'] ?? '') === 'custom' ? 'selected' : '' ?>>Custom range</option></select></div>
            <div class="admin-dashboard-filter-field"><label for="dashboard-from">From</label><input id="dashboard-from" class="form-control form-control-sm" type="date" name="from" value="<?= esc($range['from'] ?? '') ?>"></div>
            <div class="admin-dashboard-filter-field"><label for="dashboard-to">To</label><input id="dashboard-to" class="form-control form-control-sm" type="date" name="to" value="<?= esc($range['to'] ?? '') ?>"></div>
            <button class="btn btn-sm admin-dashboard-apply" type="submit"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Apply</button>
        </form>
    </header>
    <p class="admin-dashboard-range">Showing <?= esc($range['from_display'] ?? '') ?> to <?= esc($range['to_display'] ?? '') ?><?= ($scopeClinicId ?? 0) > 0 ? ' · Clinic scoped' : ' · All authorized clinics' ?></p>

    <section class="admin-kpi-section admin-primary-kpi-section" aria-labelledby="priority-kpis-title"><div class="admin-section-heading"><div><span class="admin-section-eyebrow">Priority snapshot</span><h2 id="priority-kpis-title">Needs attention first</h2></div><span class="admin-section-note">Filtered by the selected range</span></div><div class="admin-kpi-grid admin-primary-kpi-grid">
        <?php foreach ($primaryKpis as [$key, $title, $url, $icon, $tone]): ?><a class="admin-kpi-card admin-kpi-card--<?= esc($tone) ?>" href="<?= base_url($url) ?>"><span class="admin-kpi-icon" aria-hidden="true"><i class="bi <?= esc($icon) ?>"></i></span><span class="admin-kpi-copy"><span><?= esc($title) ?></span><strong><?= in_array($key, ['outstanding_balance', 'total_revenue'], true) ? esc($money((float) ($kpis[$key] ?? 0))) : number_format((int) ($kpis[$key] ?? 0)) ?></strong></span></a><?php endforeach; ?>
    </div></section>

    <?php if (!empty($metrics['pending_actions'])): ?><section class="admin-analytics-card admin-pending-card admin-pending-card--priority"><div class="admin-card-heading"><h2>Pending actions requiring attention</h2><span class="admin-card-kicker">Current range</span></div><div class="admin-pending-grid"><?php foreach ($metrics['pending_actions'] as $action): ?><a href="<?= base_url($action['url']) ?>"><span><?= esc($action['label']) ?></span><strong><?= number_format((int) $action['count']) ?></strong><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a><?php endforeach; ?></div><?php if (($metrics['audit_alerts'] ?? 0) > 0): ?><div class="alert alert-warning mt-3 mb-0"><?= number_format((int) $metrics['audit_alerts']) ?> failed or denied audit events in this range. <a href="<?= base_url('admin/audit') ?>">Review audit logs</a></div><?php endif; ?></section><?php endif; ?>

    <?php foreach ($secondaryKpiGroups as $group => $cards): ?>
        <section class="admin-kpi-section admin-secondary-kpi-section" aria-labelledby="<?= esc(strtolower(str_replace(' ', '-', $group))) ?>-title"><h2 id="<?= esc(strtolower(str_replace(' ', '-', $group))) ?>-title"><?= esc($group) ?></h2><div class="admin-kpi-grid">
            <?php foreach ($cards as [$key, $title, $url, $icon]): ?><a class="admin-kpi-card" href="<?= base_url($url) ?>"><span class="admin-kpi-icon" aria-hidden="true"><i class="bi <?= esc($icon) ?>"></i></span><span class="admin-kpi-copy"><span><?= esc($title) ?></span><strong><?= number_format((int) ($kpis[$key] ?? 0)) ?></strong></span></a><?php endforeach; ?>
        </div></section>
    <?php endforeach; ?>

    <section class="admin-finance-grid" aria-label="Revenue and balance"><div class="admin-finance-card admin-finance-paid"><span>Paid amount</span><strong><?= esc($money((float) ($kpis['paid_amount'] ?? 0))) ?></strong><small>Based on recorded payment transactions</small></div></section>

    <section class="admin-dashboard-columns" aria-label="Dashboard analysis">
        <?php foreach ([['appointments', 'Appointments by status'], ['prescriptions', 'Prescriptions by status'], ['orders', 'Orders by status'], ['payments', 'Payment status breakdown']] as [$key, $title]): ?><article class="admin-analytics-card"><div class="admin-card-heading"><h2><?= esc($title) ?></h2><span class="admin-card-kicker"><?= number_format(array_sum($breakdowns[$key] ?? [])) ?> total</span></div><?php if (!empty($breakdowns[$key])): foreach ($breakdowns[$key] as $status => $count): $max = max(1, max($breakdowns[$key])); ?><div class="admin-breakdown-row"><span><?= esc($label($status)) ?></span><strong><?= number_format((int) $count) ?></strong><span class="admin-breakdown-track"><span style="width:<?= round(((int) $count / $max) * 100) ?>%"></span></span></div><?php endforeach; else: ?><div class="admin-empty-state">No records in this range.</div><?php endif; ?></article><?php endforeach; ?>
        <article class="admin-analytics-card admin-finance-analysis"><div class="admin-card-heading"><h2>Revenue versus outstanding balance</h2><span class="admin-card-kicker"><?= esc($money((float) ($kpis['total_revenue'] ?? 0))) ?></span></div><div class="admin-finance-bar"><span style="width:<?= ($kpis['total_revenue'] ?? 0) > 0 ? min(100, round(((float) ($kpis['paid_amount'] ?? 0) / (float) $kpis['total_revenue']) * 100)) : 0 ?>%"></span></div><div class="admin-finance-legend"><span><i class="paid-dot"></i>Paid <?= esc($money((float) ($kpis['paid_amount'] ?? 0))) ?></span><span><i class="balance-dot"></i>Outstanding <?= esc($money((float) ($kpis['outstanding_balance'] ?? 0))) ?></span></div></article>
    </section>

    <section class="admin-analytics-card admin-trends-card"><div class="admin-card-heading"><h2>Activity over time</h2><span class="admin-card-kicker">Daily totals</span></div><div class="admin-trend-grid"><?php foreach ([['patients', 'New patients'], ['appointments', 'Appointments'], ['orders', 'Orders']] as [$key, $title]): ?><div><h3><?= esc($title) ?></h3><?php if (!empty($trends[$key])): foreach ($trends[$key] as $point): ?><div class="admin-trend-row"><span><?= esc((string) ($point['day'] ?? '')) ?></span><strong><?= number_format((int) ($point['total'] ?? 0)) ?></strong></div><?php endforeach; else: ?><div class="admin-empty-state">No activity in this range.</div><?php endif; ?></div><?php endforeach; ?></div></section>

    <section class="admin-dashboard-columns admin-recent-section" aria-label="Recent records">
        <?php foreach ([['appointments', 'Recent appointments', ['Appointment', 'Date', 'Status']], ['prescriptions', 'Recent prescriptions', ['Prescription', 'Date', 'Status']], ['orders', 'Recent orders', ['Order', 'Amount', 'Status']]] as [$key, $title, $headers]): ?><article class="admin-analytics-card admin-recent-card"><div class="admin-card-heading"><h2><?= esc($title) ?></h2></div><div class="table-responsive"><table class="table table-sm mb-0"><thead><tr><?php foreach ($headers as $header): ?><th><?= esc($header) ?></th><?php endforeach; ?></tr></thead><tbody><?php if (empty($recent[$key])): ?><tr><td colspan="3" class="admin-empty-state">No records in this range.</td></tr><?php else: foreach ($recent[$key] as $row): ?><tr><td><strong><?= esc($row[$key === 'appointments' ? 'appointment_number' : ($key === 'prescriptions' ? 'prescription_number' : 'order_number')] ?? 'Not recorded') ?></strong><small class="d-block text-muted"><?= esc(trim(implode(' ', array_filter([$row['registration_number'] ?? '', $row['first_name'] ?? '', $row['last_name'] ?? '']))) ?: 'Patient not recorded') ?></small></td><td><?= esc($key === 'orders' ? $money((float) ($row['total'] ?? 0)) : (string) ($row[$key === 'appointments' ? 'appointment_date' : 'prescription_date'] ?? 'Not recorded')) ?></td><td><span class="admin-status-badge"><?= esc($label((string) ($row[$key === 'orders' ? 'status' : 'status'] ?? 'unknown'))) ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></article><?php endforeach; ?>
    </section>

    <?php if ($can('doctor.view') || $can('doctor_commission.view')): ?><section class="admin-dashboard-card-links" aria-label="Doctor administration links"><a href="<?= base_url('admin/doctors') ?>">Doctor Management</a><?php if ($can('doctor_commission.view')): ?><a href="<?= base_url('admin/doctor-commissions') ?>">Doctor Commission</a><?php endif; ?></section><?php endif; ?>
    <?php $moduleAccess = [['label' => 'Doctor', 'permission' => 'doctor.view', 'url' => 'doctor'], ['label' => 'Pharmacy', 'permission' => 'pharmacy.dashboard.view', 'url' => 'pharmacy'], ['label' => 'Patient', 'permission' => 'patient.view', 'url' => 'admin/patients'], ['label' => 'CRM / Lead Management', 'permission' => 'crm.lead.view', 'url' => 'admin/crm/leads'], ['label' => 'Core API', 'permission' => 'core_api.view', 'url' => 'admin/integrations/core-api'], ['label' => 'Admin Settings', 'permission' => 'settings.manage', 'url' => 'admin/configuration/settings/general-settings']]; $availableModules = array_values(array_filter($moduleAccess, static fn (array $module): bool => $can($module['permission']))); ?>
    <?php if ($availableModules): ?><section class="admin-dashboard-card-links" aria-label="Authorized modules"><strong>Authorized modules</strong><?php foreach ($availableModules as $module): ?><a href="<?= base_url($module['url']) ?>"><?= esc($module['label']) ?></a><?php endforeach; ?></section><?php endif; ?>
</main>
<?= $this->endSection() ?>
