<?= $this->extend('templates/theme1') ?>
<?= $this->section('main-content') ?>
<main class="password-auth-page" aria-labelledby="password-change-title">
    <section class="password-auth-card">
        <header class="password-auth-card-header">
            <div class="password-auth-brand"><span class="password-auth-brand-mark" aria-hidden="true"><i class="fas fa-heartbeat"></i></span><img src="<?= base_url('assets/img/logo.png') ?>" alt="USHA DERMA"></div>
            <span class="password-auth-eyebrow">Account security</span>
            <h1 class="password-auth-title" id="password-change-title">Change password</h1>
            <p class="password-auth-subtitle">Update your password regularly to keep your USHA DERMA account secure.</p>
        </header>
        <div class="password-auth-card-body">
            <?php if ($message = session()->getFlashdata('error')): ?><div class="password-auth-alert" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i><span><?= esc((string) $message) ?></span></div><?php endif; ?>
            <?php if ($message = session()->getFlashdata('success')): ?><div class="password-auth-alert password-auth-alert-success" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i><span><?= esc((string) $message) ?></span></div><?php endif; ?>
            <?php $errors = session()->getFlashdata('errors') ?? []; ?>
            <?= form_open('account/password/change', ['autocomplete' => 'off', 'class' => 'password-auth-form']) ?><?= csrf_field() ?>
                <div class="password-auth-field"><label for="current-password">Current password</label><div class="password-auth-input-wrap"><input id="current-password" class="password-auth-input has-visibility-toggle" type="password" name="current_password" placeholder="Enter your current password" autocomplete="current-password" required><button class="password-auth-visibility" type="button" data-password-visibility="current-password" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button></div><span class="password-auth-error"><?= esc($errors['current_password'] ?? '') ?></span></div>
                <div class="password-auth-field"><label for="new-password">New password</label><div class="password-auth-input-wrap"><input id="new-password" class="password-auth-input has-visibility-toggle" type="password" name="new_password" placeholder="Create a strong password" autocomplete="new-password" minlength="10" required><button class="password-auth-visibility" type="button" data-password-visibility="new-password" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button></div><span class="password-auth-help">At least 10 characters with uppercase, lowercase, number, and special character.</span><span class="password-auth-error"><?= esc($errors['new_password'] ?? '') ?></span></div>
                <div class="password-auth-field"><label for="password-confirmation">Confirm new password</label><div class="password-auth-input-wrap"><input id="password-confirmation" class="password-auth-input has-visibility-toggle" type="password" name="password_confirmation" placeholder="Re-enter your new password" autocomplete="new-password" required><button class="password-auth-visibility" type="button" data-password-visibility="password-confirmation" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button></div><span class="password-auth-error"><?= esc($errors['password_confirmation'] ?? '') ?></span></div>
                <div class="password-auth-actions"><a class="password-auth-secondary" href="<?= esc(base_url(service('auth')->redirectUrl())) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i>Cancel</a><button class="password-auth-button" type="submit" data-loading-label="Saving…"><i class="fas fa-shield-alt" aria-hidden="true"></i><span data-submit-label>Change password</span></button></div>
            <?= form_close() ?>
            <p class="password-auth-footer">Changing your password signs out other active sessions.</p>
        </div>
    </section>
</main>
<?= $this->endSection() ?>
