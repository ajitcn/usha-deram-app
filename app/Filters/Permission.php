<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Permission implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Permission not passed in route
        if (empty($arguments) || empty($arguments[0])) {
            return;
        }
        $requiredPermission = $arguments[0];
        // Refresh authorization from current database mappings so sessions
        // created before a permission migration do not retain stale grants.
        $this->refreshSessionPermissions();
        $permissions = session('permissions') ?? [];

        if (!in_array($requiredPermission, $permissions, true)) {
            // AJAX Request
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'You do not have permission to perform this action.']);
            }

            // Normal Request
            return redirect()->to(base_url('permission-denied'));
        }
    }

    /**
     * Refresh the current user's role-derived authorization state.
     */
    private function refreshSessionPermissions(): void
    {
        $user = session('user');
        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : 0;

        if ($userId <= 0) {
            return;
        }

        service('auth')->refreshAuthorizationSession();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}
