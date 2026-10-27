<?= $this->extend('templates/theme1') ?>
<?= $this->section('main-content') ?>
<main class="password-auth-page" aria-labelledby="password-reset-title">
    <section class="password-auth-card">
        <header class="password-auth-card-header">
            <div class="password-auth-brand"><span class="password-auth-brand-mark" aria-hidden="true"><i class="fas fa-heartbeat"></i></span><img src="<?= base_url('assets/img/logo.png') ?>" alt="USHA DERMA"></div>
            <span class="password-auth-eyebrow">Secure recovery</span>
            <h1 class="password-auth-title" id="password-reset-title">Set a new password</h1>
            <p class="password-auth-subtitle">Choose a strong password. Reset links expire after one hour and can only be used once.</p>
        </header>
        <div class="password-auth-card-body">
            <?php if (!empty($invalidToken)): ?>
                <?php $tokenState = (string) ($tokenState ?? 'invalid'); $tokenMessage = ['expired' => 'This reset link has expired. Request a new link to continue.', 'used' => 'This reset link has already been used. Request a new link to continue.', 'rate_limited' => 'This reset link has reached its attempt limit. Request a new link to continue.', 'inactive' => 'This reset link is no longer available.', 'invalid' => 'This reset link is invalid. Request a new link to continue.'][$tokenState] ?? 'This reset link is invalid. Request a new link to continue.'; ?>
                <div class="password-auth-alert" role="alert"><i class="fas fa-link-slash" aria-hidden="true"></i><span><?= esc($tokenMessage) ?></span></div>
                <div class="password-auth-actions"><a class="password-auth-secondary" href="<?= esc(base_url('system/login')) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i>Back to sign in</a><a class="password-auth-button" href="<?= esc(base_url('account/password-recovery')) ?>"><i class="fas fa-paper-plane" aria-hidden="true"></i>Request a new link</a></div>
            <?php else: ?>
                <?php if ($message = session()->getFlashdata('error')): ?><div class="password-auth-alert" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i><span><?= esc((string) $message) ?></span></div><?php endif; ?>
                <?php $errors = session()->getFlashdata('errors') ?? []; ?>
                <?= form_open('account/password-reset/' . rawurlencode($token), ['autocomplete' => 'off', 'class' => 'password-auth-form']) ?><?= csrf_field() ?>
                    <div class="password-auth-field"><label for="reset-new-password">New password</label><div class="password-auth-input-wrap"><input id="reset-new-password" class="password-auth-input has-visibility-toggle" type="password" name="new_password" placeholder="Create a strong password" autocomplete="new-password" minlength="10" required><button class="password-auth-visibility" type="button" data-password-visibility="reset-new-password" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button></div><span class="password-auth-help">Use at least 10 characters with uppercase, lowercase, number, and special character.</span><span class="password-auth-error"><?= esc($errors['new_password'] ?? '') ?></span></div>
                    <div class="password-auth-field"><label for="reset-confirmation">Confirm new password</label><div class="password-auth-input-wrap"><input id="reset-confirmation" class="password-auth-input has-visibility-toggle" type="password" name="password_confirmation" placeholder="Re-enter your new password" autocomplete="new-password" required><button class="password-auth-visibility" type="button" data-password-visibility="reset-confirmation" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button></div><span class="password-auth-error"><?= esc($errors['password_confirmation'] ?? '') ?></span></div>
                    <div class="password-auth-actions"><a class="password-auth-secondary" href="<?= esc(base_url('system/login')) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i>Cancel</a><button class="password-auth-button" type="submit" data-loading-label="Resetting…"><i class="fas fa-shield-alt" aria-hidden="true"></i><span data-submit-label>Reset password</span></button></div>
                <?= form_close() ?>
            <?php endif; ?>
        </div>
    </section>
</main>
<?= $this->endSection() ?>
