<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php $patientName = trim(implode(' ', array_filter([
    $appointment['patient_first_name'] ?? '',
    $appointment['patient_middle_name'] ?? '',
    $appointment['patient_last_name'] ?? '',
], static fn ($part) => $part !== null && $part !== ''))); ?>
<?php $patientContact = service('telephoneContact')->formatDisplay($appointment['patient_telephone_country_code'] ?? null, $appointment['patient_mobile_normalized'] ?? $appointment['patient_mobile_number'] ?? null, $appointment['patient_legacy_country_code'] ?? null); ?>
<?php $feeSummary = $feeSummary ?? []; $canCollectFee = $canCollectFee ?? false; ?>

<main class="dashboard-container" aria-labelledby="doctor-appointment-title">
    <div class="page-heading">
        <div><a href="<?= base_url('doctor') ?>" class="btn btn-sm btn-light mb-3"><i class="bi bi-arrow-left mr-1" aria-hidden="true"></i>Back to dashboard</a>
        <div class="page-eyebrow">Appointment / assigned patient</div><h1 id="doctor-appointment-title" class="h3 mb-1">Appointment <?= esc($appointment['appointment_number'] ?? '') ?></h1>
        <div class="text-muted">Assigned patient appointment details</div></div>
        <span class="badge badge-primary"><i class="bi bi-shield-check mr-1" aria-hidden="true"></i>Clinic scoped</span>
    </div>

    <div class="card mb-4">
        <div class="card-header d-flex flex-wrap justify-content-between align-items-center">
            <h2 class="h5 mb-0">Appointment</h2>
            <div class="btn-group" role="group" aria-label="Appointment related records">
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/patients/' . (int) ($appointment['patient_id'] ?? 0)) ?>">Patient</a>
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/clinical') ?>">Consultation</a>
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/prescriptions') ?>">Prescriptions</a>
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/follow-ups') ?>">Follow-ups</a>
                <a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/billing') ?>">Billing</a>
                <?php if ($canCollectFee && !in_array((string) ($appointment['status'] ?? ''), ['cancelled', 'no_show'], true) && (float) ($feeSummary['remaining_amount'] ?? 0) > 0): ?><button type="button" class="btn btn-sm theme-btn js-consultation-fee" data-appointment-id="<?= (int) $appointment['id'] ?>" data-appointment-number="<?= esc($appointment['appointment_number'], 'attr') ?>" data-amount="<?= esc(number_format((float) ($feeSummary['remaining_amount'] ?? 0), 2, '.', ''), 'attr') ?>">Collect Consultation Fee</button><?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-6 mb-3"><strong>Patient</strong><div><?= esc($patientName) ?> <span class="text-muted small"><?= esc($appointment['registration_number'] ?? '') ?></span> <span class="badge <?= esc(patient_source_badge_class($appointment['patient_source_type'] ?? null)) ?>"><?= esc(patient_source_label($appointment['patient_source_type'] ?? null)) ?></span><br><small class="text-muted"><?= esc($patientContact) ?></small></div></div>
                <div class="col-md-6 mb-3"><strong>Date and time</strong><div><?= esc($appointment['appointment_date'] ?? '') ?> <?= esc(substr((string) ($appointment['appointment_time'] ?? ''), 0, 5)) ?></div></div>
                <div class="col-md-6 mb-3"><strong>Type</strong><div><?= esc(ucwords(str_replace('_', ' ', (string) ($appointment['appointment_type'] ?? '')))) ?></div></div>
                <div class="col-md-6 mb-3"><strong>Service</strong><div><?= esc($appointment['service_name'] ?? '—') ?></div></div>
                <div class="col-md-6 mb-3"><strong>Status</strong><div><?= esc(ucwords(str_replace('_', ' ', (string) ($appointment['status'] ?? '')))) ?></div></div>
                <div class="col-md-6 mb-3"><strong>Consultation Fee</strong><div><?= esc(number_format((float) ($feeSummary['consultation_fee'] ?? 0), 2)) ?> INR</div></div><div class="col-md-6 mb-3"><strong>Consultation Fee Paid</strong><div><?= esc(number_format((float) ($feeSummary['paid_amount'] ?? 0), 2)) ?> INR <span class="badge badge-light"><?= esc(ucwords(str_replace('_', ' ', (string) ($feeSummary['payment_status'] ?? 'unpaid')))) ?></span></div><?php if (!empty($feeSummary['payment_date'])): ?><small class="text-muted d-block"><?= esc($feeSummary['payment_date']) ?> · <?= esc($feeSummary['payment_method'] ?? 'Not recorded') ?> · <?= esc($feeSummary['receipt_number'] ?? $feeSummary['reference_number'] ?? 'No reference') ?></small><?php endif; ?></div>
                <div class="col-12"><strong>Reason for visit</strong><div><?= nl2br(esc($appointment['reason_for_visit'] ?? '')) ?: '<span class="text-muted">Not provided.</span>' ?></div></div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6 mb-4"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Consultation</h5></div><div class="card-body">
            <?php if ($consultation === null): ?><span class="text-muted">No consultation linked.</span><?php else: ?>
                <div><?= esc($consultation['consultation_number'] ?? '') ?></div><div><?= esc($consultation['consultation_date'] ?? '') ?></div><div><?= esc(ucwords(str_replace('_', ' ', (string) ($consultation['status'] ?? '')))) ?></div>
            <?php endif; ?>
        </div></div></div>
        <div class="col-md-6 mb-4"><div class="card h-100"><div class="card-header"><h5 class="mb-0">Prescription</h5></div><div class="card-body">
            <?php if ($prescription === null): ?><span class="text-muted">No prescription linked.</span><?php else: ?>
                <div><?= esc($prescription['prescription_number'] ?? '') ?></div><div><?= esc($prescription['prescription_date'] ?? '') ?></div><div><?= esc(ucwords(str_replace('_', ' ', (string) ($prescription['status'] ?? '')))) ?></div>
            <?php endif; ?>
        </div></div></div>
    </div>
</main>
<?= view('parts/consultation_fee_modal', ['canCollectFee' => $canCollectFee && !in_array((string) ($appointment['status'] ?? ''), ['cancelled', 'no_show'], true) && (float) ($feeSummary['remaining_amount'] ?? 0) > 0, 'feeEndpoint' => base_url('doctor/appointments/collect-fee')]) ?>
<?= $this->endSection() ?>
