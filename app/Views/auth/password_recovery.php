<?= $this->extend('templates/theme1') ?>
<?= $this->section('main-content') ?>
<main class="password-auth-page" aria-labelledby="password-recovery-title">
    <section class="password-auth-card">
        <header class="password-auth-card-header">
            <div class="password-auth-brand"><span class="password-auth-brand-mark" aria-hidden="true"><i class="fas fa-heartbeat"></i></span><img src="<?= base_url('assets/img/logo.png') ?>" alt="USHA DERMA"></div>
            <span class="password-auth-eyebrow">Account access</span>
            <h1 class="password-auth-title" id="password-recovery-title">Forgot password?</h1>
            <p class="password-auth-subtitle">Enter your email address or username. If it matches an account, we will send reset instructions.</p>
        </header>
        <div class="password-auth-card-body">
            <?php if ($message = session()->getFlashdata('success')): ?><div class="password-auth-alert password-auth-alert-success" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i><span><?= esc((string) $message) ?></span></div><?php endif; ?>
            <?php if ($message = session()->getFlashdata('error')): ?><div class="password-auth-alert" role="alert"><i class="fas fa-exclamation-circle" aria-hidden="true"></i><span><?= esc((string) $message) ?></span></div><?php endif; ?>
            <?= form_open('account/password-recovery', ['autocomplete' => 'off', 'class' => 'password-auth-form']) ?><?= csrf_field() ?>
                <div class="password-auth-field"><label for="recovery-identifier">Email or username</label><input id="recovery-identifier" class="password-auth-input" type="text" name="identifier" value="<?= esc(old('identifier')) ?>" placeholder="you@example.com or username" autocomplete="username" maxlength="150" required><span class="password-auth-help">For your security, the same message is shown whether or not an account matches.</span></div>
                <div class="password-auth-actions"><a class="password-auth-secondary" href="<?= esc(base_url('system/login')) ?>"><i class="fas fa-arrow-left" aria-hidden="true"></i>Back to sign in</a><button class="password-auth-button" type="submit" data-loading-label="Sending…"><i class="fas fa-paper-plane" aria-hidden="true"></i><span data-submit-label>Send reset link</span></button></div>
            <?= form_close() ?>
            <p class="password-auth-footer">Reset links expire after one hour and can only be used once.</p>
        </div>
    </section>
</main>
<?= $this->endSection() ?>
