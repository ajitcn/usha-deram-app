<?php

namespace App\Services;

use Admin\Models\User;
use Admin\Models\UserRole;

class AuthService
{
    private const MAX_RESET_ATTEMPTS = 5;

    protected User $user;

    protected UserRole $userRole;

    protected string $error = '';

    /** @var array<string, string> */
    protected array $errors = [];

    protected ?array $userData = null;

    protected array $roles = [];

    protected array $permissions = [];

    public function __construct()
    {
        $this->user = new User();
        $this->userRole = new UserRole();
    }

    /**
     * --------------------------------------------------------
     * Attempt Login
     * --------------------------------------------------------.
     */
    public function attempt(string $userId, string $password): bool
    {
        $this->userData = $this->user->where('email', $userId)->first();

        if (!$this->userData) {
            $this->error = 'Invalid username or password.';

            return false;
        }
        // The authenticated identity and all scope context must come from the
        // server-side record and its trusted relationships.
        $this->userData['id'] = (int) $this->userData['id'];

        if (!$this->verifyPassword($password, $this->userData['password'])) {
            return false;
        }

        if ($this->userData['status'] != 1) {
            $this->error = 'Your account is inactive.';

            return false;
        }
        $this->loadRoles();
        $this->loadPermissions();
        session()->regenerate(true);
        $this->createSession();

        return true;
    }

    /**
     * --------------------------------------------------------
     * Verify Password
     * --------------------------------------------------------.
     */
    protected function verifyPassword(string $password, string $hash): bool
    {
        if (!password_verify($password, $hash)) {
            $this->error = 'Invalid username or password.';

            return false;
        }

        return true;
    }

    /**
     * --------------------------------------------------------
     * Load User Roles
     * --------------------------------------------------------.
     */
    protected function loadRoles(): void
    {
        $this->roles = (new UserRole())->select('roles.role_name')->join('roles', 'roles.id=user_roles.role_id')->where('user_id', $this->userData['id'])->findColumn('role_name');
    }

    /**
     * --------------------------------------------------------
     * Load User Permissions
     * --------------------------------------------------------.
     */
    protected function loadPermissions(): void
    {
        $this->permissions = (new UserRole())->select('permissions.permission_key')->join('role_permissions', 'role_permissions.role_id = user_roles.role_id')->join('permissions', 'permissions.id = role_permissions.permission_id')->where('user_roles.user_id', $this->userData['id'])->findColumn('permission_key') ?? [];
        // Remove duplicate permissions if user has multiple roles
        $this->permissions = array_values(array_unique($this->permissions));
    }

