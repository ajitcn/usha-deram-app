<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$selected = static function (array $options, mixed $value): bool {
    $value = trim((string) ($value ?? ''));
    foreach ($options as $option) {
        if ($value !== '' && ($value === (string) ($option['id'] ?? '') || $value === (string) ($option['code'] ?? '') || $value === (string) ($option['name'] ?? ''))) {
            return true;
        }
    }
    return false;
};
$optionValue = static fn (array $option): string => (string) ($option['code'] ?? $option['id'] ?? '');
?>
<main class="dashboard-container doctor-prescription-edit-page" aria-labelledby="doctor-prescription-edit-title">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Clinical records</div>
            <h1 id="doctor-prescription-edit-title">Edit Prescription</h1>
            <p class="text-muted mb-0">Update this prescription before pharmacy approval.</p>
        </div>
        <a class="btn btn-outline-secondary" href="<?= base_url('doctor/prescriptions') ?>">Back to prescriptions</a>
    </div>

    <section class="card" aria-labelledby="prescription-edit-summary-title">
        <div class="card-header"><h2 id="prescription-edit-summary-title" class="h5 mb-0">Prescription <?= esc($prescription['prescription_number']) ?></h2></div>
        <div class="card-body">
            <dl class="row mb-4">
                <dt class="col-sm-3">Patient</dt><dd class="col-sm-9"><?= esc($prescription['patient_name']) ?></dd>
                <dt class="col-sm-3">Status</dt><dd class="col-sm-9"><?= esc(ucwords(str_replace('_', ' ', $prescription['status']))) ?></dd>
            </dl>
            <form method="post" action="<?= base_url('doctor/prescriptions/' . (int) $prescription['id'] . '/edit') ?>" id="doctor-prescription-edit-form">
                <?= csrf_field() ?>
                <div class="form-row">
                    <div class="form-group col-md-4">
                        <label for="prescription-medicine-duration">Prescription Duration (Days)</label>
                        <input class="form-control" id="prescription-medicine-duration" type="number" name="medicine_duration" min="1" max="3650" step="1" required value="<?= esc((string) ($prescription['medicine_duration'] ?? '')) ?>">
                    </div>
                    <div class="form-group col-md-8">
                        <label for="prescription-notes">Prescription Notes</label>
                        <textarea class="form-control" id="prescription-notes" name="prescription_notes" rows="2" maxlength="65535"><?= esc((string) ($prescription['notes'] ?? '')) ?></textarea>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-bordered align-middle">
                        <caption class="sr-only">Editable medicines for prescription <?= esc($prescription['prescription_number']) ?></caption>
                        <thead><tr><th>Medicine</th><th>Duration</th><th>Frequency</th><th>Dose</th><th>Intake</th><th>Note</th><th>Quantity</th></tr></thead>
                        <tbody>
                        <?php foreach ($items as $index => $item): ?>
                            <tr>
                                <td>
                                    <input type="hidden" name="items[<?= (int) $index ?>][item_id]" value="<?= (int) $item['id'] ?>">
                                    <select class="form-control" name="items[<?= (int) $index ?>][medicine_id]" required>
                                        <?php foreach ($medicines as $medicine): ?><option value="<?= (int) $medicine['id'] ?>" <?= (int) $medicine['id'] === (int) $item['medicine_id'] ? 'selected' : '' ?>><?= esc($medicine['medicine_name']) ?></option><?php endforeach; ?>
                                    </select>
                                </td>
                                <?php foreach ([['duration', 'Duration', 'duration'], ['frequency', 'Frequency', 'frequency'], ['dosage', 'Dose', 'dose'], ['instructions', 'Intake', 'intake']] as [$field, $label, $type]): ?>
                                    <td data-label="<?= esc($label) ?>">
                                        <select class="form-control" name="items[<?= (int) $index ?>][<?= esc($field) ?>]">
                                            <option value="">Not specified</option>
                                            <?php foreach (($optionSets[$type] ?? []) as $option): ?><option value="<?= esc($optionValue($option)) ?>" <?= $selected($optionSets[$type] ?? [], $item[$field] ?? '') ? (in_array((string) ($item[$field] ?? ''), [(string) ($option['id'] ?? ''), (string) ($option['code'] ?? ''), (string) ($option['name'] ?? '')], true) ? 'selected' : '') : '' ?>><?= esc($option['name']) ?></option><?php endforeach; ?>
                                        </select>
                                    </td>
                                <?php endforeach; ?>
                                <td><textarea class="form-control" name="items[<?= (int) $index ?>][notes]" rows="2" maxlength="65535"><?= esc((string) ($item['notes'] ?? '')) ?></textarea></td>
                                <td><?= $item['dispensing_quantity'] === null ? 'Not recorded' : (int) $item['dispensing_quantity'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="d-flex justify-content-end mt-3">
                    <button class="btn btn-primary" type="submit">Save Prescription Changes</button>
                </div>
            </form>
        </div>
    </section>
</main>
<?= $this->endSection() ?>
