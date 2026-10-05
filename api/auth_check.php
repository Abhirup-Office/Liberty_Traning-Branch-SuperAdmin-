<?php
/**
 * Reusable page guard — require this at the very top of any protected page,
 * before any HTML is echoed.
 *
 * Usage:
 *   require __DIR__ . '/../api/auth_check.php';
 *   $admin = require_login(['super_admin']);   // or ['branch_admin'], or both
 *
 * This is a thin, readable entry point; the actual session logic
 * (current_admin, require_login, scoped_branch_id, ...) lives in
 * auth_guard.php so every page and every API endpoint shares one
 * implementation instead of duplicating the session_start()/role-check
 * snippet in each file.
 */

declare(strict_types=1);

require_once __DIR__ . '/auth_guard.php';
