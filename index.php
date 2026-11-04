<?php
require_once __DIR__ . '/includes/auth.php';
$u = current_user();
if ($u && $u['role'] === 'admin') redirect('admin/dashboard.php');
if ($u && $u['role'] === 'customer') redirect('customer/dashboard.php');
redirect('customer/login.php');
