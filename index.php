<?php
/**
 * Project entry point.
 * Not logged in  -> public/login.php
 * Logged in      -> the dashboard matching the admin's role
 */

declare(strict_types=1);
require __DIR__ . '/api/auth_check.php';

$admin = current_admin();
$target = $admin ? dashboard_for_role($admin['role']) : 'login.php';

header('Location: public/' . $target);
exit;
