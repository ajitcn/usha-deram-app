<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */
$routes->get('/', 'UserAuth::index');
/**
 *-------------------------------------------------------------------
 * Common Routes
 *-------------------------------------------------------------------.
 */
$routes->get('system/login', 'UserAuth::index');
$routes->get('login', 'UserAuth::index');
$routes->get('system/logout', 'UserAuth::logout');
$routes->post('system/user-auth-authenticate', 'UserAuth::authenticate');
$routes->get('account/password/change', 'UserAuth::changePassword', ['filter' => 'auth']);
$routes->post('account/password/change', 'UserAuth::changePassword', ['filter' => 'auth']);
$routes->get('account/password-recovery', 'UserAuth::passwordRecovery');
$routes->post('account/password-recovery', 'UserAuth::passwordRecovery');
$routes->get('account/password-reset/(:segment)', 'UserAuth::passwordReset/$1');
$routes->post('account/password-reset/(:segment)', 'UserAuth::passwordReset/$1');
$routes->get('permission-denied', 'PermissionDenied::index');
//Location routes
$routes->get('location/states/(:num)', 'Location::getStates/$1');
$routes->get('location/cities/(:num)', 'Location::getCities/$1');
$routes->get('location/localities/(:num)', 'Location::getLocalities/$1');
$routes->post('api/v1/patients', '\\Doctor\\Controllers\\Api\\Patients::create');

// Import module routes
foreach (glob(APPPATH . 'Modules/*/Config/Routes.php') as $moduleRoute) {
    require $moduleRoute;
}
