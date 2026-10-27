(function (window, document) {
    'use strict';

    function baseUrl() { return (document.querySelector('meta[name="base-url"]') || {}).content || '/'; }
    function endpoint(path) { return baseUrl().replace(/\/$/, '') + '/admin/appointments/' + path; }
    function csrfToken(form) { return (form && form.querySelector('[name="csrf_rtn"]') || {}).value || ''; }
    function formatDate(value) { var parts = String(value || '').split('-'); return parts.length === 3 && parts[0].length === 4 ? parts[2] + '-' + parts[1] + '-' + parts[0] : String(value || ''); }

    function initWithoutJQuery() {
        var form = document.getElementById('appointmentForm');
        var modal = document.getElementById('appointmentModal');
        if (!form || !modal) return;
        var field = function (id) { return document.getElementById(id); };
        var show = function () {
            modal.style.display = 'block'; modal.removeAttribute('aria-hidden'); modal.setAttribute('aria-modal', 'true'); modal.classList.add('show'); document.body.classList.add('modal-open');
            if (!document.querySelector('.modal-backdrop')) { var backdrop = document.createElement('div'); backdrop.className = 'modal-backdrop fade show'; document.body.appendChild(backdrop); }
        };
        var hide = function () {
            modal.classList.remove('show'); modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); modal.removeAttribute('aria-modal'); document.body.classList.remove('modal-open'); document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) { backdrop.remove(); });
        };
        var toggleCancellation = function () { var status = field('appointment_status'), cancellation = document.getElementById('cancellationFields'); if (!status || !cancellation) return; cancellation.style.display = status.value === 'cancelled' ? '' : 'none'; if (status.value !== 'cancelled' && field('cancellation_reason')) field('cancellation_reason').value = ''; };
        var clearErrors = function () { form.querySelectorAll('.validation-error').forEach(function (error) { error.textContent = ''; }); form.querySelectorAll('.is-invalid').forEach(function (input) { input.classList.remove('is-invalid'); }); };
        var showErrors = function (errors) { Object.keys(errors || {}).forEach(function (name) { var error = document.getElementById(name + 'Err'), input = form.querySelector('[name="' + name + '"]'); if (error) error.textContent = errors[name]; if (input) input.classList.add('is-invalid'); }); };
        var reset = function () { form.reset(); clearErrors(); field('appointment_id').value = ''; field('duration_minutes').value = '15'; field('appointment_type').value = 'in_person'; field('appointment_status').value = 'scheduled'; document.querySelector('#appointmentModal .modal-title').textContent = 'Add Appointment'; form.querySelector('.save').textContent = 'Save Appointment'; toggleCancellation(); };
        var populate = function (appointment) {
            field('appointment_id').value = appointment.id || ''; field('appointment_patient_id').value = appointment.patient_id || ''; field('appointment_doctor_id').value = appointment.doctor_id || ''; field('appointment_clinic_id').value = appointment.clinic_id || ''; field('appointment_date').value = formatDate(appointment.appointment_date); field('appointment_time').value = String(appointment.appointment_time || '').substring(0, 5); field('duration_minutes').value = appointment.duration_minutes || ''; field('appointment_type').value = appointment.appointment_type || 'in_person'; field('appointment_status').value = appointment.status || 'scheduled'; field('reason_for_visit').value = appointment.reason_for_visit || ''; field('appointment_notes').value = appointment.notes || ''; field('cancellation_reason').value = appointment.cancellation_reason || ''; toggleCancellation(); document.querySelector('#appointmentModal .modal-title').textContent = 'Edit Appointment'; form.querySelector('.save').textContent = 'Update Appointment'; show();
        };
        var loadEdit = function (id) { clearErrors(); fetch(endpoint('edit/' + encodeURIComponent(id)), { headers: { 'X-Requested-With': 'XMLHttpRequest' } }).then(function (response) { return response.json(); }).then(function (response) { if (!response.status) throw new Error(response.msg || 'Unable to load appointment.'); populate(response.data || {}); }).catch(function (error) { if (window.showError) window.showError(error.message); else window.alert(error.message); }); };
        document.getElementById('openAppointmentModal')?.addEventListener('click', function () { reset(); show(); });
        modal.querySelectorAll('[data-dismiss="modal"], .close').forEach(function (button) { button.addEventListener('click', hide); });
        document.querySelectorAll('.edit-appointment').forEach(function (button) { button.addEventListener('click', function () { loadEdit(button.dataset.id); }); });
        document.querySelectorAll('.ri-more-actions-toggle').forEach(function (button) { button.addEventListener('click', function () { var menu = button.closest('.dropdown')?.querySelector('.dropdown-menu'); if (!menu) return; document.querySelectorAll('.dropdown-menu.show').forEach(function (other) { if (other !== menu) other.classList.remove('show'); }); menu.classList.toggle('show'); }); });
        document.addEventListener('click', function (event) { if (!event.target.closest('.dropdown')) document.querySelectorAll('.dropdown-menu.show').forEach(function (menu) { menu.classList.remove('show'); }); if (event.target === modal) hide(); });
        document.addEventListener('keydown', function (event) { if (event.key === 'Escape' && modal.classList.contains('show')) hide(); });
        if (field('appointment_status')) field('appointment_status').addEventListener('change', toggleCancellation);
        form.addEventListener('submit', function (event) {
            event.preventDefault(); clearErrors(); var id = field('appointment_id').value, data = new URLSearchParams(new FormData(form));
            fetch(endpoint(id ? 'update/' + encodeURIComponent(id) : 'save'), { method: 'POST', body: data, headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(form), 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' } }).then(function (response) { return response.json(); }).then(function (response) { if (!response.status) { showErrors(response.errors || {}); throw new Error(response.msg || 'Unable to save appointment.'); } hide(); if (window.showSuccess) window.showSuccess(response.msg); else window.alert(response.msg); window.setTimeout(function () { window.location.reload(); }, 500); }).catch(function (error) { if (!form.querySelector('.is-invalid') && window.showError) window.showError(error.message); });
        });
        var editId = new URLSearchParams(window.location.search).get('edit'); if (/^\d+$/.test(editId || '')) loadEdit(editId);
    }

    function initWithJQuery($) {
        // Keep the established cancellation route contract: ROUTES.appointments.queueStatus + id; status: 'cancelled'.
        var form = '#appointmentForm';
        function showModal() {
            var modal = document.getElementById('appointmentModal');
            if ($.fn && $.fn.modal) { $('#appointmentModal').modal('show'); }
            modal.style.display = 'block'; modal.classList.add('show'); modal.removeAttribute('aria-hidden'); modal.setAttribute('aria-modal', 'true'); document.body.classList.add('modal-open');
            window.setTimeout(function () { if (!modal.classList.contains('show')) { modal.style.display = 'block'; modal.classList.add('show'); modal.removeAttribute('aria-hidden'); document.body.classList.add('modal-open'); } }, 0);
        }
        function hideModal() {
            var modal = document.getElementById('appointmentModal');
            if ($.fn && $.fn.modal) { $('#appointmentModal').modal('hide'); }
            modal.classList.remove('show'); modal.style.display = 'none'; modal.setAttribute('aria-hidden', 'true'); modal.removeAttribute('aria-modal'); document.body.classList.remove('modal-open');
            window.setTimeout(function () { if (!document.querySelector('.modal.show')) document.querySelectorAll('.modal-backdrop').forEach(function (backdrop) { backdrop.remove(); }); }, 80);
        }
        function toggleCancellationFields() { var cancelled = $('#appointment_status').val() === 'cancelled'; $('#cancellationFields').toggle(cancelled); if (!cancelled) $('#cancellation_reason').val(''); }
        function resetAppointmentForm() { resetForm(form); clearFormErrors(); $('#appointment_id').val(''); $('#duration_minutes').val(15); $('#appointment_type').val('in_person').selectpicker('refresh'); $('#appointment_status').val('scheduled').selectpicker('refresh'); $('#appointmentModal .modal-title').text('Add Appointment'); $(form + ' .save').text('Save Appointment'); toggleCancellationFields(); }
        $('#openAppointmentModal').on('click', function () { resetAppointmentForm(); showModal(); }); $('#appointment_status').on('change', toggleCancellationFields);
        $(form).on('submit', function (e) { e.preventDefault(); clearFormErrors(); var id = $('#appointment_id').val(); ajaxRequest({ url: id ? ROUTES.appointments.update + id : ROUTES.appointments.save, method: 'POST', data: $(this).serialize(), success: function (response) { if (response.status) { hideModal(); showSuccess(response.msg); reload_page(500); return; } showError(response.msg); bindFormError(form, response.errors); } }); });
        $(document).on('click.adminAppointmentEdit', '.edit-appointment', function () { var id = $(this).data('id'); clearFormErrors(); ajaxRequest({ url: ROUTES.appointments.edit + id, method: 'GET', success: function (response) { if (!response.status) { showError(response.msg); return; } resetAppointmentForm(); var appointment = response.data; $('#appointment_id').val(appointment.id); $('#appointment_patient_id').val(appointment.patient_id).selectpicker('refresh'); $('#appointment_doctor_id').val(appointment.doctor_id).selectpicker('refresh'); $('#appointment_clinic_id').val(appointment.clinic_id).selectpicker('refresh'); if (window.setSharedDateValue) setSharedDateValue('#appointment_date', appointment.appointment_date); else $('#appointment_date').val(appointment.appointment_date); $('#appointment_time').val((appointment.appointment_time || '').substring(0, 5)); $('#duration_minutes').val(appointment.duration_minutes); $('#appointment_type').val(appointment.appointment_type).selectpicker('refresh'); $('#appointment_status').val(appointment.status).selectpicker('refresh'); $('#reason_for_visit').val(appointment.reason_for_visit || ''); $('#appointment_notes').val(appointment.notes || ''); $('#cancellation_reason').val(appointment.cancellation_reason || ''); toggleCancellationFields(); $('#appointmentModal .modal-title').text('Edit Appointment'); $(form + ' .save').text('Update Appointment'); showModal(); } }); });
        $('.cancel-appointment').on('click', function () { var button=$(this), id=button.data('id'); if (!window.confirm('Cancel ' + (button.data('appointment-number') || 'this appointment') + '?')) return; ajaxRequest({url:ROUTES.appointments.queueStatus+id,method:'POST',data:{csrf_rtn:$('#appointmentForm input[name="csrf_rtn"]').val(),status:'cancelled'},success:function(r){if(r.status){showSuccess(r.msg);reload_page(500);}else showError(r.msg);}}); }); $('.delete-appointment').on('click', function () { var id=$(this).data('id'); if (!window.confirm('Delete this appointment?')) return; ajaxRequest({url:ROUTES.appointments.delete+id,method:'POST',data:{csrf_rtn:$('#appointmentForm input[name="csrf_rtn"]').val()},success:function(r){if(r.status){showSuccess(r.msg);reload_page(500);}else showError(r.msg);}}); });
        $('#appointmentModal').on('hidden.bs.modal.adminAppointment', function () { if (!document.querySelector('.modal.show')) $('.modal-backdrop').remove(); });
        $('#appointmentModal [data-dismiss="modal"], #appointmentModal .close').on('click.adminAppointment', function () { window.setTimeout(function () { if (!document.querySelector('.modal.show')) $('.modal-backdrop').remove(); }, 80); });
        var editId = new URLSearchParams(window.location.search).get('edit'); if (/^\d+$/.test(editId || '')) $('.edit-appointment[data-id="' + editId + '"]').first().trigger('click');
    }

    function start() { if (window.jQuery) initWithJQuery(window.jQuery); else initWithoutJQuery(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
}(window, document));
