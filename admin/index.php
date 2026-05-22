<?php
require __DIR__ . '/../src/bootstrap.php';
use Mori\Auth;
use function Mori\redirect;
use function Mori\asset;

redirect(asset(Auth::check() ? 'admin/dashboard.php' : 'admin/login.php'));
