<?php

use CodeIgniter\Test\CIUnitTestCase;

final class PasswordWorkflowTest extends CIUnitTestCase
{
    public function testPasswordWorkflowHasServerSideStrengthAndConfirmationChecks(): void
    {
        $auth = file_get_contents(ROOTPATH . 'app/Services/AuthService.php');
        self::assertStringContainsString('passwordValidationErrors', $auth);
        self::assertStringContainsString('strlen($password) < 10', $auth);
        self::assertStringContainsString('preg_match(\'/[A-Z]/\', $password)', $auth);
        self::assertStringContainsString('password_reused', $auth);
        self::assertStringContainsString('hash_equals($password, $confirmation)', $auth);
    }

    public function testResetTokensAreHashedSingleUseExpiringAndRateLimited(): void
    {
        $auth = file_get_contents(ROOTPATH . 'app/Services/AuthService.php');
        $migration = file_get_contents(ROOTPATH . 'app/Database/Migrations/2026-10-25-140000_CreatePasswordResetTokens.php');
        foreach (['random_bytes(32)', "hash('sha256', \$rawToken)", "where('used_at', null)", "attempts + 1", 'MAX_RESET_ATTEMPTS', 'passwordResetState', 'password_reset_attempt_', 'passwordResetCacheKey', 'password_reset_succeeded'] as $text) self::assertStringContainsString($text, $auth);
        self::assertStringNotContainsString('password-reset-token:', $auth);
        self::assertStringNotContainsString('password-reset-request:', $auth);
        foreach (['token_hash', 'expires_at', 'used_at', 'addUniqueKey', 'request_ip'] as $text) self::assertStringContainsString($text, $migration);
    }

    public function testPasswordRoutesAreCsrfProtectedAndScoped(): void
    {
        $routes = file_get_contents(ROOTPATH . 'app/Config/Routes.php');
        $controller = file_get_contents(ROOTPATH . 'app/Controllers/UserAuth.php');
        self::assertStringContainsString("'account/password/change', 'UserAuth::changePassword', ['filter' => 'auth']", $routes);
        self::assertStringContainsString("'account/password-recovery', 'UserAuth::passwordRecovery'", $routes);
        self::assertStringContainsString("'account/password-reset/(:segment)', 'UserAuth::passwordReset/$1'", $routes);
        self::assertStringContainsString("form_open('account/password-reset/", file_get_contents(ROOTPATH . 'app/Views/auth/password_reset.php'));
        self::assertStringContainsString('$auth->changePassword', $controller);
    }

    public function testRetiredIntegrationIsNotReferencedByActiveApplicationCode(): void
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(ROOTPATH . 'app', FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if (!$file->isFile() || str_contains($file->getPathname(), DIRECTORY_SEPARATOR . 'Database' . DIRECTORY_SEPARATOR . 'Migrations' . DIRECTORY_SEPARATOR)) continue;
            $files[] = $file->getPathname();
        }
        $retiredIntegration = 'shop' . 'ify';
        foreach ($files as $file) self::assertStringNotContainsStringIgnoringCase($retiredIntegration, (string) file_get_contents($file), $file);
    }
}
