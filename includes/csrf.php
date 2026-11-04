<?php
function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') . '">';
}
function csrf_valid(): bool
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($sent) && $sent !== '' && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $sent);
}
/** For HTML form handlers: abort on bad token. */
function csrf_require(): void
{
    if (!csrf_valid()) {
        http_response_code(419);
        exit('Security token invalid or expired. Go back, refresh the page and try again.');
    }
}
