<?= $this->extend('templates/theme1') ?>

<?= $this->section('side-menu') ?>
<?php
$path = trim(uri_string(), '/');
$permissions = session('permissions') ?? [];
$can = static fn (string $permission): bool => in_array($permission, $permissions, true);
$active = static function (string $prefix) use ($path): bool {
    $prefix = trim($prefix, '/');

    return $path === $prefix || str_starts_with($path, $prefix . '/');
};
?>
<nav id="sidebar" aria-label="Doctor panel navigation">
    <div class="sidebar-brand">
        <span class="brand-img">
            <img src="<?= base_url('assets/img/logo.png') ?>" alt="USHA DERMA">
        </span>
        <span class="system-tag">Doctor</span>
        <button type="button" class="sidebar-close d-lg-none" data-sidebar-close aria-label="Close navigation"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
    </div>

    <ul class="sidebar-nav" id="sidebarMenu" aria-label="Doctor navigation">
            <li class="sidebar-section-title" aria-hidden="true">Workspace</li>

            <?php if ($can('doctor.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $path === 'doctor' ? 'active' : '' ?>"
                        href="<?= base_url('doctor') ?>"
                    >
                        <i class="bi bi-speedometer2 menu-icon" aria-hidden="true"></i>
                        <span>Dashboard</span>
                    </a>
                </li>
            <?php endif; ?>
            <?php if ($can('consultation.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/clinical') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/clinical') ?>"
                    >
                        <i class="bi bi-clipboard2-pulse menu-icon" aria-hidden="true"></i>
                        <span>Clinical workspace</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/follow-ups') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/follow-ups') ?>"
                    >
                        <i class="bi bi-calendar2-check menu-icon" aria-hidden="true"></i>
                        <span>Follow-ups</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('patient.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/patients') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/patients') ?>"
                    >
                        <i class="bi bi-person-vcard menu-icon" aria-hidden="true"></i>
                        <span>My patients</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('appointment.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/appointments') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/appointments') ?>"
                    >
                        <i class="bi bi-calendar3 menu-icon" aria-hidden="true"></i>
                        <span>Appointments</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/video') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/appointments') ?>"
                    >
                        <i class="bi bi-camera-video menu-icon" aria-hidden="true"></i>
                        <span>Video consultations</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('doctor.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/availability') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/availability') ?>"
                    >
                        <i class="bi bi-calendar-week menu-icon" aria-hidden="true"></i>
                        <span>Availability &amp; leave</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('prescription.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/prescriptions') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/prescriptions') ?>"
                    >
                        <i class="bi bi-prescription2 menu-icon" aria-hidden="true"></i>
                        <span>Prescriptions</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $active('doctor/prescription-clarifications') ? 'active' : '' ?>" href="<?= base_url('doctor/prescription-clarifications') ?>">
                        <i class="bi bi-chat-left-text menu-icon" aria-hidden="true"></i>
                        <span>Prescription clarifications</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('billing.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/billing') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/billing') ?>"
                    >
                        <i class="bi bi-receipt menu-icon" aria-hidden="true"></i>
                        <span>Billing</span>
                    </a>
                </li>
            <?php endif; ?>

            <?php if ($can('doctor.view')): ?>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/notifications') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/notifications') ?>"
                    >
                        <i class="bi bi-bell menu-icon" aria-hidden="true"></i>
                        <span>Notifications</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a
                        class="nav-link <?= $active('doctor/profile') ? 'active' : '' ?>"
                        href="<?= base_url('doctor/profile') ?>"
                    >
                        <i class="bi bi-person-gear menu-icon" aria-hidden="true"></i>
                        <span>Profile &amp; settings</span>
                    </a>
                </li>
            <?php endif; ?>
    </ul>
</nav>
<?= $this->endSection() ?>

<?= $this->section('top-menu') ?>
<?= view('parts/module_topbar', ['moduleLabel' => 'Doctor panel', 'logoutUrl' => base_url('system/logout'), 'notificationsUrl' => base_url('doctor/notifications'), 'profileUrl' => $can('doctor.view') ? base_url('doctor/profile') : '']) ?>
<?= $this->endSection() ?>
