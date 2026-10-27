<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#234e5c">
    <title>USHA DERMA::Admin Login</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="<?= base_url('assets/css/style.css?v=20261018-login-redesign1') ?>">
    <link rel="stylesheet" href="<?= base_url('assets/css/password-auth.css?v=20261025-password-ui1') ?>">
    <style>
        :root { --login-teal:#234e5c; --login-gold:#c3a56d; --login-dark:#16343e; --login-ink:#17313a; --login-muted:#62757b; --login-border:#d9e3e5; --login-page:#f2f6f7; --login-danger:#9b3f45; }
        html, body { min-height:100%; margin:0; }
        body.usha-derma-login { min-height:100vh; min-height:100dvh; overflow-x:hidden; overflow-y:auto; color:var(--login-ink); background:var(--login-page); font-family:'Segoe UI',Arial,sans-serif; }
        .login-shell { display:grid; min-height:100vh; min-height:100dvh; grid-template-columns:minmax(300px,.82fr) minmax(0,1.18fr); }
        .login-brand-panel { position:relative; display:flex; min-width:0; align-items:flex-end; overflow:hidden; padding:clamp(2rem,6vw,5.5rem); color:#fff; background:linear-gradient(145deg,var(--login-teal) 0%,var(--login-dark) 100%); }
        .login-brand-panel::before,.login-brand-panel::after { position:absolute; border:1px solid rgba(195,165,109,.28); border-radius:50%; content:''; pointer-events:none; }
        .login-brand-panel::before { width:30rem; height:30rem; right:-14rem; top:-10rem; }
        .login-brand-panel::after { width:18rem; height:18rem; right:8rem; bottom:-12rem; }
        .login-brand-content { position:relative; z-index:1; max-width:30rem; }
        .login-brand-logo { display:block; width:min(210px,75%); height:auto; margin-bottom:2.25rem; padding:.65rem .8rem; border-radius:12px; box-sizing:border-box; background:#fff; box-shadow:0 8px 20px rgba(7,28,35,.16); object-fit:contain; object-position:left center; }
        .login-brand-kicker { display:inline-flex; align-items:center; gap:.5rem; margin-bottom:1rem; color:#f4dfb5; font-size:.72rem; font-weight:800; letter-spacing:.16em; text-transform:uppercase; }
        .login-brand-kicker::before { width:2rem; height:2px; background:var(--login-gold); content:''; }
        .login-brand-title { margin:0 0 1rem; color:#fff; font-size:clamp(2rem,4vw,3.4rem); font-weight:750; letter-spacing:-.035em; line-height:1.06; }
        .login-brand-description { max-width:27rem; margin:0; color:rgba(255,255,255,.82); font-size:1rem; line-height:1.65; }
        .login-brand-rule { width:4rem; height:3px; margin-top:2rem; background:var(--login-gold); }
        .login-form-panel { display:flex; min-width:0; align-items:center; justify-content:center; padding:clamp(1.25rem,5vw,4rem); background:var(--login-page); }
        .login-card-brand-logo { display:none; }
        .login-card { width:100%; max-width:480px; padding:clamp(1.5rem,4vw,2.75rem); border:1px solid var(--login-border); border-radius:16px; background:#fff; box-shadow:0 18px 50px rgba(22,52,62,.12); }
        .login-card-header { margin-bottom:1.75rem; }
        .login-card-brand { display:flex; align-items:center; gap:.7rem; margin-bottom:1.5rem; }
        .login-card-brand-mark { display:inline-flex; width:2.5rem; height:2.5rem; align-items:center; justify-content:center; color:#fff; background:var(--login-teal); border-radius:10px; box-shadow:0 5px 12px rgba(35,78,92,.18); }
        .login-card-brand-name { color:var(--login-teal); font-size:.9rem; font-weight:850; letter-spacing:.08em; }
        .login-title { margin:0; color:var(--login-ink); font-size:clamp(1.6rem,3vw,2rem); font-weight:800; letter-spacing:-.025em; line-height:1.15; }
        .login-subtitle { margin:.6rem 0 0; color:var(--login-muted); font-size:.9rem; line-height:1.5; }
        .login-alert { margin-bottom:1.25rem; padding:.75rem .9rem; border:1px solid #e6bfc2; border-radius:8px; color:var(--login-danger); background:#fff5f5; font-size:.82rem; line-height:1.45; }
        .login-alert .close { color:var(--login-danger); opacity:.8; }
        .login-field { margin-bottom:1.1rem; }
        .login-field label { display:block; margin-bottom:.45rem; color:var(--login-ink); font-size:.78rem; font-weight:750; }
        .login-input-wrap { position:relative; }
        .login-input-icon { position:absolute; z-index:2; top:50%; left:1rem; color:var(--login-teal); transform:translateY(-50%); pointer-events:none; }
        .login-input { width:100%; min-height:48px; padding:.7rem 1rem .7rem 2.8rem; border:1px solid var(--login-border); border-radius:9px; color:var(--login-ink); background:#fbfdfd; font-size:.9rem; line-height:1.35; transition:border-color .15s ease,box-shadow .15s ease,background-color .15s ease; }
        .login-input.has-toggle { padding-right:3rem; }
        .login-input::placeholder { color:#8a9a9f; }
        .login-input:hover { border-color:#a9c1c6; background:#fff; }
        .login-input:focus { border-color:var(--login-gold); outline:0; background:#fff; box-shadow:0 0 0 3px rgba(195,165,109,.24); }
        .login-password-toggle { position:absolute; top:50%; right:.55rem; display:inline-flex; width:2rem; height:2rem; align-items:center; justify-content:center; padding:0; border:0; border-radius:6px; color:var(--login-teal); background:transparent; transform:translateY(-50%); }
        .login-password-toggle:hover,.login-password-toggle:focus-visible { color:var(--login-dark); background:rgba(195,165,109,.22); outline:0; }
        .login-validation-error { min-height:1.1rem; margin-top:.35rem; color:var(--login-danger); font-size:.72rem; line-height:1.35; }
        .login-form-options { display:flex; align-items:center; justify-content:flex-end; margin:.25rem 0 1.5rem; }
        .login-link { color:var(--login-teal); font-size:.78rem; font-weight:700; text-decoration:none; }
        .login-link:hover,.login-link:focus-visible { color:var(--login-dark); text-decoration:underline; }
        .login-submit { display:inline-flex; min-height:50px; align-items:center; justify-content:center; gap:.55rem; border:1px solid var(--login-teal); border-radius:9px; color:#fff; background:var(--login-teal); font-size:.9rem; font-weight:800; transition:background-color .15s ease,border-color .15s ease,transform .15s ease,box-shadow .15s ease; }
        body.usha-derma-login .login-submit { min-height:50px !important; padding-top:.75rem; padding-bottom:.75rem; }
        .login-submit:hover,.login-submit:focus-visible { color:#fff; background:var(--login-dark); border-color:var(--login-dark); box-shadow:0 6px 16px rgba(22,52,62,.2); outline:0; }
        .login-submit:active { transform:translateY(1px); }
        .login-submit:disabled { cursor:wait; opacity:.72; }
        .login-submit.is-loading .login-submit-icon { animation:login-spin .75s linear infinite; }
        .login-footer { margin-top:1.75rem; padding-top:1rem; border-top:1px solid #edf1f2; color:var(--login-muted); font-size:.7rem; line-height:1.5; text-align:center; }
        @keyframes login-spin { to { transform:rotate(360deg); } }
        @media (max-width:767.98px) {
            .login-shell { display:block; min-height:100vh; }
            .login-brand-panel { display:none; }
            .login-form-panel { min-height:100vh; min-height:100dvh; align-items:center; justify-content:flex-start; flex-direction:column; padding:.9rem .75rem calc(1rem + env(safe-area-inset-bottom)); }
            .login-card-brand { display:block; margin:0 0 .85rem; padding:0; text-align:center; }
            .login-card-brand-mark,.login-card-brand-name { display:none; }
            .login-card-brand-logo { display:block; width:auto; max-width:min(140px,42vw); height:auto; margin:0 auto; padding:0; object-fit:contain; object-position:center center; }
            .login-card { max-width:520px; padding:1rem 1rem 1.1rem; border-radius:12px; }
            .login-card-header { margin-bottom:1rem; }
            .login-subtitle,.login-footer { display:none; }
            .login-field { margin-bottom:.85rem; }
            .login-form-options { margin:.1rem 0 1rem; }
            .login-link { display:inline-flex; min-height:44px; align-items:center; padding:.45rem 0; }
            .login-password-toggle { width:2.75rem; height:2.75rem; }
        }
        @media (max-width:767.98px) and (max-height:520px) {
            .login-form-panel { padding-top:.75rem; }
            .login-card-brand { margin-bottom:.55rem; }
            .login-card-brand-logo { max-width:min(105px,36vw); }
            .login-card { padding-top:.8rem; padding-bottom:.9rem; }
            .login-card-header { margin-bottom:.75rem; }
            .login-card-brand-logo { max-width:min(96px,32vw); }
        }
        @media (max-width:767.98px) and (min-height:700px) {
            .login-form-panel { justify-content:center; }
        }
        @media (max-height:620px) and (min-width:768px) {
            .login-brand-panel,.login-form-panel { align-items:flex-start; padding-top:1.25rem; padding-bottom:1.25rem; }
            .login-card { padding-top:1.25rem; padding-bottom:1.25rem; }
            .login-card-header { margin-bottom:1rem; }
            .login-footer { margin-top:1rem; }
        }
        @media (prefers-reduced-motion:reduce) {
            .login-input,.login-submit,.login-password-toggle { transition:none; }
            .login-submit.is-loading .login-submit-icon { animation:none; }
        }
    </style>
    <link rel="stylesheet" href="<?= base_url('assets/css/password-auth.css?v=20261025-password-ui1') ?>">
</head>
<body class="usha-derma-login">
    <main class="login-shell" aria-labelledby="login-title">
        <section class="login-brand-panel" aria-label="USHA DERMA branding">
            <div class="login-brand-content">
                <img class="login-brand-logo" src="<?= base_url('assets/img/logo.png') ?>" alt="USHA DERMA">
                <span class="login-brand-kicker">Healthcare operations</span>
                <h1 class="login-brand-title">USHA DERMA</h1>
                <p class="login-brand-description">A focused workspace for managing care teams, patients, consultations, prescriptions, and clinic operations.</p>
                <div class="login-brand-rule" aria-hidden="true"></div>
            </div>
        </section>
        <section class="login-form-panel" aria-label="Staff sign in">
            <div class="login-card">
                <header class="login-card-header">
                    <div class="login-card-brand" aria-label="USHA DERMA staff access">
                        <span class="login-card-brand-mark" aria-hidden="true"><i class="fas fa-heartbeat"></i></span>
                        <span class="login-card-brand-name">USHA DERMA</span>
                        <img class="login-card-brand-logo" src="<?= base_url('assets/img/logo.png') ?>" alt="USHA DERMA">
                    </div>
                    <h2 class="login-title" id="login-title">USHA DERMA</h2>
                    <p class="login-subtitle">Sign in to continue to your authorized workspace.</p>
                </header>
                <?php if (session()->getFlashdata('error')) : ?>
                    <div class="login-alert" role="alert"><i class="fas fa-exclamation-circle mr-1" aria-hidden="true"></i><?= esc((string) session()->getFlashdata('error')) ?></div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('success')) : ?>
                    <div class="login-alert login-alert-success" role="status"><i class="fas fa-check-circle mr-1" aria-hidden="true"></i><?= esc((string) session()->getFlashdata('success')) ?></div>
                <?php endif; ?>
                <?= form_open('system/user-auth-authenticate', 'autocomplete="off" id="staff-login-form"') ?>
                    <?php if (!empty($redirectTarget)): ?><input type="hidden" name="redirect" value="<?= esc($redirectTarget) ?>"><?php endif; ?>
                    <div class="login-field">
                        <label for="user-id">Email address</label>
                        <div class="login-input-wrap"><i class="fas fa-envelope login-input-icon" aria-hidden="true"></i><input id="user-id" class="login-input" type="email" name="user_id" value="<?= esc(old('user_id')) ?>" placeholder="you@example.com" autocomplete="username" required></div>
                        <div class="login-validation-error" id="user_idErr"><?= display_form_error(validation_errors(), 'user_id') ?></div>
                    </div>
                    <div class="login-field">
                        <label for="user-password">Password</label>
                        <div class="login-input-wrap"><i class="fas fa-lock login-input-icon" aria-hidden="true"></i><input id="user-password" class="login-input has-toggle" type="password" name="user_password" placeholder="Enter your password" autocomplete="current-password" required><button class="login-password-toggle" type="button" id="toggle-password" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye" aria-hidden="true"></i></button></div>
                        <div class="login-validation-error" id="user_passwordErr"><?= display_form_error(validation_errors(), 'user_password') ?></div>
                    </div>
                    <div class="login-form-options"><a class="login-link" href="<?= base_url('account/password-recovery') ?>">Forgot password?</a></div>
                    <button class="login-submit btn btn-block" type="submit" id="login-submit"><i class="fas fa-arrow-right login-submit-icon" aria-hidden="true"></i><span class="login-submit-label">Sign in</span></button>
                <?= form_close() ?>
                <footer class="login-footer">Secure staff access · <?= date('Y') ?> · www.ushaderma.com</footer>
            </div>
        </section>
    </main>
    <script>
        (function () {
            var password = document.getElementById('user-password');
            var toggle = document.getElementById('toggle-password');
            var form = document.getElementById('staff-login-form');
            var submit = document.getElementById('login-submit');
            if (password && toggle) {
                toggle.addEventListener('click', function () {
                    var visible = password.type === 'text';
                    password.type = visible ? 'password' : 'text';
                    toggle.setAttribute('aria-label', visible ? 'Show password' : 'Hide password');
                    toggle.setAttribute('aria-pressed', visible ? 'false' : 'true');
                    toggle.querySelector('i').className = visible ? 'fas fa-eye' : 'fas fa-eye-slash';
                });
            }
            if (form && submit) {
                form.addEventListener('submit', function () {
                    if (!form.checkValidity()) return;
                    submit.disabled = true;
                    submit.classList.add('is-loading');
                    submit.querySelector('.login-submit-label').textContent = 'Signing in…';
                });
            }
        }());
    </script>
</body>
</html>
