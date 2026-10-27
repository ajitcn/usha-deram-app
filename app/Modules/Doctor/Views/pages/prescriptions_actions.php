<div class="doctor-prescription-action-group" role="group" aria-label="Actions for prescription <?= esc($prescriptionNumber) ?>">
    <button type="button" class="doctor-prescription-metadata" data-toggle="tooltip" data-placement="top" title="Created: <?= esc($createdAt) ?>. Updated: <?= esc($updatedAt) ?>." aria-label="Show created and updated dates for prescription <?= esc($prescriptionNumber) ?>"><i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i></button>
    <div class="dropdown">
        <button id="<?= esc($detailId) ?>-actions-toggle" class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" aria-controls="<?= esc($detailId) ?>-actions-menu" aria-label="More Actions for prescription <?= esc($prescriptionNumber) ?>">More Actions</button>
        <div id="<?= esc($detailId) ?>-actions-menu" class="dropdown-menu dropdown-menu-right doctor-prescription-action-menu" role="menu" aria-labelledby="<?= esc($detailId) ?>-actions-toggle">
            <button type="button" class="dropdown-item" role="menuitem" data-toggle="modal" data-target="#<?= esc($detailId) ?>" aria-label="View full prescription <?= esc($prescriptionNumber) ?>"><i class="fa-solid fa-eye mr-2" aria-hidden="true"></i>View</button>
            <button type="button" class="dropdown-item doctor-prescription-list-print" role="menuitem" data-print-modal="#<?= esc($detailId) ?>" aria-controls="<?= esc($detailId) ?>" aria-label="Print prescription <?= esc($prescriptionNumber) ?>"><i class="fa-solid fa-print mr-2" aria-hidden="true"></i>Print</button>
            <?php if (($doctorCanEditClinical ?? false) && ($canEditConsultation ?? false) && (int) ($appointmentId ?? 0) > 0 && (int) ($patientId ?? 0) > 0): ?>
                <a class="dropdown-item" role="menuitem" href="<?= base_url('doctor/clinical?appointment_id=' . (int) $appointmentId . '&patient_id=' . (int) $patientId . '&edit=1#patient-summary') ?>" title="Edit Consultation &amp; Prescription" aria-label="Edit Consultation &amp; Prescription for <?= esc($prescriptionNumber) ?>"><i class="fa-solid fa-pen-to-square mr-2" aria-hidden="true"></i>Edit Consultation &amp; Prescription</a>
            <?php endif; ?>
            <?php if ($doctorCanViewPatients && $patientId > 0): ?>
                <a class="dropdown-item" role="menuitem" href="<?= base_url('doctor/patients/' . $patientId) ?>"><i class="fa-solid fa-user mr-2" aria-hidden="true"></i>View patient</a>
            <?php endif; ?>
            <?php if ($doctorCanViewClinical): ?>
                <a class="dropdown-item" role="menuitem" href="<?= base_url('doctor/clinical') ?>"><i class="fa-solid fa-stethoscope mr-2" aria-hidden="true"></i>Open clinical workspace</a>
            <?php endif; ?>
        </div>
    </div>
</div>
