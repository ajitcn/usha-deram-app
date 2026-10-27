<?= $this->extend(ADMIN_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$patientName = trim(implode(' ', array_filter([
    $appointment['patient_first_name'] ?? '',
    $appointment['patient_middle_name'] ?? '',
    $appointment['patient_last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
$doctorName = trim(implode(' ', array_filter([
    $appointment['doctor_first_name'] ?? '',
    $appointment['doctor_last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== '')));
$patientMobile = trim((string) ($appointment['patient_mobile'] ?? '')) ?: 'Not provided';
$patientEmail = trim((string) ($appointment['patient_email'] ?? '')) ?: 'Not provided';
$feeSummary = $feeSummary ?? [];
?>
<main class="dashboard-container admin-appointment-page">
    <?= view('templates/_breadcrumb', ['module' => 'admin', 'menu_title' => 'Appointment Management', 'page' => 'Appointment Details']) ?>

    <div class="action-btn-container">
        <a href="<?= base_url('admin/appointments/calendar') ?>" class="btn btn-sm theme-btn"><i class="bi bi-arrow-left"></i> Back to Calendar</a>
        <a href="<?= base_url('admin/appointments') ?>" class="btn btn-sm btn-outline-secondary">List View</a>
        <?php if (($canEdit ?? false) && !in_array((string) ($appointment['status'] ?? ''), ['completed', 'cancelled', 'no_show'], true)): ?><a href="<?= base_url('admin/appointments') . '?edit=' . (int) $appointment['id'] ?>" class="btn btn-sm btn-outline-secondary">Edit Appointment</a><?php endif; ?>
        <?php if ($canCollectFee && !in_array((string) ($appointment['status'] ?? ''), ['cancelled', 'no_show'], true) && (float) ($feeSummary['remaining_amount'] ?? 0) > 0): ?><button type="button" class="btn btn-sm theme-btn js-consultation-fee" data-appointment-id="<?= (int) $appointment['id'] ?>" data-appointment-number="<?= esc($appointment['appointment_number'], 'attr') ?>" data-amount="<?= esc(number_format((float) ($feeSummary['remaining_amount'] ?? 0), 2, '.', ''), 'attr') ?>">Collect Consultation Fee</button><?php endif; ?>
    </div>

    <div class="card">
        <div class="card-header"><h5 class="mb-0">Appointment <?= esc($appointment['appointment_number']) ?></h5></div>
        <div class="card-body">
            <div class="row">
                <?php foreach ([
                    'Patient' => $patientName . ' (' . ($appointment['registration_number'] ?? '') . ')',
                    'Patient mobile' => $patientMobile,
                    'Patient email' => $patientEmail,
                    'Doctor' => $doctorName . ' (' . ($appointment['doctor_code'] ?? '') . ')',
                    'Clinic' => $appointment['clinic_name'] ?? '',
                    'Date' => usha_derma_format_date($appointment['appointment_date'] ?? ''),
                    'Time' => substr((string) ($appointment['appointment_time'] ?? ''), 0, 5),
                    'Duration' => ($appointment['duration_minutes'] ?? '') . ' minutes',
                    'Appointment Type' => ucwords(str_replace('_', ' ', $appointment['appointment_type'] ?? '')),
                    'Status' => ucwords(str_replace('_', ' ', $appointment['status'] ?? '')),
                ] as $label => $value): ?>
                    <div class="col-md-6 mb-3"><strong><?= esc($label) ?></strong><div><?= esc($value) ?></div></div>
                <?php endforeach; ?>
                <div class="col-12 mb-3"><strong>Patient Source</strong><div><span class="badge <?= esc(patient_source_badge_class($appointment['patient_source_type'] ?? null)) ?>"><?= esc(patient_source_label($appointment['patient_source_type'] ?? null)) ?></span></div></div><div class="col-12 mb-3"><strong>Reason for Visit</strong><div><?= nl2br(esc($appointment['reason_for_visit'] ?: '—')) ?></div></div>
                <div class="col-12 mb-3"><strong>Notes</strong><div><?= nl2br(esc($appointment['notes'] ?: '—')) ?></div></div>
                <div class="col-md-6 mb-3"><strong>Consultation Fee</strong><div><?= esc(number_format((float) ($feeSummary['consultation_fee'] ?? 0), 2)) ?> INR</div></div><div class="col-md-6 mb-3"><strong>Consultation Fee Paid</strong><div><?= esc(number_format((float) ($feeSummary['paid_amount'] ?? 0), 2)) ?> INR <span class="badge badge-light"><?= esc(ucwords(str_replace('_', ' ', (string) ($feeSummary['payment_status'] ?? 'unpaid')))) ?></span></div><?php if (!empty($feeSummary['payment_date'])): ?><small class="text-muted d-block"><?= esc($feeSummary['payment_date']) ?> · <?= esc($feeSummary['payment_method'] ?? 'Not recorded') ?> · <?= esc($feeSummary['receipt_number'] ?? $feeSummary['reference_number'] ?? 'No reference') ?></small><?php endif; ?></div>
                <?php if ($appointment['status'] === 'cancelled'): ?>
                    <div class="col-12 mb-3"><strong>Cancellation Reason</strong><div><?= nl2br(esc($appointment['cancellation_reason'] ?: '—')) ?></div></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</main>
<?= view('parts/consultation_fee_modal', ['canCollectFee' => $canCollectFee, 'feeEndpoint' => base_url('admin/appointments/collect-fee')]) ?>
<?= $this->endSection() ?>
