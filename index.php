<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/auth.php';
redirect(Auth::check() ? Auth::homeFor(Auth::role()) : 'login');
