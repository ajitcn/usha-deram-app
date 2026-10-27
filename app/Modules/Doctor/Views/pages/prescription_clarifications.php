<?= $this->extend(DOCTOR_VIEW . 'layout') ?>
<?= $this->section('main-content') ?>
<main class="dashboard-container doctor-clarifications-page" aria-labelledby="doctor-clarifications-title">
    <div class="page-heading">
        <div>
            <div class="page-eyebrow">Doctor panel / pharmacy handoff</div>
            <h1 id="doctor-clarifications-title" class="h3 mb-1">Prescription clarifications</h1>
            <p class="text-muted mb-0">Review pharmacy questions and respond within your authorized clinic scope.</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <span class="badge badge-warning"><?= count($clarifications ?? []) ?> <?= $pendingOnly ? 'pending' : 'history' ?></span>
            <a class="btn btn-sm btn-light" href="<?= base_url('doctor/prescription-clarifications?status=all') ?>">View history</a>
        </div>
    </div>

    <?php if (($clarifications ?? []) === []): ?>
        <section class="card empty-state" role="status"><i class="bi bi-chat-left-text d-block h3 mb-2" aria-hidden="true"></i><strong>No pending clarification requests</strong><small>Pharmacy questions for your prescriptions will appear here.</small></section>
    <?php else: ?>
        <section class="card doctor-clarifications-card" aria-labelledby="doctor-clarifications-list-title">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center"><h2 id="doctor-clarifications-list-title" class="h5 mb-0">Clarification requests</h2><span class="small text-muted">Messages and responses are retained as a chronological record.</span></div>
            <div class="table-responsive">
                <table class="table mb-0 doctor-clarifications-table">
                    <caption class="sr-only">Prescription clarification requests</caption>
                    <thead><tr><th>Patient</th><th>Prescription</th><th>Medicine</th><th>Pharmacy requester</th><th>Status</th><th>Requested</th><th class="text-right">Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($clarifications as $row): ?>
                        <tr class="<?= ($selected['id'] ?? 0) === ($row['id'] ?? 0) ? 'table-active' : '' ?>">
                            <td><strong><?= esc($row['patient_name']) ?></strong></td>
                            <td><strong><?= esc($row['prescription_number'] ?? 'Prescription') ?></strong><small class="d-block text-muted"><?= esc($row['prescription_date'] ?? 'Date unavailable') ?></small></td>
                            <td><?= esc($row['medicine_name'] ?? 'Entire prescription') ?></td>
                            <td><strong><?= esc($row['pharmacy_name']) ?></strong><small class="d-block text-muted"><?= esc($row['requester_name']) ?></small></td>
                            <td><span class="badge badge-<?= $row['status'] === 'requested' ? 'warning' : ($row['status'] === 'rejected' ? 'danger' : 'success') ?>"><?= esc($row['status_label']) ?></span></td>
                            <td><?= esc($row['requested_display']) ?></td>
                            <td class="text-right"><a class="btn btn-sm btn-outline-primary" href="<?= base_url('doctor/prescription-clarifications?clarification_id=' . (int) $row['id'] . ($pendingOnly ? '' : '&status=all')) ?>">Review Clarification</a></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endif; ?>

    <?php if (!empty($selected)): ?>
        <?php $oldValues = !empty($selected['old_item_values']) ? json_decode((string) $selected['old_item_values'], true) : []; ?>
        <?php $newValues = !empty($selected['new_item_values']) ? json_decode((string) $selected['new_item_values'], true) : []; ?>
        <section class="card mt-3 doctor-clarification-detail" aria-labelledby="doctor-clarification-detail-title">
            <div class="card-header d-flex flex-wrap justify-content-between align-items-center"><div><h2 id="doctor-clarification-detail-title" class="h5 mb-1">Review Clarification</h2><span class="small text-muted"><?= esc($selected['prescription_number'] ?? 'Prescription') ?> · <?= esc($selected['patient_name']) ?></span></div><span class="badge badge-<?= $selected['status'] === 'requested' ? 'warning' : 'success' ?>"><?= esc($selected['status_label']) ?></span></div>
            <div class="card-body">
                <div class="row small mb-3"><div class="col-md-4"><strong>Patient</strong><div><?= esc($selected['patient_name']) ?></div></div><div class="col-md-4"><strong>Pharmacy</strong><div><?= esc($selected['pharmacy_name']) ?></div><small class="text-muted"><?= esc($selected['requester_name']) ?></small></div><div class="col-md-4"><strong>Requested</strong><div><?= esc($selected['requested_display']) ?></div></div></div>
                <div class="alert alert-warning"><strong>Pharmacy message</strong><div class="mt-1 clarification-long-text"><?= nl2br(esc($selected['pharmacy_message'] ?? 'No message recorded.')) ?></div></div>
                <?php if (($selected['medicine_name'] ?? '') !== ''): ?>
                    <?php $clarificationDuration = trim((string) ($selected['medicine_duration'] ?? $selected['duration'] ?? '')); ?>
                    <?php if ($clarificationDuration !== ''): ?><div class="doctor-clarification-summary card card-body mb-3"><h3 class="h6">Prescription Summary</h3><strong>Duration Days: <?= esc($clarificationDuration . ($clarificationDuration === '1' ? ' day' : ' days')) ?></strong></div><?php endif; ?><div class="doctor-clarification-medicine card card-body mb-3"><h3 class="h6">Medicine involved</h3><div class="row small"><div class="col-md-4"><strong>Medicine</strong><div><?= esc($selected['medicine_name']) ?></div></div><div class="col-md-2"><strong>Dosage</strong><div><?= esc($selected['dosage'] ?? 'Not recorded') ?></div></div><div class="col-md-2"><strong>Frequency</strong><div><?= esc($selected['frequency'] ?? 'Not recorded') ?></div></div><div class="col-md-4"><strong>Instructions</strong><div class="clarification-long-text"><?= esc($selected['instructions'] ?? 'Not recorded') ?></div></div></div></div>
                <?php endif; ?>
                <?php if (($selected['status'] ?? '') !== 'requested'): ?>
                    <div class="alert alert-success"><strong>Doctor response</strong><div class="mt-1 clarification-long-text"><?= nl2br(esc($selected['doctor_response'] ?? 'No response recorded.')) ?></div><small>Action: <?= esc(ucwords(str_replace('_', ' ', (string) ($selected['doctor_action'] ?? '')))) ?> · <?= esc($selected['responded_display']) ?></small></div>
                    <?php if (is_array($oldValues) && is_array($newValues) && ($oldValues !== [] || $newValues !== [])): ?>
                        <div class="border rounded p-3 mb-3">
                            <h3 class="h6 mb-2">Prescription changes</h3>
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead><tr><th scope="col">Field</th><th scope="col">Previous value</th><th scope="col">Updated value</th></tr></thead>
                                    <tbody>
                                    <?php foreach (['medicine_name' => 'Medicine', 'dosage' => 'Dosage', 'frequency' => 'Frequency', 'duration' => 'Duration', 'duration_unit' => 'Duration unit', 'instructions' => 'Instructions', 'notes' => 'Additional notes'] as $field => $label): ?>
                                        <?php $old = trim((string) ($oldValues[$field] ?? '')); $new = trim((string) ($newValues[$field] ?? '')); ?>
                                        <?php if ($old !== $new): ?><tr><th scope="row"><?= esc($label) ?></th><td class="clarification-long-text"><?= esc($old !== '' ? $old : 'Not recorded') ?></td><td class="clarification-long-text"><?= esc($new !== '' ? $new : 'Not recorded') ?></td></tr><?php endif; ?>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php elseif ($canRespond): ?>
                    <form id="doctor-clarification-response-form" data-action="<?= base_url('doctor/prescription-clarifications/' . (int) $selected['id'] . '/respond') ?>">
                        <?= csrf_field() ?><input type="hidden" name="action" id="doctor-clarification-action"><input type="hidden" name="clarification_id" value="<?= (int) $selected['id'] ?>">
                        <div class="form-group"><label for="doctor-clarification-response">Response message <span class="text-danger">*</span></label><textarea class="form-control" name="response_message" id="doctor-clarification-response" rows="4" maxlength="4000" required placeholder="Explain the clarification decision for the pharmacy."></textarea><div class="validation-error" id="response_messageErr"></div></div>
                        <?php if (($selected['medicine_name'] ?? '') !== ''): ?><div class="border rounded p-3 mb-3"><h3 class="h6">Updated medicine details <small class="text-muted">Complete these only when correcting the prescription.</small></h3><div class="form-row"><div class="form-group col-md-4"><label for="clarification-medicine-name">Medicine</label><input class="form-control" id="clarification-medicine-name" name="medicine_name" value="<?= esc($selected['medicine_name'] ?? '', 'attr') ?>"></div><div class="form-group col-md-2"><label for="clarification-dosage">Dosage</label><input class="form-control" id="clarification-dosage" name="dosage" value="<?= esc($selected['dosage'] ?? '', 'attr') ?>"></div><div class="form-group col-md-2"><label for="clarification-frequency">Frequency</label><input class="form-control" id="clarification-frequency" name="frequency" value="<?= esc($selected['frequency'] ?? '', 'attr') ?>"></div><div class="form-group col-md-2"><label for="clarification-duration">Duration Days</label><input class="form-control" id="clarification-duration" name="duration" value="<?= esc($selected['medicine_duration'] ?? $selected['duration'] ?? '', 'attr') ?>"></div><div class="form-group col-md-2"><label for="clarification-duration-unit">Unit</label><input class="form-control" id="clarification-duration-unit" name="duration_unit" value="<?= esc($selected['duration_unit'] ?? '', 'attr') ?>"></div><div class="form-group col-12"><label for="clarification-instructions">Instructions</label><textarea class="form-control" id="clarification-instructions" name="instructions" rows="2"><?= esc($selected['instructions'] ?? '') ?></textarea></div><div class="form-group col-12 mb-0"><label for="clarification-notes">Additional notes</label><textarea class="form-control" id="clarification-notes" name="notes" rows="2"><?= esc($selected['item_notes'] ?? '') ?></textarea></div></div></div><?php endif; ?>
                        <div class="d-flex flex-wrap justify-content-end gap-2"><button class="btn btn-outline-primary js-clarification-action" type="button" data-action="respond">Respond</button><?php if (($selected['medicine_name'] ?? '') !== ''): ?><button class="btn btn-primary js-clarification-action" type="button" data-action="update">Update prescription and respond</button><?php endif; ?><button class="btn btn-success js-clarification-action" type="button" data-action="confirm">Confirm without changes</button><button class="btn btn-outline-danger js-clarification-action" type="button" data-action="reject">Reject</button><button class="btn btn-light js-clarification-action" type="button" data-action="close">Close request</button></div><div class="validation-error mt-2" id="clarificationActionErr"></div>
                    </form>
                <?php endif; ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?= $this->section('scripts') ?><script>
$(function () {
    $(document).on('click', '.js-clarification-action', function () {
        const button = $(this), form = $('#doctor-clarification-response-form');
        if (!form.length || button.prop('disabled')) return;
        const action = button.data('action');
        $('#doctor-clarification-action').val(action);
        clearFormErrors(form); $('#clarificationActionErr').text('');
        button.prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({url: form.data('action'), method: 'POST', data: form.serialize(), dataType: 'json'}).done(function (response) {
            if (response.status) { showSuccess(response.msg); reload_page(700); return; }
            bindFormError(form, response.errors || {}); $('#clarificationActionErr').text(response.msg || 'Clarification could not be completed.'); showError(response.msg || 'Clarification could not be completed.');
        }).fail(function (xhr) { handleFormAjaxError(form, xhr, 'Clarification could not be completed safely.'); }).always(function () { button.prop('disabled', false).removeAttr('aria-busy'); });
    });
});
</script><?= $this->endSection() ?>
<?= $this->endSection() ?>
