<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<main class="dashboard-container doctor-dashboard-page" aria-labelledby="doctor-dashboard-title">
    <?php
    $doctorName = trim(implode(' ', array_filter([
        $doctor['first_name'] ?? '',
        $doctor['last_name'] ?? '',
    ], static fn ($part) => $part !== null && $part !== '')));
    $canCollectFee = in_array('billing.edit', session('permissions') ?? [], true);
?>

    <div class="page-heading doctor-dashboard-header">
        <div class="doctor-dashboard-header-copy"><div class="page-eyebrow">Doctor workspace</div>
        <h1 id="doctor-dashboard-title" class="h3 mb-1">Welcome, Dr. <?= esc($doctorName) ?></h1>
        <div class="text-muted">Doctor code: <?= esc($doctor['doctor_code'] ?? '') ?></div>
        </div><div class="doctor-dashboard-header-actions"><a class="btn btn-primary" href="<?= base_url('doctor/clinical') ?>"><i class="bi bi-clipboard2-pulse mr-1" aria-hidden="true"></i> Open clinical workspace</a><a class="btn btn-outline-primary" href="<?= base_url('doctor/notifications') ?>"><i class="bi bi-bell mr-1" aria-hidden="true"></i> Notifications</a></div>
    </div>

    <?php
        $statusClass = static function (string $status): string {
            return match (strtolower($status)) {
                'completed', 'approved', 'confirmed', 'processed', 'dispensed' => 'badge-success',
                'clarification_requested', 'requested', 'pending', 'submitted', 'in_progress' => 'badge-warning',
                'cancelled', 'rejected', 'no_show' => 'badge-danger',
                default => 'badge-light',
            };
        };
        $patientName = static fn (array $row): string => trim(implode(' ', array_filter([$row['patient_first_name'] ?? '', $row['patient_middle_name'] ?? '', $row['patient_last_name'] ?? ''], static fn ($part) => $part !== null && $part !== '')));
        $displayStatus = static fn ($status): string => ucwords(str_replace('_', ' ', (string) $status));
    ?>

    <section class="doctor-dashboard-kpis mb-4" aria-label="Doctor dashboard key performance indicators">
        <?php foreach ([
            ['Today\'s appointments', $appointmentCount, 'doctor/appointments', 'bi-calendar-check'],
            ['Pending consultations', $consultationCount, 'doctor/clinical', 'bi-clipboard2-pulse'],
            ['Completed consultations', $completedConsultationCount, 'doctor/clinical', 'bi-check2-circle'],
            ['Prescriptions created', $prescriptionCount, 'doctor/prescriptions', 'bi-prescription2'],
            ['Awaiting pharmacy review', $prescriptionReviewCount, 'doctor/prescriptions?status=submitted', 'bi-hourglass-split'],
            ['Clarifications pending', $prescriptionClarificationCount, 'doctor/prescription-clarifications', 'bi-chat-square-exclamation'],
            ['Prescriptions approved', $approvedPrescriptionCount, 'doctor/prescriptions?status=approved', 'bi-patch-check'],
            ['Patients requiring follow-up', $followUpCount, 'doctor/follow-ups', 'bi-calendar2-check'],
        ] as [$label, $count, $url, $icon]): ?>
            <a class="card ri-stat-card doctor-dashboard-kpi text-decoration-none" href="<?= base_url($url) ?>" aria-label="<?= esc($label) ?>: <?= (int) $count ?>">
                <div class="card-body"><div class="doctor-dashboard-kpi-label"><?= esc($label) ?></div><div class="ri-stat-value"><?= (int) $count ?></div><span class="ri-stat-icon" aria-hidden="true"><i class="bi <?= esc($icon) ?>"></i></span></div>
            </a>
        <?php endforeach; ?>
    </section>

    <div class="doctor-dashboard-grid">
        <section class="card doctor-dashboard-panel doctor-dashboard-urgent">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between"><div><h2 class="h5 mb-1">Prescription clarifications</h2><small class="text-muted">Pharmacy requests requiring your action</small></div><a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/prescription-clarifications') ?>">View all (<?= (int) $prescriptionClarificationCount ?>)</a></div>
            <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Patient / prescription</th><th>Medicine</th><th>Pharmacy message</th><th>Requested</th><th></th></tr></thead><tbody>
                <?php if ($clarifications === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No prescription clarifications require action.</td></tr><?php else: foreach ($clarifications as $clarification): ?><tr>
                    <td><strong><?= esc(trim(($clarification['patient_first_name'] ?? '') . ' ' . ($clarification['patient_last_name'] ?? ''))) ?></strong><br><small class="text-muted"><?= esc($clarification['prescription_number'] ?? '') ?></small></td>
                    <td><?= esc($clarification['medicine_name'] ?? 'All medicines') ?></td>
                    <td class="doctor-dashboard-message"><?= esc($clarification['pharmacy_message'] ?? '') ?></td>
                    <td><?= esc(usha_derma_format_date($clarification['requested_at'] ?? '')) ?></td>
                    <td class="text-nowrap"><a class="btn btn-sm btn-primary" href="<?= base_url('doctor/prescription-clarifications?clarification_id=' . (int) ($clarification['id'] ?? 0)) ?>">Review</a></td>
                </tr><?php endforeach; endif; ?></tbody></table></div>
        </section>

        <section class="card doctor-dashboard-panel">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between"><h2 class="h5 mb-0">Upcoming appointments</h2><a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/appointments') ?>">View all</a></div>
            <div class="table-responsive"><table class="table mb-0"><thead><tr><th>Appointment</th><th>Patient</th><th>Date / time</th><th>Fee / paid</th><th>Status</th></tr></thead><tbody><?php if ($appointments === []): ?><tr><td colspan="5" class="text-center text-muted py-4">No upcoming appointments.</td></tr><?php else: foreach (array_slice($appointments, 0, 6) as $appointment): ?><?php $fee = $appointment['fee_summary'] ?? []; ?><tr><td><a href="<?= base_url('doctor/appointments/' . (int) ($appointment['id'] ?? 0)) ?>"><?= esc($appointment['appointment_number'] ?? '') ?></a><br><small class="text-muted"><?= esc($displayStatus($appointment['appointment_type'] ?? '')) ?></small></td><td><?= esc($patientName($appointment)) ?><br><small class="text-muted"><?= esc($appointment['registration_number'] ?? '') ?></small></td><td><?= esc(usha_derma_format_date($appointment['appointment_date'] ?? '')) ?><br><small><?= esc(substr((string) ($appointment['appointment_time'] ?? ''), 0, 5)) ?></small></td><td><small><?= esc(number_format((float) ($fee['consultation_fee'] ?? 0), 2)) ?> / <?= esc(number_format((float) ($fee['paid_amount'] ?? 0), 2)) ?> INR</small><br><span class="badge badge-light"><?= esc(ucwords(str_replace('_', ' ', (string) ($fee['payment_status'] ?? 'unpaid')))) ?></span><?php if ($canCollectFee && !in_array((string) ($appointment['status'] ?? ''), ['cancelled', 'no_show'], true) && (float) ($fee['remaining_amount'] ?? 0) > 0): ?><button type="button" class="btn btn-sm btn-outline-primary d-block mt-2 js-consultation-fee" data-appointment-id="<?= (int) $appointment['id'] ?>" data-appointment-number="<?= esc($appointment['appointment_number'] ?? '', 'attr') ?>" data-amount="<?= esc(number_format((float) ($fee['remaining_amount'] ?? 0), 2, '.', ''), 'attr') ?>">Collect fee</button><?php endif; ?></td><td><span class="badge <?= esc($statusClass((string) ($appointment['status'] ?? ''))) ?>"><?= esc($displayStatus($appointment['status'] ?? '')) ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div>
        </section>

        <section class="card doctor-dashboard-panel"><div class="card-header d-flex flex-wrap align-items-center justify-content-between"><h2 class="h5 mb-0">Recent consultations</h2><a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/clinical') ?>">Open clinical</a></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Consultation</th><th>Patient</th><th>Date</th><th>Status</th></tr></thead><tbody><?php if ($recentConsultations === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No consultations found.</td></tr><?php else: foreach ($recentConsultations as $consultation): ?><tr><td><?= esc($consultation['consultation_number'] ?? '') ?></td><td><?= esc(trim(($consultation['patient_first_name'] ?? '') . ' ' . ($consultation['patient_last_name'] ?? ''))) ?><br><small class="text-muted"><?= esc($consultation['registration_number'] ?? '') ?></small></td><td><?= esc(usha_derma_format_date($consultation['consultation_date'] ?? '')) ?></td><td><span class="badge <?= esc($statusClass((string) ($consultation['status'] ?? ''))) ?>"><?= esc($displayStatus($consultation['status'] ?? '')) ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></section>

        <section class="card doctor-dashboard-panel"><div class="card-header d-flex flex-wrap align-items-center justify-content-between"><h2 class="h5 mb-0">Recent prescriptions</h2><a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/prescriptions') ?>">View all</a></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Prescription</th><th>Patient</th><th>Date</th><th>Status</th></tr></thead><tbody><?php if ($recentPrescriptions === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No prescriptions found.</td></tr><?php else: foreach ($recentPrescriptions as $prescription): ?><tr><td><?= esc($prescription['prescription_number'] ?? '') ?></td><td><?= esc(trim(($prescription['patient_first_name'] ?? '') . ' ' . ($prescription['patient_last_name'] ?? ''))) ?><br><small class="text-muted"><?= esc($prescription['registration_number'] ?? '') ?></small></td><td><?= esc(usha_derma_format_date($prescription['prescription_date'] ?? '')) ?></td><td><span class="badge <?= esc($statusClass((string) ($prescription['status'] ?? ''))) ?>"><?= esc($displayStatus($prescription['status'] ?? '')) ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></section>

        <section class="card doctor-dashboard-panel"><div class="card-header d-flex flex-wrap align-items-center justify-content-between"><h2 class="h5 mb-0">Follow-up appointments</h2><a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/follow-ups') ?>">View all</a></div><div class="table-responsive"><table class="table mb-0"><thead><tr><th>Patient</th><th>Date / time</th><th>Type</th><th>Status</th></tr></thead><tbody><?php if ($followUps === []): ?><tr><td colspan="4" class="text-center text-muted py-4">No upcoming follow-up appointments.</td></tr><?php else: foreach ($followUps as $followUp): ?><tr><td><?= esc(trim(($followUp['patient_first_name'] ?? '') . ' ' . ($followUp['patient_last_name'] ?? ''))) ?><br><small class="text-muted"><?= esc($followUp['registration_number'] ?? '') ?></small></td><td><?= esc(usha_derma_format_date($followUp['follow_up_date'] ?? '')) ?><br><small><?= esc(substr((string) ($followUp['follow_up_time'] ?? ''), 0, 5)) ?></small></td><td><?= esc($displayStatus($followUp['follow_up_type'] ?? 'Follow-up')) ?></td><td><span class="badge <?= esc($statusClass((string) ($followUp['follow_up_status_code'] ?? ''))) ?>"><?= esc($followUp['follow_up_status_name'] ?? $displayStatus($followUp['follow_up_status_code'] ?? 'Scheduled')) ?></span></td></tr><?php endforeach; endif; ?></tbody></table></div></section>

        <section class="card doctor-dashboard-panel"><div class="card-header d-flex flex-wrap align-items-center justify-content-between"><div><h2 class="h5 mb-1">Pharmacy responses &amp; updates</h2><small class="text-muted"><?= (int) $unreadNotificationCount ?> unread task<?= $unreadNotificationCount === 1 ? '' : 's' ?></small></div><a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/notifications') ?>">Open alerts</a></div><div class="list-group list-group-flush"><?php if ($pharmacyUpdates === []): ?><div class="empty-state py-4 text-center"><div>No pharmacy updates or tasks.</div></div><?php else: foreach ($pharmacyUpdates as $notification): ?><article class="list-group-item px-0 <?= ($notification['status'] ?? '') === 'unread' ? 'font-weight-bold' : '' ?>"><div class="d-flex justify-content-between align-items-start gap-2"><div class="min-width-0"><div><?= esc($notification['title'] ?? '') ?> <span class="badge <?= ($notification['status'] ?? '') === 'unread' ? 'badge-warning' : 'badge-light' ?>"><?= esc(ucfirst((string) ($notification['status'] ?? ''))) ?></span></div><p class="small text-muted mb-1 font-weight-normal"><?= esc($notification['message'] ?? '') ?></p><small class="text-muted font-weight-normal"><?= esc($notification['created_at'] ?? '') ?></small></div></div></article><?php endforeach; endif; ?></div></section>
    </div>
</main>
<?= view('parts/consultation_fee_modal', ['canCollectFee' => $canCollectFee, 'feeEndpoint' => base_url('doctor/appointments/collect-fee')]) ?>
<?= $this->endSection() ?>