    /**
     * Re-resolve the current user's roles and active permission keys from the
     * canonical database mapping. This prevents long-lived sessions from
     * retaining stale authorization after a role-permission change.
     *
     * @return array<int, string>
     */
    public function refreshAuthorizationSession(): array
    {
        $user = session('user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;

        if ($userId <= 0) {
            return [];
        }

        try {
            $db = \Config\Database::connect('default');
            $permissions = array_column($db->table('user_roles')
                ->select('permissions.permission_key')
                ->join('role_permissions', 'role_permissions.role_id = user_roles.role_id')
                ->join('permissions', 'permissions.id = role_permissions.permission_id')
                ->where('user_roles.user_id', $userId)
                ->where('permissions.status', 1)
                ->get()->getResultArray(), 'permission_key');

            $roleRows = $db->table('user_roles')
                ->select('roles.role_name')
                ->join('roles', 'roles.id = user_roles.role_id')
                ->where('user_roles.user_id', $userId)
                ->get()->getResultArray();
            $roles = array_map(static function (array $role): string {
                return in_array((string) ($role['role_name'] ?? ''), ['FarmAssist', 'Farmassist'], true)
                    ? 'Pharmacy Assistant'
                    : (string) ($role['role_name'] ?? '');
            }, $roleRows);
            $roles = array_values(array_unique(array_filter($roles)));
            $permissions = array_values(array_unique(array_filter(array_map('strval', $permissions))));
            session()->set(['roles' => $roles, 'permissions' => $permissions]);
            $this->roles = $roles;
            $this->permissions = $permissions;
            return $permissions;
        } catch (\Throwable $exception) {
            log_message('error', 'Authorization session refresh failed safely: ' . $exception->getMessage());
            return session('permissions') ?? [];
        }
    }

    /**
     * --------------------------------------------------------
     * Create Session
     * --------------------------------------------------------.
     */
    protected function createSession(): void
    {
        $clinicId = $this->resolveClinicId();
        session()->set(['isLoggedIn' => true, 'password_changed_at' => $this->userData['password_changed_at'] ?? null, 'user' => ['id' => $this->userData['id'], 'username' => $this->userData['username'], 'email' => $this->userData['email'], 'clinic_id' => $clinicId], 'clinic_id' => $clinicId, 'roles' => $this->roles, 'permissions' => $this->permissions]);
    }

    /**
     * Resolve the user's clinic from trusted server-side relationships.
     * Global administrators receive scope 0; controllers treat that as global.
     */
    protected function resolveClinicId(): int
    {
        $userId = (int) ($this->userData['id'] ?? 0);
        $doctorId = (int) ($this->userData['doctor_id'] ?? 0);
        $db = db_connect();

        if ($doctorId > 0) {
            $clinicId = $db->table('doctors')->select('clinic_id')->where('id', $doctorId)->get(1)->getRowArray();

            if ($clinicId !== null) {
                return (int) $clinicId['clinic_id'];
            }
        }

        if ($userId > 0) {
            $clinicId = $db->table('patients')->select('clinic_id')->where('user_id', $userId)->where('clinic_id IS NOT NULL', null, false)->orderBy('id', 'ASC')->get(1)->getRowArray();

            if ($clinicId !== null) {
                return (int) $clinicId['clinic_id'];
            }
        }

        return 0;
    }

    /**
     * --------------------------------------------------------
     * Redirect URL
     * --------------------------------------------------------.
     */
    public function redirectUrl(): string
    {
        $roles = $this->roles ?: (session('roles') ?? []);
        $permissions = $this->permissions ?: (session('permissions') ?? []);

        if (in_array('Admin', $roles, true) || in_array('Super Admin', $roles, true)) {
            return '/admin';
        }

        if (in_array('Doctor', $roles, true) && in_array('doctor.view', $permissions, true)) {
            return '/doctor';
        }

        if (in_array('Patient', $roles, true)) {
            return '/patient';
        }

        if (in_array('Pharmacy Assistant', $roles, true) && in_array('pharmacy.dashboard.view', $permissions, true)) {
            return '/pharmacy';
        }

        return 'system/login';
    }

    /**
     * --------------------------------------------------------
     * Logout
     * --------------------------------------------------------.
     */
    public function logout(): void
    {
        session()->destroy();
    }

    /**
     * --------------------------------------------------------
     * Change Password
     * --------------------------------------------------------.
     */
    public function changePassword(int $userId, string $currentPassword, string $newPassword): bool
    {
        $this->errors = [];
        $this->error = '';
        $user = $this->user->withDeleted()->where('id', $userId)->first();

        if ($user === null || (int) ($user['status'] ?? 0) !== 1 || !empty($user['deleted_at'])) {
            $this->error = 'The current password is incorrect.';
            return false;
        }

        if (!password_verify($currentPassword, (string) ($user['password'] ?? ''))) {
            $this->error = 'The current password is incorrect.';
            $this->errors['current_password'] = $this->error;
            service('audit')->record('auth.password_change_failed', ['user_id' => $userId, 'reason' => 'current_password_invalid']);
            return false;
        }

        $this->errors = $this->passwordValidationErrors($newPassword, '');

        if ($this->errors !== []) {
            $this->error = (string) reset($this->errors);
            return false;
        }

        if (password_verify($newPassword, (string) $user['password'])) {
            $this->errors['new_password'] = 'The new password must be different from the current password.';
            $this->error = $this->errors['new_password'];
            service('audit')->record('auth.password_change_failed', ['user_id' => $userId, 'reason' => 'password_reused']);
            return false;
        }

        $changedAt = date('Y-m-d H:i:s');
        $updated = $this->user->update($userId, ['password' => password_hash($newPassword, PASSWORD_DEFAULT), 'password_changed_at' => $changedAt]);

        if (!$updated) {
            $this->error = 'The password could not be changed safely.';
            return false;
        }

        if (db_connect()->tableExists('password_reset_tokens')) {
            db_connect()->table('password_reset_tokens')->where('user_id', $userId)->where('used_at', null)->update(['used_at' => $changedAt, 'updated_at' => $changedAt]);
        }
        $this->userData = $user;
        $this->userData['password_changed_at'] = $changedAt;
        session()->regenerate(true);
        $this->loadRoles();
        $this->loadPermissions();
        $this->createSession();
        service('audit')->record('auth.password_changed', ['user_id' => $userId]);
        return true;
    }

    /**
     * --------------------------------------------------------
     * Forgot Password
     * --------------------------------------------------------.
     */
    public function forgotPassword(string $email): bool
    {
        $this->errors = [];
        $this->error = '';
        $request = service('request');
        $ip = substr((string) ($request->getIPAddress() ?? ''), 0, 45);
        $identifier = trim($email);
        $ipRateKey = $this->passwordResetCacheKey('password_reset_rate_', $ip);
        $identifierRateKey = $this->passwordResetCacheKey('password_reset_account_', strtolower($identifier) . '|' . $ip);

        if (!service('throttler')->check($ipRateKey, 5, MINUTE, 1) || !service('throttler')->check($identifierRateKey, 3, MINUTE, 1)) {
            service('audit')->record('auth.password_reset_rate_limited', ['request_ip' => $ip]);
            $this->error = 'Please wait before requesting another reset email.';
            return false;
        }

        $user = $this->user->where('status', 1)->groupStart()->where('email', $identifier)->orWhere('username', $identifier)->groupEnd()->first();
        service('audit')->record('auth.password_reset_requested', ['account_found' => $user !== null, 'request_ip' => $ip]);

        if ($user === null || trim((string) ($user['email'] ?? '')) === '') {
            return true;
        }

        $db = db_connect();
        $now = date('Y-m-d H:i:s');
        $db->table('password_reset_tokens')->where('user_id', (int) $user['id'])->where('used_at', null)->update(['used_at' => $now, 'updated_at' => $now]);
        $rawToken = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600);
        $db->table('password_reset_tokens')->insert(['user_id' => (int) $user['id'], 'token_hash' => hash('sha256', $rawToken), 'expires_at' => $expires, 'request_ip' => $ip ?: null, 'attempts' => 0, 'created_at' => $now, 'updated_at' => $now]);
        $resetUrl = base_url('account/password-reset/' . $rawToken);
        $sent = false;
        try {
            $emailService = service('email');
            $emailService->setTo((string) $user['email']);
            $emailService->setSubject('Reset your USHA DERMA password');
            $emailService->setMessage("A password reset was requested for your USHA DERMA account. Use this link within one hour:\n\n" . $resetUrl . "\n\nIf you did not request this, you can ignore this email.");
            $sent = $emailService->send();
        } catch (\Throwable $exception) {
            log_message('error', 'Password reset notification failed safely: ' . $exception->getMessage());
        }
        service('audit')->record('auth.password_reset_notification', ['user_id' => (int) $user['id'], 'sent' => $sent === true]);
        return true;
    }

