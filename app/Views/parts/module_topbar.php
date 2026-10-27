<?php
$moduleLabel = (string) ($moduleLabel ?? 'Application panel');
$logoutUrl = (string) ($logoutUrl ?? base_url('system/logout'));
$notificationsUrl = (string) ($notificationsUrl ?? '');
$profileUrl = (string) ($profileUrl ?? '');
$showMessages = !empty($showMessages);
?>
<div class="sticky-top-area usha-module-topbar">
    <header class="top-header usha-module-topbar-header" role="banner">
        <div class="usha-topbar-leading">
            <button class="btn btn-sm btn-light d-lg-none" id="sidebarToggle" type="button" aria-label="Open navigation" aria-controls="sidebar" aria-expanded="false"><i class="bi bi-list" aria-hidden="true"></i></button>
            <div class="usha-topbar-brand"><span class="topbar-kicker">USHA DERMA</span><span class="font-weight-bold d-block"><?= esc($moduleLabel) ?></span></div>
        </div>
        <div class="usha-topbar-actions">
            <?php if ($showMessages): ?><a class="btn-tabler-icon" href="#" title="Messages" aria-label="Messages"><i class="bi bi-chat-left-text" aria-hidden="true"></i><span class="badge-dot"></span></a><?php endif; ?>
            <?php if ($notificationsUrl !== ''): ?><a class="btn-tabler-icon" href="<?= esc($notificationsUrl, 'attr') ?>" title="Notifications" aria-label="Notifications"><i class="bi bi-bell" aria-hidden="true"></i></a><?php endif; ?>
            <?php if ($profileUrl !== ''): ?><a class="btn-tabler-icon" href="<?= esc($profileUrl, 'attr') ?>" title="Profile" aria-label="Profile"><i class="bi bi-person-circle" aria-hidden="true"></i></a><?php endif; ?>
            <div class="vr mx-1 opacity-25" aria-hidden="true"></div>
            <div class="dropdown usha-topbar-account">
                <button class="usha-topbar-account-toggle dropdown-toggle" type="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false"><span class="avatar-pill" aria-hidden="true">A</span><span class="d-none d-sm-inline">Account</span></button>
                <div class="dropdown-menu dropdown-menu-right shadow-sm" role="menu">
                    <a class="dropdown-item" role="menuitem" href="<?= base_url('account/password/change') ?>"><i class="bi bi-shield-lock mr-2" aria-hidden="true"></i>Change password</a>
                    <?php if ($profileUrl !== ''): ?><a class="dropdown-item" role="menuitem" href="<?= esc($profileUrl, 'attr') ?>"><i class="bi bi-person-gear mr-2" aria-hidden="true"></i>Profile</a><?php endif; ?>
                    <div class="dropdown-divider"></div>
                    <a class="dropdown-item text-danger" role="menuitem" href="<?= esc($logoutUrl, 'attr') ?>"><i class="bi bi-box-arrow-right mr-2" aria-hidden="true"></i>Log out</a>
                </div>
            </div>
        </div>
    </header>
</div>
