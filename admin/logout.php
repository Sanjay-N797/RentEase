<?php
require_once __DIR__ . '/../includes/auth.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    logout_user();
    session_start();
    flash_set('success', 'You have been logged out.');
}
redirect('admin/login.php');
