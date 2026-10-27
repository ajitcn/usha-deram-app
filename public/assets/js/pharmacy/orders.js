(function ($) {
    'use strict';

    const appBase = (typeof BASE_URL !== 'undefined' ? BASE_URL : (window.BASE_URL || '/')).replace(/\/+$/, '');
    const base = appBase + '/pharmacy/orders/';

    function esc(value) { return $('<div>').text(value == null ? '' : value).html(); }
    function human(value) { return String(value || 'Unknown').replace(/_/g, ' ').replace(/\b\w/g, function (letter) { return letter.toUpperCase(); }); }
    const statusMaps = {
        order: { pending: ['Pending', 'pending'], approved: ['Approved', 'approved'], confirmed: ['Confirmed', 'approved'], processing: ['Processing', 'processing'], ready: ['Ready', 'success'], fulfilled: ['Completed', 'success'], delivered: ['Delivered', 'success'], cancelled: ['Cancelled', 'danger'], rejected: ['Rejected', 'danger'], failed: ['Failed', 'danger'] },
        payment: { pending: ['Unpaid', 'pending'], unpaid: ['Unpaid', 'pending'], authorized: ['Authorized', 'approved'], partially_paid: ['Partially Paid', 'partial'], paid: ['Paid', 'success'], failed: ['Failed', 'danger'], cancelled: ['Cancelled', 'danger'], refunded: ['Refunded', 'refunded'] },
        delivery: { pending: ['Pending', 'pending'], packed: ['Packed', 'processing'], dispatched: ['Dispatched', 'processing'], out_for_delivery: ['Out for Delivery', 'processing'], delivered: ['Delivered', 'success'], failed: ['Failed', 'danger'], returned: ['Returned', 'danger'] }
    };
    function statusMeta(domain, value) { const raw = String(value == null ? '' : value).trim().toLowerCase().replace(/[ -]+/g, '_'); if (!raw || raw === 'not_recorded' || raw === 'unknown') return { label: 'Not recorded', tone: 'neutral' }; const entry = (statusMaps[domain] || {})[raw]; return entry ? { label: entry[0], tone: entry[1] } : { label: 'Unknown status', tone: 'neutral' }; }
    function display(value) { const raw = String(value == null ? '' : value).trim(); return raw === '' || raw.toLowerCase() === 'not recorded' ? 'Not recorded' : raw; }
    function displayDateTime(value) { const raw = String(value || ''); const match = raw.match(/^(\d{4})-(\d{2})-(\d{2})(?:[ T](\d{2}:\d{2}))?/); return match ? match[3] + '-' + match[2] + '-' + match[1] + (match[4] ? ' ' + match[4] : '') : display(value); }
    function loading(message) { return '<div class="text-center text-muted py-4"><span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>' + esc(message) + '</div>'; }
    function detailInfo(label, value) { return '<div class="order-detail-info-item"><span class="order-card-label">' + esc(label) + '</span><strong>' + esc(display(value)) + '</strong></div>'; }
    function statusBadge(value, domain) { const meta = statusMeta(domain, value); return '<span class="badge pharmacy-status-badge pharmacy-status-badge--' + meta.tone + '">' + esc(meta.label) + '</span>'; }
    function auditRows(history) { return (history || []).map(function (event) { return '<li class="order-audit-item"><strong>' + esc(event.action || 'Activity') + '</strong><span>' + esc(displayDateTime(event.occurred_at)) + '</span></li>'; }).join('') || '<li class="order-audit-item text-muted">No audit events recorded.</li>'; }
    function durationSummary(record) { const common = record && !Array.isArray(record) && record.medicine_duration != null ? String(record.medicine_duration).trim() : ''; return common === '' ? '' : common + (common === '1' ? ' day' : ' days'); }
    function relocateDuration(container, items) { const table = container.find('table').first(); if (table.length && /Duration Days/i.test(table.find('th').eq(1).text())) { table.find('tr').each(function () { $(this).find('th,td').eq(1).remove(); }); } container.find('.pharmacy-prescription-item small').remove(); const duration = durationSummary(items); if (duration) container.find('.order-detail-section').eq(1).prepend($('<div class="pharmacy-prescription-summary"><strong>Prescription Summary</strong><span>Duration Days: ' + esc(duration) + '</span></div>')); }

    function showDetail(orderId) {
        $('#orderDetailBody').html(loading('Loading order details...'));
        $('#orderDetailSummary').text('Loading order details...');
        $('#pharmacyOrderDetailModal').modal('show');
        ajaxRequest({ url: base + 'detail/' + encodeURIComponent(orderId), method: 'GET', dataType: 'json' }).done(function (response) {
            if (!response.status || !response.data || !response.data.order) {
                $('#orderDetailBody').html('<div class="alert alert-danger" role="alert">Order details are unavailable.</div>');
                return;
            }
            const o = response.data.order;
            const invoice = o.invoice || {};
            const payment = o.payment_summary || {};
            const history = (o.payment_history || []).map(function (transaction) { return '<tr><td>' + esc(displayDateTime(transaction.paid_at)) + '</td><td>' + esc(human(transaction.transaction_type || 'payment')) + '</td><td>' + esc(statusMeta('payment', transaction.status || 'unknown').label) + '</td><td class="text-right">' + esc((invoice.currency || 'INR') + ' ' + Number(transaction.amount || 0).toFixed(2)) + '</td><td>' + esc(transaction.payment_method || 'Not recorded') + '</td><td>' + esc(transaction.reference_number || 'Not recorded') + '</td></tr>'; }).join('');
            const pricing = '<section class="order-detail-section"><h3 class="order-detail-heading">Pricing and payment summary</h3><div class="order-detail-info-grid">' + detailInfo('Medicine subtotal', (invoice.currency || 'INR') + ' ' + Number(invoice.subtotal || 0).toFixed(2)) + detailInfo('Courier charges', (invoice.currency || 'INR') + ' ' + Number(invoice.courier_charges || 0).toFixed(2)) + detailInfo('Packaging charges', (invoice.currency || 'INR') + ' ' + Number(invoice.tax || 0).toFixed(2)) + detailInfo('Discount', (invoice.currency || 'INR') + ' ' + Number(invoice.discount || 0).toFixed(2) + (invoice.discount_type === 'percentage' ? ' (' + Number(invoice.discount_value || 0).toFixed(2) + '%)' : '')) + detailInfo('Grand total', (invoice.currency || 'INR') + ' ' + Number(invoice.total || o.total_amount || 0).toFixed(2)) + detailInfo('Paid amount', (invoice.currency || 'INR') + ' ' + Number(payment.paid_amount || 0).toFixed(2)) + detailInfo('Remaining balance', (invoice.currency || 'INR') + ' ' + Number(payment.remaining_balance || 0).toFixed(2)) + detailInfo('Payment status', payment.payment_status_label || human(payment.payment_status || o.payment_status)) + '</div></section><section class="order-detail-section"><h3 class="order-detail-heading">Payment history</h3><div class="table-responsive order-detail-table-wrap"><table class="table table-sm mb-0"><caption class="sr-only">Payment transaction history</caption><thead><tr><th scope="col">Date</th><th scope="col">Type</th><th scope="col">Status</th><th scope="col">Amount</th><th scope="col">Method</th><th scope="col">Reference</th></tr></thead><tbody>' + (history || '<tr><td colspan="6">No payment transactions recorded.</td></tr>') + '</tbody></table></div></section>';
            $('#orderDetailSummary').text((o.order_number || 'Order') + ' · ' + (o.patient_name || 'Patient') + ' · ' + (o.prescription_number || 'Prescription'));
            const medicineRows = (o.items || []).map(function (item) {
                return '<tr><td>' + esc(display(item.medicine_name)) + '</td><td>' + esc(display(item.batch_number)) + '</td><td>' + esc(item.quantity || '0') + '</td><td>' + esc(Number(item.unit_price || 0).toFixed(2)) + '</td><td>' + esc(Number(item.line_total || 0).toFixed(2)) + '</td><td>' + esc([item.dosage, item.frequency, item.instructions, item.notes].filter(Boolean).join(' · ') || 'Not recorded') + '</td></tr>';
            }).join('');
            const html = '<section class="order-detail-section"><h3 class="order-detail-heading">Order Information</h3><div class="order-detail-info-grid">' + detailInfo('Order number', o.order_number) + detailInfo('Clinic', o.clinic_name) + detailInfo('Order date', o.created_at_display || displayDateTime(o.created_at)) + detailInfo('Last updated', o.updated_at_display || displayDateTime(o.updated_at)) + detailInfo('Order status', statusMeta('order', o.status).label) + '</div></section>'
                + '<section class="order-detail-section"><h3 class="order-detail-heading">Patient and Prescription</h3><div class="order-detail-info-grid">' + detailInfo('Patient', o.patient_name) + detailInfo('Patient number', o.registration_number) + detailInfo('Mobile', o.patient_mobile) + detailInfo('Prescription', o.prescription_number) + detailInfo('Prescription date', o.prescription_date_display || o.prescription_date) + '</div></section>'
                + '<section class="order-detail-section"><h3 class="order-detail-heading">Medicines and Stock</h3><div class="table-responsive order-detail-table-wrap"><table class="table table-sm mb-0"><caption class="sr-only">Order medicines, stock batches, quantities, and prices</caption><thead><tr><th scope="col">Medicine</th><th scope="col">Batch</th><th scope="col">Quantity</th><th scope="col">Unit price</th><th scope="col">Line total</th><th scope="col">Directions</th></tr></thead><tbody>' + (medicineRows || '<tr><td colspan="6">No medicines recorded.</td></tr>') + '</tbody></table></div><div class="text-right font-weight-bold mt-3">Total: ' + esc(Number(o.total_amount || 0).toFixed(2)) + '</div></section>'
                + pricing
                + '<section class="order-detail-section"><h3 class="order-detail-heading">Payment and Invoice</h3><div class="order-detail-status-row">' + statusBadge(o.payment_status, 'payment') + '</div><div class="order-detail-info-grid mt-3">' + detailInfo('Invoice number', invoice.invoice_number || o.invoice_number) + detailInfo('Invoice status', invoice.status) + detailInfo('Payment provider', o.payment_provider) + detailInfo('Payment reference', o.payment_reference) + detailInfo('Invoice total', (invoice.currency || 'INR') + ' ' + Number(invoice.total || o.total_amount || 0).toFixed(2)) + '</div></section>'
                + '<section class="order-detail-section"><h3 class="order-detail-heading">Delivery</h3><div class="order-detail-status-row">' + statusBadge(o.delivery_status, 'delivery') + '</div><div class="order-detail-info-grid mt-3">' + detailInfo('Provider', o.delivery_provider) + detailInfo('Tracking reference', o.tracking_reference) + detailInfo('Address snapshot', o.delivery_address) + detailInfo('Contact snapshot', o.delivery_contact) + detailInfo('Dispatched date', o.dispatched_at_display || o.dispatched_at) + detailInfo('Delivered date', o.delivered_at_display || o.delivered_at) + detailInfo('Failure reason', o.failure_reason) + detailInfo('Return reason', o.return_reason) + '</div></section>'
                + '<section class="order-detail-section"><h3 class="order-detail-heading">Audit History</h3><ul class="order-audit-list mb-0">' + auditRows(o.audit_history) + '</ul></section>';
            $('#orderDetailBody').html(html);
            relocateDuration($('#orderDetailBody'), o);
        }).fail(function (xhr) {
            $('#orderDetailBody').html('<div class="alert alert-danger" role="alert">' + esc((xhr.responseJSON && xhr.responseJSON.msg) || 'Order details could not be loaded safely.') + '</div>');
        });
    }

    function renderRelated(data) {
        const row = data.billing || data.delivery || data;
        const labels = data.billing ? [['Invoice', row.invoice_number || 'Unavailable'], ['Invoice status', row.invoice_status || 'Unavailable'], ['Payment status', statusMeta('payment', row.payment_status).label], ['Amount', (row.currency || 'INR') + ' ' + Number(row.amount || row.invoice_total || 0).toFixed(2)], ['Provider', row.provider || 'Not recorded'], ['Reference', row.payment_reference || 'Not recorded'], ['Updated', row.updated_at || 'Not recorded']] : [['Delivery status', statusMeta('delivery', row.delivery_status).label], ['Provider', row.provider || 'Not recorded'], ['Tracking/reference', row.tracking_reference || 'Not recorded'], ['Dispatched', row.dispatched_at || 'Not recorded'], ['Delivered', row.delivered_at || 'Not recorded'], ['Failure reason', row.failure_reason || 'Not recorded'], ['Return reason', row.return_reason || 'Not recorded']];
        return '<div class="order-detail-section"><div class="order-detail-info-grid">' + labels.map(function (item) { return detailInfo(item[0], item[1]); }).join('') + '</div></div>';
    }
    function bindCancelErrors(form, errors) { bindFormError(form, errors || {}); if (errors && errors.reason) $('#cancel_reasonErr').text(errors.reason); if (errors && errors.confirmation) $('#cancel_confirmationErr').text(errors.confirmation); }
    function currency(value) { return '₹' + Number(value || 0).toFixed(2); }
    function pricingNumber(value) { const number = Number(String(value == null ? '' : value).trim()); return Number.isFinite(number) && number >= 0 ? number : 0; }
    function updatePricing(form, items) {
        const subtotal = (items || []).reduce(function (total, item) { return total + pricingNumber(item.unit_price) * pricingNumber(item.dispensing_quantity); }, 0);
        const courier = pricingNumber(form.find('#courierCharges').val());
        const other = pricingNumber(form.find('#otherCharges').val());
        const discountType = form.find('#discountType').val() === 'percentage' ? 'percentage' : 'fixed';
        const discountValue = pricingNumber(form.find('#discountAmount').val());
        const discount = discountType === 'percentage' ? subtotal * discountValue / 100 : discountValue;
        form.find('#medicineSubtotalDisplay').text(currency(subtotal));
        form.find('#grandTotalDisplay').text(currency(Math.max(0, subtotal + courier + other - discount)));
        form.find('#discountPrefix').text(discountType === 'percentage' ? '−%' : '−₹');
        form.find('#discountAmount').attr('max', discountType === 'percentage' ? '100' : '9999999999.99');
    }
    function money(value) {
        return '₹' + Number(value).toFixed(2);
    }
    function savedQuantity(item) {
        const value = Number(item && item.dispensing_quantity);
        return Number.isInteger(value) && value > 0 && value <= 100000 ? value : null;
    }
    function rateValue(item) {
        const value = Number(item && item.unit_price);
        return Number.isFinite(value) && value >= 0 ? value : null;
    }
    function resetPricing(form) {
        form.removeData('pricing-items').find('#pharmacyOrderPricing').prop('hidden', true);
        form.find('#courierCharges, #discountAmount, #otherCharges').val('0');
        form.find('#discountType').val('fixed');
        form.find('#medicineSubtotalDisplay, #grandTotalDisplay').text(currency(0));
        form.find('#discountPrefix').text('−₹');
    }
    function renderPrescriptionDetails(form, prescription) {
        const items = prescription.items || [];
        const missingQuantity = items.some(function (item) { return !/^[1-9][0-9]*$/.test(String(item.dispensing_quantity == null ? '' : item.dispensing_quantity)) || Number(item.dispensing_quantity) > 100000; });
        const readiness = missingQuantity ? '<div class="alert alert-warning mt-3 mb-0" role="alert">Complete Prescription Review and save a valid dispensing quantity for every medicine before creating this order.</div>' : '';
        const rows = items.map(function (item) {
            const quantity = savedQuantity(item);
            const rate = rateValue(item);
            const amount = quantity !== null && rate !== null ? quantity * rate : null;
            return '<tr><td class="pharmacy-prescription-medicine">' + esc(item.medicine_name || 'Medicine not recorded') + '</td><td class="text-right pharmacy-prescription-number">' + esc(quantity === null ? 'Quantity not entered' : 'Qty: ' + String(quantity)) + '</td><td class="text-right pharmacy-prescription-number">' + esc(rate === null ? 'Rate not available' : money(rate)) + '</td><td class="text-right pharmacy-prescription-number pharmacy-prescription-amount">' + esc(amount === null ? 'Amount not available' : money(amount)) + '</td></tr>';
        }).join('');
        const table = '<div class="pharmacy-prescription-table-wrap"><table class="table table-sm pharmacy-prescription-table mb-0"><caption class="sr-only">Selected prescription medicines and order amounts</caption><thead><tr><th scope="col">Medicine</th><th scope="col" class="text-right">Qty</th><th scope="col" class="text-right">Rate</th><th scope="col" class="text-right">Amount</th></tr></thead><tbody>' + (rows || '<tr><td colspan="4" class="text-muted text-center">No medicines recorded.</td></tr>') + '</tbody></table></div>';
        form.find('#pharmacyPrescriptionDetails').html(readiness + table).prop('hidden', false);
        const duration = durationSummary(prescription);
        if (duration) form.find('#pharmacyPrescriptionDetails').prepend('<div class="pharmacy-prescription-summary"><strong>Prescription Summary</strong><span>Duration Days: ' + esc(duration) + '</span></div>');
        form.find('#pharmacyPrescriptionDetails .pharmacy-prescription-item small').remove();
        form.find('#pharmacyPrescriptionState').prop('hidden', true);
        form.data('pricing-items', items);
        form.find('#pharmacyOrderPricing').prop('hidden', false);
        updatePricing(form, items);
        form.find('button[type="submit"]').prop('disabled', items.length === 0 || missingQuantity);
    }
    function loadPrescriptionDetails(form, prescriptionId) {
        const state = form.find('#pharmacyPrescriptionState');
        const details = form.find('#pharmacyPrescriptionDetails');
        details.prop('hidden', true).empty();
        resetPricing(form);
        if (!prescriptionId) { state.text('Select an approved prescription to load its medicines.').removeClass('text-danger').prop('hidden', false); form.find('button[type="submit"]').prop('disabled', true); return; }
        state.html('<span class="spinner-border spinner-border-sm mr-2" role="status" aria-hidden="true"></span>Loading prescription medicines...').removeClass('text-danger').prop('hidden', false);
        form.find('button[type="submit"]').prop('disabled', true);
        ajaxRequest({ url: base + 'prescription/' + encodeURIComponent(prescriptionId), method: 'GET', dataType: 'json' }).done(function (response) {
            if (!response.status || !response.data || !response.data.prescription) { state.text(response.msg || 'Prescription details are unavailable.').addClass('text-danger'); return; }
            renderPrescriptionDetails(form, response.data.prescription);
        }).fail(function (xhr) { state.text((xhr.responseJSON && xhr.responseJSON.msg) || 'Prescription details could not be loaded safely.').addClass('text-danger'); });
    }

    $(document).on('click', '.js-order-detail', function () { showDetail($(this).data('order-id')); });
    $(document).on('click', '.js-order-related', function () { const button = $(this); $('#pharmacyOrderRelatedTitle').text(button.data('related-title') || 'Details'); $('#pharmacyOrderRelatedBody').html(loading('Loading details...')); $('#pharmacyOrderRelatedModal').modal('show'); ajaxRequest({ url: button.data('related-url'), method: 'GET', dataType: 'json' }).done(function (response) { if (!response.status || !response.data) { $('#pharmacyOrderRelatedBody').html('<div class="alert alert-danger" role="alert">Details are unavailable.</div>'); return; } $('#pharmacyOrderRelatedBody').html(renderRelated(response.data)); }).fail(function (xhr) { $('#pharmacyOrderRelatedBody').html('<div class="alert alert-danger" role="alert">' + esc((xhr.responseJSON && xhr.responseJSON.msg) || 'Details could not be loaded safely.') + '</div>'); }); });
    $(document).on('click', '.js-order-cancel', function () { $('#cancelOrderId').val($(this).data('order-id')); $('#cancelOrderLabel').text('Cancel ' + $(this).data('order-number') + '? Allocated stock will be released.'); $('#cancelReason').val(''); $('#cancelConfirmation').prop('checked', false); $('#cancel_reasonErr, #cancel_confirmationErr').empty(); $('#pharmacyOrderCancelModal').modal('show'); });
    $('#pharmacyOrderCancelForm').on('submit', function (event) { event.preventDefault(); const form = $(this); const submit = form.find('button[type="submit"]'); if (submit.prop('disabled')) return; clearFormErrors(form); submit.prop('disabled', true).attr('aria-busy', 'true'); ajaxRequest({ url: base + 'cancel', method: 'POST', data: form.serialize(), dataType: 'json' }).done(function (response) { if (response.status) { $('#pharmacyOrderCancelModal').modal('hide'); window.location.reload(); return; } bindCancelErrors(form, response.errors || {}); showError(response.msg || 'The order could not be cancelled.'); }).fail(function (xhr) { handleFormAjaxError(form, xhr, 'The order could not be cancelled safely.'); bindCancelErrors(form, (xhr.responseJSON && xhr.responseJSON.errors) || {}); }).always(function () { submit.prop('disabled', false).removeAttr('aria-busy'); }); });
    $('#pharmacyOrderCreateModal').on('shown.bs.modal', function () { const select = $(this).find('.btSelect'); if (select.length && select.data('selectpicker')) select.selectpicker('refresh'); });
    $('#orderPrescription').on('change', function () { const form = $('#pharmacyOrderCreateForm'); clearFormErrors(form); loadPrescriptionDetails(form, $(this).val()); });
    $(document).on('input change', '#pharmacyOrderCreateForm .pharmacy-pricing-input', function () { const form = $('#pharmacyOrderCreateForm'); updatePricing(form, form.data('pricing-items') || []); });
    $('#pharmacyOrderCreateForm').on('submit', function (event) { event.preventDefault(); const form = $(this); const prescription = form.find('[name="prescription_id"]'); const submit = form.find('button[type="submit"]'); if (submit.prop('disabled')) return; clearFormErrors(form); if (!prescription.val()) { bindFormError(form, {prescription_id: 'Select a valid approved medicine prescription.'}); showError('Select a medicine before creating the order.'); const pickerButton = prescription.closest('.bootstrap-select').find('button').first(); (pickerButton.length ? pickerButton : prescription).trigger('focus'); return; } submit.prop('disabled', true).attr('aria-busy', 'true'); ajaxRequest({ url: form.attr('action'), method: 'POST', data: form.serialize(), dataType: 'json' }).done(function (response) { if (response.status) { $('#pharmacyOrderCreateModal').modal('hide'); window.location.reload(); } else { bindFormError(form, response.errors || {}); showError(response.msg || 'The order could not be created.'); } }).fail(function (xhr) { handleFormAjaxError(form, xhr, 'The order could not be created safely.'); }).always(function () { submit.prop('disabled', false).removeAttr('aria-busy'); }); });
})(jQuery);
