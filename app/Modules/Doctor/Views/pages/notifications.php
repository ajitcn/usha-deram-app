<?= $this->extend(DOCTOR_VIEW . 'layout') ?>

<?= $this->section('main-content') ?>
<?php
$iconFor = static function (array $notification): string {
    return match ((string) ($notification['related_entity_type'] ?? '')) {
        'prescription_clarification' => 'bi-chat-square-exclamation',
        'prescription' => 'bi-prescription2',
        'appointment' => 'bi-calendar-check',
        'consultation' => 'bi-clipboard2-pulse',
        default => str_contains(strtolower((string) ($notification['title'] ?? '')), 'clarification') ? 'bi-chat-square-exclamation' : 'bi-bell',
    };
};
$relatedUrl = static function (array $notification): ?string {
    $id = (int) ($notification['related_entity_id'] ?? 0);

    if ($id <= 0) {
        return null;
    }

    return match ((string) ($notification['related_entity_type'] ?? '')) {
        'prescription_clarification' => base_url('doctor/prescription-clarifications?clarification_id=' . $id),
        'prescription' => base_url('doctor/prescriptions?prescription_id=' . $id),
        'appointment' => base_url('doctor/appointments/' . $id),
        default => null,
    };
};
?>
<main class="dashboard-container doctor-notifications-page" aria-labelledby="doctor-notifications-title">
    <div class="page-heading doctor-notifications-heading">
        <div class="doctor-notifications-heading-copy">
            <div class="page-eyebrow">Doctor panel / updates</div>
            <h1 id="doctor-notifications-title" class="h3 mb-1">Notifications</h1>
            <p class="text-muted mb-0">Clinic-scoped tasks, pharmacy responses, and workflow updates.</p>
        </div>
        <div class="doctor-notifications-heading-actions">
            <span class="doctor-notifications-count" aria-label="<?= (int) $unread ?> unread notifications"><i class="bi bi-bell" aria-hidden="true"></i><strong><?= (int) $unread ?></strong><span>unread</span></span>
            <button class="btn btn-primary btn-sm" type="button" id="doctor-notifications-read-all" <?= (int) $unread === 0 ? 'disabled' : '' ?>><i class="bi bi-check2-all mr-1" aria-hidden="true"></i>Mark all as read</button>
        </div>
    </div>

    <section class="card doctor-notification-inbox" aria-labelledby="doctor-notification-inbox-title">
        <div class="card-header doctor-notification-toolbar">
            <div><h2 class="h5 mb-1" id="doctor-notification-inbox-title">Notification inbox</h2><small class="text-muted"><?= (int) ($pagination['total'] ?? 0) ?> total notification<?= ((int) ($pagination['total'] ?? 0) === 1) ? '' : 's' ?></small></div>
            <div class="doctor-notification-toolbar-actions"><span class="doctor-notification-filter-summary"><?php if (($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?>Filters applied<?php else: ?>All notifications<?php endif; ?></span><button class="btn btn-outline-primary btn-sm" type="button" data-toggle="modal" data-target="#doctor-notification-filters"><i class="bi bi-funnel mr-1" aria-hidden="true"></i>Filters</button></div>
        </div>

        <div class="doctor-notification-list" role="list">
            <?php if ($notifications === []): ?>
                <div class="doctor-notification-empty" role="status"><span class="doctor-notification-empty-icon"><i class="bi bi-bell-slash" aria-hidden="true"></i></span><h3 class="h5 mb-2">No notifications found</h3><p class="text-muted mb-3"><?php if (($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?>Try clearing or changing your filters.<?php else: ?>New clinic tasks and updates will appear here.<?php endif; ?></p><?php if (($filters['search'] ?? '') !== '' || ($filters['status'] ?? '') !== ''): ?><a class="btn btn-sm btn-outline-primary" href="<?= current_url() ?>">Clear filters</a><?php endif; ?></div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <?php $isUnread = (string) ($notification['status'] ?? '') === 'unread'; $url = $relatedUrl($notification); ?>
                    <article class="doctor-notification-item <?= $isUnread ? 'is-unread' : 'is-read' ?>" role="listitem">
                        <span class="doctor-notification-icon <?= $isUnread ? 'is-unread' : 'is-read' ?>" aria-hidden="true"><i class="bi <?= esc($iconFor($notification)) ?>"></i></span>
                        <div class="doctor-notification-copy">
                            <div class="doctor-notification-title-row"><h3 class="doctor-notification-title"><?= esc($notification['title'] ?? 'Notification') ?></h3><span class="doctor-notification-status <?= $isUnread ? 'is-unread' : 'is-read' ?>"><?= $isUnread ? 'Unread' : 'Read' ?></span></div>
                            <p class="doctor-notification-message"><?= nl2br(esc($notification['message'] ?? '')) ?></p>
                            <div class="doctor-notification-meta"><span><i class="bi bi-clock" aria-hidden="true"></i><?= esc($notification['created_at'] ?? '') ?></span><?php if (($notification['related_entity_type'] ?? '') !== ''): ?><span><i class="bi bi-link-45deg" aria-hidden="true"></i><?= esc(ucwords(str_replace('_', ' ', (string) $notification['related_entity_type']))) ?></span><?php endif; ?></div>
                        </div>
                        <div class="doctor-notification-actions"><?php if ($url !== null): ?><a class="btn btn-sm btn-outline-primary" href="<?= esc($url) ?>">Open <span class="sr-only">related record</span></a><?php endif; ?><?php if ($isUnread): ?><button class="btn btn-sm btn-light js-doctor-notification-read" type="button" data-id="<?= (int) ($notification['id'] ?? 0) ?>"><i class="bi bi-check2 mr-1" aria-hidden="true"></i>Mark read</button><?php else: ?><span class="doctor-notification-read-label"><i class="bi bi-check2-circle mr-1" aria-hidden="true"></i>Read</span><?php endif; ?></div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
        <?= view(DOCTOR_VIEW . 'parts/pagination', ['pagination' => $pagination, 'filters' => $filters]) ?>
    </section>
</main>

<div class="modal fade ri-scroll-modal" id="doctor-notification-filters" tabindex="-1" role="dialog" aria-modal="true" aria-labelledby="doctor-notification-filters-title" aria-hidden="true"><div class="modal-dialog modal-dialog-scrollable" role="document"><div class="modal-content"><form method="get" action="<?= current_url() ?>"><div class="modal-header"><h2 class="modal-title h5" id="doctor-notification-filters-title">Filter notifications</h2><button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button></div><div class="modal-body"><div class="form-group"><label for="doctor-notification-search">Search</label><input id="doctor-notification-search" class="form-control" name="search" value="<?= esc($filters['search'] ?? '') ?>" placeholder="Title or message"></div><div class="form-group"><label for="doctor-notification-status">Status</label><select id="doctor-notification-status" class="form-control btSelect" name="status"><option value="">All statuses</option><option value="unread" <?= ($filters['status'] ?? '') === 'unread' ? 'selected' : '' ?>>Unread</option><option value="read" <?= ($filters['status'] ?? '') === 'read' ? 'selected' : '' ?>>Read</option></select></div></div><div class="modal-footer"><a class="btn btn-light" href="<?= current_url() ?>">Clear</a><button class="btn btn-primary" type="submit">Apply filters</button></div></form></div></div></div>
<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    function csrfToken() {
        const match = document.cookie.match(/(?:^|;\s*)csrf_rtn_cookie=([^;]+)/);
        return match ? decodeURIComponent(match[1]) : '';
    }
    function notifyError(message) {
        if (typeof showError === 'function') { showError(message); } else { window.alert(message); }
    }
    function notifySuccess(message) {
        if (typeof showSuccess === 'function') { showSuccess(message); }
    }
    function postNotification(url, button) {
        const original = button.innerHTML;
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.innerHTML = '<span class="spinner-border spinner-border-sm mr-1" role="status" aria-hidden="true"></span>Updating';
        const finish = function () { button.disabled = false; button.removeAttribute('aria-busy'); button.innerHTML = original; };
        if (typeof window.ajaxRequest === 'function' && typeof window.jQuery === 'function') {
            window.ajaxRequest({url: url, method: 'POST', data: {csrf_rtn: csrfToken()}, success: function (response) { if (response.status) { notifySuccess(response.msg || 'Notification updated.'); window.location.reload(); } else { notifyError(response.msg || 'Notification could not be updated.'); } }, error: function () { notifyError('Notification could not be updated safely.'); }, complete: finish});
            return;
        }
        window.fetch(url, {method: 'POST', credentials: 'same-origin', headers: {'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': csrfToken(), 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'}, body: new URLSearchParams({csrf_rtn: csrfToken()})}).then(function (response) { return response.json(); }).then(function (response) { if (!response.status) throw new Error(response.msg || 'Notification could not be updated.'); notifySuccess(response.msg || 'Notification updated.'); window.location.reload(); }).catch(function (error) { notifyError(error.message || 'Notification could not be updated safely.'); finish(); });
    }
    document.addEventListener('click', function (event) {
        const readButton = event.target.closest('.js-doctor-notification-read');
        if (readButton) { postNotification('<?= base_url('doctor/notifications') ?>/' + encodeURIComponent(readButton.dataset.id) + '/read', readButton); return; }
        const allButton = event.target.closest('#doctor-notifications-read-all');
        if (allButton) { postNotification('<?= base_url('doctor/notifications/read-all') ?>', allButton); return; }
        const filterButton = event.target.closest('[data-target="#doctor-notification-filters"]');
        if (filterButton && typeof window.jQuery !== 'function') { const modal = document.getElementById('doctor-notification-filters'); modal.style.display = 'block'; modal.classList.add('show'); modal.setAttribute('aria-hidden', 'false'); }
        const closeButton = event.target.closest('#doctor-notification-filters [data-dismiss="modal"]');
        if (closeButton && typeof window.jQuery !== 'function') { const modal = document.getElementById('doctor-notification-filters'); modal.style.display = 'none'; modal.classList.remove('show'); modal.setAttribute('aria-hidden', 'true'); }
    });
})();
</script>
<?= $this->endSection() ?>