    /**
     * --------------------------------------------------------
     * Get Error
     * --------------------------------------------------------.
     */
    public function getError(): string
    {
        return $this->error;
    }

    /** @return array<string, string> */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /** @return array<string, string> */
    public function passwordValidationErrors(string $password, string $confirmation): array
    {
        $errors = [];
        if (strlen($password) < 10) $errors['new_password'] = 'Password must be at least 10 characters.';
        elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[a-z]/', $password) || !preg_match('/[0-9]/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) $errors['new_password'] = 'Password must include uppercase, lowercase, number, and special character.';
        if (!hash_equals($password, $confirmation)) $errors['password_confirmation'] = 'Password confirmation does not match.';
        return $errors;
    }

    public function resetPassword(string $rawToken, string $newPassword, string $confirmation): bool
    {
        $this->errors = [];
        $this->error = '';
        $tokenHash = hash('sha256', trim($rawToken));
        if (trim($rawToken) === '' || !service('throttler')->check($this->passwordResetCacheKey('password_reset_attempt_', $tokenHash), 10, MINUTE, 1)) {
            $this->error = 'This reset link is invalid or has expired.';
            service('audit')->record('auth.password_reset_failed', ['reason' => 'rate_limited_or_missing_token']);
            return false;
        }
        $db = db_connect();
        $row = $db->table('password_reset_tokens')->where('token_hash', $tokenHash)->get()->getRowArray();
        if ($row === null) {
            $this->error = 'This reset link is invalid or has expired.';
            service('audit')->record('auth.password_reset_failed', ['reason' => 'invalid_token']);
            return false;
        }
        $now = date('Y-m-d H:i:s');
        if (!empty($row['used_at'])) {
            $this->error = 'This reset link is invalid or has expired.';
            service('audit')->record('auth.password_reset_failed', ['reason' => 'token_used', 'user_id' => (int) $row['user_id']]);
            return false;
        }
        if (strtotime((string) ($row['expires_at'] ?? '')) <= time()) {
            $this->error = 'This reset link is invalid or has expired.';
            service('audit')->record('auth.password_reset_failed', ['reason' => 'token_expired', 'user_id' => (int) $row['user_id']]);
            return false;
        }
        if ((int) ($row['attempts'] ?? 0) >= self::MAX_RESET_ATTEMPTS) {
            $this->error = 'This reset link has reached its maximum number of attempts. Request a new link.';
            service('audit')->record('auth.password_reset_rate_limited', ['reason' => 'token_attempt_limit', 'user_id' => (int) $row['user_id']]);
            return false;
        }
        $attemptUpdated = $db->table('password_reset_tokens')->where('id', (int) $row['id'])->where('used_at', null)->where('attempts <', self::MAX_RESET_ATTEMPTS)->set('attempts', 'attempts + 1', false)->set('updated_at', $now)->update();
        if (!$attemptUpdated || $db->affectedRows() !== 1) {
            $this->error = 'This reset link has reached its maximum number of attempts. Request a new link.';
            service('audit')->record('auth.password_reset_rate_limited', ['reason' => 'token_attempt_limit', 'user_id' => (int) $row['user_id']]);
            return false;
        }
        $this->errors = $this->passwordValidationErrors($newPassword, $confirmation);
        if ($this->errors !== []) { $this->error = (string) reset($this->errors); service('audit')->record('auth.password_reset_failed', ['reason' => 'password_validation', 'user_id' => (int) $row['user_id']]); return false; }
        $user = $this->user->where('id', (int) $row['user_id'])->where('status', 1)->first();
        if ($user === null) { $this->error = 'This reset link is invalid or has expired.'; service('audit')->record('auth.password_reset_failed', ['reason' => 'user_inactive', 'user_id' => (int) $row['user_id']]); return false; }
        $db->transBegin();
        try {
            $updated = $db->table('password_reset_tokens')->where('id', (int) $row['id'])->where('used_at', null)->update(['used_at' => $now, 'updated_at' => $now]);
            if (!$updated || $db->affectedRows() !== 1) throw new \RuntimeException('Reset token already used.');
            if (!$this->user->update((int) $user['id'], ['password' => password_hash($newPassword, PASSWORD_DEFAULT), 'password_changed_at' => $now])) throw new \RuntimeException('Password update failed.');
            $db->table('password_reset_tokens')->where('user_id', (int) $user['id'])->where('used_at', null)->update(['used_at' => $now, 'updated_at' => $now]);
            if (!$db->transStatus()) throw new \RuntimeException('Password reset transaction failed.');
            $db->transCommit();
        } catch (\Throwable $exception) {
            $db->transRollback();
            $this->error = 'The password could not be reset safely.';
            service('audit')->record('auth.password_reset_failed', ['user_id' => (int) $user['id'], 'reason' => 'transaction_failed']);
            return false;
        }
        service('audit')->record('auth.password_reset_succeeded', ['user_id' => (int) $user['id']]);
        return true;
    }

    private function passwordResetCacheKey(string $prefix, string $value): string
    {
        return (string) preg_replace('/[^A-Za-z0-9_-]/', '_', $prefix) . hash('sha256', $value);
    }

    public function passwordResetState(string $rawToken): string
    {
        $token = trim($rawToken);
        if ($token === '' || !preg_match('/^[a-f0-9]{64}$/i', $token)) return 'invalid';
        $row = db_connect()->table('password_reset_tokens')->where('token_hash', hash('sha256', $token))->get()->getRowArray();
        if ($row === null) return 'invalid';
        if (!empty($row['used_at'])) return 'used';
        if (strtotime((string) ($row['expires_at'] ?? '')) <= time()) return 'expired';
        if ((int) ($row['attempts'] ?? 0) >= self::MAX_RESET_ATTEMPTS) return 'rate_limited';
        if ($this->user->where('id', (int) $row['user_id'])->where('status', 1)->first() === null) return 'inactive';
        return 'valid';
    }

    /**
     * --------------------------------------------------------
     * Get Logged User
     * --------------------------------------------------------.
     */
    public function user(): ?array
    {
        if ($this->userData === null) {
            return null;
        }

        return ['id' => (int) ($this->userData['id'] ?? 0), 'username' => $this->userData['username'] ?? null, 'email' => $this->userData['email'] ?? null, 'doctor_id' => isset($this->userData['doctor_id']) ? (int) $this->userData['doctor_id'] : null, 'clinic_id' => $this->resolveClinicId(), 'status' => (int) ($this->userData['status'] ?? 0)];
    }

    /**
     * --------------------------------------------------------
     * User Roles
     * --------------------------------------------------------.
     */
    public function roles(): array
    {
        return $this->roles;
    }

    /**
     * --------------------------------------------------------
     * User Permissions
     * --------------------------------------------------------.
     */
    public function permissions(): array
    {
        return $this->permissions;
    }

    /**
     * --------------------------------------------------------
     * Has Role
     * --------------------------------------------------------.
     */
    public function hasRole(string $role): bool
    {
        return in_array($role, $this->roles);
    }

    /**
     * --------------------------------------------------------
     * Has Permission
     * --------------------------------------------------------.
     */
    public function hasPermission(string $permission): bool
    {
        return in_array($permission, $this->permissions);
    }
}
