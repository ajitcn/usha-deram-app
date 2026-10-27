<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class Auth implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session('patient_authenticated')) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(403)->setJSON(['status' => false, 'msg' => 'Patient accounts cannot access staff workflows.']);
            }

            return redirect()->to(base_url('permission-denied'));
        }

        if (!session('isLoggedIn')) {
            if ($request->isAJAX()) {
                return service('response')->setStatusCode(401)->setJSON(['status' => false, 'msg' => 'Your session has expired. Please login again.']);
            }

            $target = trim(uri_string(), '/');
            $query = $request->getUri()->getQuery();
            $target = $target . ($query !== '' ? '?' . $query : '');
            $loginUrl = base_url('system/login');

            if ($target !== '' && !in_array($target, ['login', 'system/login'], true)) {
                $loginUrl .= '?redirect=' . rawurlencode($target);
            }

            return redirect()->to($loginUrl)->with('error', 'Please login first.');
        }

        $userId = (int) ((session('user') ?? [])['id'] ?? 0);
        if ($userId > 0 && db_connect()->tableExists('users') && db_connect()->fieldExists('password_changed_at', 'users')) {
            $row = db_connect()->table('users')->select('password_changed_at')->where('id', $userId)->where('status', 1)->get()->getRowArray();
            $sessionPasswordChangedAt = session('password_changed_at');
            if ($row === null || (string) ($row['password_changed_at'] ?? '') !== (string) ($sessionPasswordChangedAt ?? '')) {
                session()->destroy();
                if ($request->isAJAX()) return service('response')->setStatusCode(401)->setJSON(['status' => false, 'msg' => 'Your session has expired. Please login again.']);
                return redirect()->to(base_url('system/login'))->with('error', 'Your session has expired. Please login again.');
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}
