<?php
require __DIR__ . '/../src/bootstrap.php';
use Mori\Auth;
use function Mori\redirect;
use function Mori\asset;

Auth::logout();
redirect(asset('admin/login.php'));
