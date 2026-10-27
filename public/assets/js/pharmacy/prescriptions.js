(function ($) {
    'use strict';

    function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
    function durationSummary(record) {
        const common = record && record.medicine_duration != null ? String(record.medicine_duration).trim() : '';
        if (common !== '') return common + (common === '1' ? ' day' : ' days');
        return '';
    }
    function recordFrom(button) { try { return JSON.parse($(button).attr('data-record') || '{}'); } catch (error) { return {}; } }
    function statusBadgeClass(status) {
        status = String(status || '').toLowerCase();
        if (status.indexOf('approved') !== -1) return 'badge-success';
        if (status.indexOf('rejected') !== -1 || status.indexOf('cancel') !== -1 || status.indexOf('expired') !== -1 || status.indexOf('inactive') !== -1) return 'badge-danger';
        if (status.indexOf('clarif') !== -1 || status.indexOf('pending') !== -1 || status.indexOf('submitted') !== -1) return 'badge-warning';
        if (status === '' || status === 'not recorded' || status === 'unknown') return 'badge-light';
        return 'badge-info';
    }
    function setStatusBadge(selector, value) {
        const text = String(value || 'Not recorded');
        $(selector).removeClass('badge-light badge-info badge-success badge-warning badge-danger').addClass(statusBadgeClass(text)).text(text);
    }
    function renderClarificationHistory(history) {
        const container = $('#review-clarification-history-content');
        const section = $('.review-clarification-history');
        if (!Array.isArray(history) || !history.length) {
            section.addClass('d-none');
            container.empty();
            return;
        }
        section.removeClass('d-none');
        container.html(history.map(function (item) {
            const action = item.doctor_action ? '<div class=\"small text-muted mt-1\">Doctor action: ' + esc(String(item.doctor_action).replace(/_/g, ' ')) + '</div>' : '';
            const response = item.doctor_response ? '<div class=\"clarification-history-response\"><strong>Doctor response</strong><div class=\"clarification-long-text\">' + esc(item.doctor_response) + '</div>' + action + '</div>' : '';
            const message = item.pharmacy_message ? '<div class=\"clarification-history-message\"><strong>Pharmacy message</strong><div class=\"clarification-long-text\">' + esc(item.pharmacy_message) + '</div></div>' : '';
            return '<article class=\"clarification-history-item\"><div class=\"d-flex flex-wrap justify-content-between align-items-center\"><strong>' + esc(item.status_label || 'Clarification update') + '</strong><small class=\"text-muted\">' + esc(item.responded_at || item.requested_at || 'Date not recorded') + '</small></div>' + message + response + '</article>';
        }).join(''));
    }
    let canEditDispensingQuantity = false;
    function validateDispensingQuantities(form) {
        let valid = true;
        form.find('.review-dispensing-quantity').add('[form="pharmacy-review-form"].review-dispensing-quantity').each(function () {
            const input = $(this);
            const value = String(input.val() || '').trim();
            const error = input.siblings('.validation-error');
            error.empty();
            input.removeClass('is-invalid');
            if (!/^[1-9][0-9]*$/.test(value) || Number(value) > 100000) {
                valid = false;
                input.addClass('is-invalid');
                error.text('Enter a positive whole number up to 100000.');
            }
        });
        return valid;
    }
    function loadRecord(record, decision) {
        $('#review-prescription-id').val(record.id || '');
        $('#review-patient-name').text(record.patient_name || 'Patient not recorded');
        $('#review-patient-number').text(record.patient_number || 'Not recorded');
        $('#review-patient-mobile').text(record.patient_mobile || 'Not recorded');
        $('#review-doctor-name').text(record.doctor_name || 'Doctor not recorded');
        $('#review-doctor-code').text(record.doctor_code || 'Not recorded');
        $('#review-prescription-date').text(record.prescription_date || 'Not recorded');
        $('#review-prescription-number').text(record.prescription_number || 'Not recorded');
        setStatusBadge('#review-current-status', record.status_label);
        setStatusBadge('#review-state', record.review_state);
        $('#review-date-label').text(record.review_date_label || 'Last updated');
        $('#review-date').text(record.review_date || 'Not recorded');
        const durationText = durationSummary(record);
        $('#review-duration-summary').text(durationText);
        $('#review-duration-summary-wrap').toggleClass('d-none', durationText === '');
        renderClarificationHistory(record.clarification_history || []);
        $('#review-header-number').text(record.prescription_number || 'Prescription not recorded');
        $('#review-header-date').text(record.prescription_date || 'Date not recorded');
        $('#review-header-status').removeClass('badge-light badge-info badge-success badge-warning badge-danger').addClass(statusBadgeClass(record.status_label)).text(record.status_label || 'Status unavailable');
        $('#review-header-state').removeClass('badge-light badge-info badge-success badge-warning badge-danger').addClass(statusBadgeClass(record.review_state)).text(record.review_state || 'Review state unavailable');
        canEditDispensingQuantity = Boolean(record.can_edit_dispensing_quantity);
        $('#pharmacy-prescription-modal-subtitle').text((record.review_state || '') + (record.notes ? ' · ' + record.notes : ''));
        $('#review-medicines-heading').text('Prescribed Medicines');
        const rows = (record.items || []).map(function (item, index) {
            const prescriptionItemId = String(item.prescription_item_id || item.id || 0);
            const medicineId = item.medicine_id == null ? '' : String(item.medicine_id);
            const savedQuantity = item.dispensing_quantity == null ? '' : String(item.dispensing_quantity);
            const quantityControl = canEditDispensingQuantity ? '<input type="hidden" form="pharmacy-review-form" name="items[' + esc(index) + '][prescription_item_id]" value="' + esc(prescriptionItemId) + '"><label class="sr-only" for="review-dispensing-quantity-' + esc(prescriptionItemId) + '">Dispensing Quantity for ' + esc(item.medicine_name || 'medicine') + '</label><input id="review-dispensing-quantity-' + esc(prescriptionItemId) + '" class="form-control review-dispensing-quantity" data-prescription-item-id="' + esc(prescriptionItemId) + '" data-medicine-id="' + esc(medicineId) + '" form="pharmacy-review-form" type="number" name="items[' + esc(index) + '][quantity]" value="' + esc(savedQuantity) + '" min="1" max="100000" step="1" inputmode="numeric" placeholder="Enter quantity" required aria-describedby="review-items-' + esc(prescriptionItemId) + '-quantityErr"><div class="validation-error" id="review-items-' + esc(prescriptionItemId) + '-quantityErr" data-error-for="items.' + esc(index) + '.quantity"></div>' : '<span class="review-saved-quantity">' + esc(savedQuantity || 'Not entered') + '</span>';
            const durationValue = item.duration == null ? '' : String(item.duration).trim();
            const durationText = durationValue === '' ? 'Not specified' : durationValue + (durationValue === '1' ? ' day' : ' days');
            return '<tr><td>' + esc(item.medicine_name || 'Not recorded') + '</td><td>' + esc(durationText) + '</td><td>' + esc(item.frequency || 'Not recorded') + '</td><td>' + esc(item.dose || 'Not recorded') + '</td><td>' + esc(item.intake || 'Not recorded') + '</td><td>' + esc(item.note || 'Not recorded') + '</td><td>' + quantityControl + '</td></tr>';
        });
        $('#review-medicine-rows').html(rows.length ? rows.join('') : '<tr><td colspan="7" class="text-muted">No medicine details recorded.</td></tr>');
        $('#review-decision').val(decision || '');
        $('#review-reason').val('');
        const itemSelect = $('#review-clarification-item').empty().append($('<option>', {value: 0, text: 'Entire prescription'}));
        (record.items || []).forEach(function (item) {
            $('<option>', {value: item.id || 0, text: item.medicine_name || 'Medicine'}).appendTo(itemSelect);
        });
        $('#review-clarification-item').val('0');
        $('#review-approve-confirmation').prop('checked', false);
        clearFormErrors('#pharmacy-review-form');
        setReviewMode(decision || '');
    }
    function setReviewMode(decision) {
        const active = decision !== '';
        $('.review-submit-action').addClass('d-none');
        if (active) $('.review-submit-action[data-decision="' + decision + '"]').removeClass('d-none');
        if (!active && canEditDispensingQuantity) $('.review-submit-action[data-decision="save_quantities"]').removeClass('d-none');
        $('.review-reason-group').toggleClass('d-none', !active || decision === 'approve');
        $('.review-clarification-item-group').toggleClass('d-none', decision !== 'clarification');
        $('.review-confirmation-group').toggleClass('d-none', !active || decision !== 'approve');
        $('.review-reason-required').toggle(decision !== 'approve');
        $('#review-modal-help').text(active ? 'Review action: ' + decision.replace('_', ' ') : 'Select a review action to continue.');
    }
    function submitReview(button) {
        const form = $('#pharmacy-review-form');
        const submit = $(button);
        if (submit.prop('disabled')) return;
        const decision = String(submit.data('decision') || '');
        $('#review-decision').val(decision);
        if (decision === 'cancel' && !window.confirm('Cancel this prescription review?')) return;
        clearFormErrors(form);
        if ((decision === 'approve' || decision === 'save_quantities') && !validateDispensingQuantities(form)) {
            showError('Enter a valid dispensing quantity for every medicine.');
            return;
        }
        submit.prop('disabled', true).attr('aria-busy', 'true');
        ajaxRequest({url: form.data('review-url'), method: 'POST', data: form.serialize(), dataType: 'json'}).done(function (response) {
            if (response.status) {
                $('#pharmacy-prescription-modal').modal('hide');
                showSuccess(response.msg);
                reload_page(700);
                return;
            }
            bindFormError(form, response.errors || {});
            showError(response.msg || 'Review could not be applied.');
        }).fail(function (xhr) {
            handleFormAjaxError(form, xhr, 'Review could not be applied safely.');
        }).always(function () {
            submit.prop('disabled', false).removeAttr('aria-busy');
        });
    }
    $(function () {
        $(document).on('click', '.prescription-detail-action, .prescription-review-action', function () { loadRecord(recordFrom(this), $(this).data('decision') || ''); });
        $(document).on('click', '.review-submit-action', function () { submitReview(this); });
    });
})(jQuery);
