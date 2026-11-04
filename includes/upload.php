<?php
/**
 * Secure photo upload. Returns the new random file name, '' when no file was sent,
 * or null with $err filled when validation fails.
 */
function handle_photo_upload(array $file, ?string &$err): ?string
{
    $err = null;
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return '';
    if ($file['error'] !== UPLOAD_ERR_OK) { $err = 'Upload failed (error code ' . (int)$file['error'] . ').'; return null; }
    if ($file['size'] <= 0 || $file['size'] > MAX_UPLOAD_BYTES) { $err = 'Photo must be smaller than 2 MB.'; return null; }
    if (!is_uploaded_file($file['tmp_name'])) { $err = 'Invalid upload.'; return null; }

    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    if (!isset($allowed[$mime])) { $err = 'Only JPG, PNG or WEBP images are allowed.'; return null; }

    $info = @getimagesize($file['tmp_name']);           // content check: must really be an image
    if ($info === false || $info[0] < 50 || $info[1] < 50 || $info[0] > 8000 || $info[1] > 8000 || $info['mime'] !== $mime) {
        $err = 'File content is not a valid image.'; return null;
    }
    $raw = file_get_contents($file['tmp_name']);        // defence in depth: no embedded PHP
    if (stripos($raw, '<?php') !== false || stripos($raw, '<?=') !== false) { $err = 'Suspicious file content.'; return null; }

    if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0755, true);
    $name = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];   // never use the client file name
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $name)) { $err = 'Could not save the photo.'; return null; }
    @chmod(UPLOAD_DIR . $name, 0644);
    return $name;
}
function delete_photo(?string $name): void
{
    if ($name && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name) && is_file(UPLOAD_DIR . $name)) @unlink(UPLOAD_DIR . $name);
}
function photo_url(?string $name): string
{
    return ($name && preg_match('/^[a-f0-9]{32}\.(jpg|png|webp)$/', $name)) ? url('uploads/' . $name) : '';
}
