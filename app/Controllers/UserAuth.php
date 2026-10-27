<?php

namespace App\Controllers;

use App\Controllers\BaseController;

/**
 *------------------------------------------------------------------
 * For Admin, Doctor and Pharmacy Assistant only
 *---------------------------------------------------------------------.
 */
class UserAuth extends BaseController
{
    /**
     * Login Page.
     */
    public function index()
    {
        if (session('patient_authenticated')) {
            return redirect()->to(base_url('patient'));
        }

        if (session('isLoggedIn') && is_array(session('user'))) {
            return redirect()->to(base_url(service('auth')->redirectUrl()));
        }

        if (trim(uri_string(), '/') === '') {
            return redirect()->to(base_url('system/login'));
        }

        return view('auth/user_login', [
            'redirectTarget' => $this->safeReturnTarget($this->request->getGet('redirect')),
        ]);
    }

    /**
     * Authenticate User.
     */
    public function authenticate()
    {
        $rules = ['user_id' => ['label' => 'User ID', 'rules' => 'required'], 'user_password' => ['label' => 'Password', 'rules' => 'required']];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()->with('error', 'Username and Password are required.');
        }
        $user_id = trim($this->request->getPost('user_id'));
        $password = $this->request->getPost('user_password');
        $auth = service('auth');

        if (!$auth->attempt($this->request->getPost('user_id'), $this->request->getPost('user_password'))) {
            return redirect()->back()->withInput()->with('error', $auth->getError());
        }

        $returnTarget = $this->safeReturnTarget($this->request->getPost('redirect'));

        if ($returnTarget !== null && $this->canUseReturnTarget($returnTarget)) {
            return redirect()->to(base_url($returnTarget));
        }

        return redirect()->to(base_url($auth->redirectUrl()));
    }

    /**
     * Logout.
     */
    public function logout()
    {
        session()->destroy();

        return redirect()->to(base_url('system/login'));
    }

    public function changePassword()
    {
        $userId = (int) ((session('user') ?? [])['id'] ?? 0);
        if ($userId < 1) return redirect()->to(base_url('system/login'));
        if ($this->request->getMethod(true) === 'GET') return view('auth/password_change');
        $current = (string) $this->request->getPost('current_password');
        $new = (string) $this->request->getPost('new_password');
        $confirmation = (string) $this->request->getPost('password_confirmation');
        $auth = service('auth');
        $errors = $auth->passwordValidationErrors($new, $confirmation);
        if ($current === '') $errors['current_password'] = 'Current password is required.';
        if ($errors !== []) return redirect()->back()->withInput()->with('errors', $errors);
        if (!$auth->changePassword($userId, $current, $new)) return redirect()->back()->withInput()->with('errors', $auth->getErrors())->with('error', $auth->getError());
        return redirect()->to(base_url('account/password/change'))->with('success', 'Your password was changed successfully.');
    }

    public function passwordRecovery()
    {
        if ($this->request->getMethod(true) === 'GET') return view('auth/password_recovery');
        $identifier = trim((string) $this->request->getPost('identifier'));
        if ($identifier === '' || strlen($identifier) > 150) return redirect()->back()->withInput()->with('error', 'Enter your email address or username.');
        service('auth')->forgotPassword($identifier);
        return redirect()->to(base_url('account/password-recovery'))->with('success', 'If an account matches those details, a password reset link will be sent shortly.');
    }

    public function passwordReset(string $token)
    {
        if (trim($token) === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) {
            service('audit')->record('auth.password_reset_failed', ['reason' => 'invalid_token_format']);
            return view('auth/password_reset', ['token' => '', 'invalidToken' => true]);
        }
        $tokenState = service('auth')->passwordResetState($token);
        if ($this->request->getMethod(true) === 'GET') {
            if ($tokenState !== 'valid') service('audit')->record($tokenState === 'rate_limited' ? 'auth.password_reset_rate_limited' : 'auth.password_reset_failed', ['reason' => 'token_' . $tokenState]);
            return view('auth/password_reset', ['token' => $token, 'invalidToken' => $tokenState !== 'valid', 'tokenState' => $tokenState]);
        }
        $new = (string) $this->request->getPost('new_password');
        $confirmation = (string) $this->request->getPost('password_confirmation');
        $auth = service('auth');
        if (!$auth->resetPassword($token, $new, $confirmation)) return redirect()->back()->withInput()->with('errors', $auth->getErrors())->with('error', $auth->getError());
        return redirect()->to(base_url('system/login'))->with('success', 'Your password was reset successfully. Please sign in.');
    }

    private function safeReturnTarget($target): ?string
    {
        $target = trim((string) $target);

        if ($target === '' || str_starts_with($target, '/') || str_contains($target, '\\') || preg_match('/^[a-z][a-z0-9+.-]*:/i', $target)) {
            return null;
        }

        $path = trim((string) parse_url('/' . ltrim($target, '/'), PHP_URL_PATH), '/');

        if ($path === '' || in_array($path, ['login', 'system/login', 'system/logout'], true)) {
            return null;
        }

        return $path . (($query = parse_url('/' . ltrim($target, '/'), PHP_URL_QUERY)) ? '?' . $query : '');
    }

    private function canUseReturnTarget(string $target): bool
    {
        $path = trim((string) parse_url('/' . ltrim($target, '/'), PHP_URL_PATH), '/');
        $roles = session('roles') ?? [];
        $permissions = session('permissions') ?? [];

        if (str_starts_with($path, 'admin/')) {
            return in_array('Admin', $roles, true) || in_array('Super Admin', $roles, true);
        }

        if (str_starts_with($path, 'doctor')) {
            return in_array('Doctor', $roles, true) && in_array('doctor.view', $permissions, true);
        }

        if (str_starts_with($path, 'pharmacy')) {
            return in_array('Pharmacy Assistant', $roles, true) && in_array('pharmacy.dashboard.view', $permissions, true);
        }

        return false;
    }
}
