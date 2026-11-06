<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

if (!function_exists('readline')) {
    fwrite(STDERR, "This setup command requires PHP readline support.\n");
    exit(1);
}

$fullName = trim(readline('Admin name: '));
if ($fullName === '' || mb_strlen($fullName) > 120) {
    fwrite(STDERR, "Enter the admin's name (up to 120 characters).\n");
    exit(1);
}

$email = strtolower(trim(readline('Admin email: ')));
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
    fwrite(STDERR, "Enter a valid email address (maximum 190 characters).\n");
    exit(1);
}

$password = readline('Admin password (minimum 12 characters): ');
if (strlen($password) < 12 || strlen($password) > 4096) {
    fwrite(STDERR, "Password must be between 12 and 4096 characters.\n");
    exit(1);
}

$confirmation = readline('Confirm password: ');
if (!hash_equals($password, $confirmation)) {
    fwrite(STDERR, "Passwords do not match.\n");
    exit(1);
}

$escape = static fn (string $value): string => str_replace(["\\", "'"], ["\\\\", "''"], $value);
$hash = password_hash($password, PASSWORD_DEFAULT);
$sql = "INSERT INTO admin_users (full_name, email, password_hash, role, is_active) SELECT '" . $escape($fullName) . "', '" . $escape($email) . "', '" . $escape($hash) . "', 'super_admin', 1 "
    . 'WHERE NOT EXISTS (SELECT 1 FROM admin_users LIMIT 1);';

fwrite(STDOUT, "Run this SQL once in the hosting database's phpMyAdmin SQL tab:\n\n");
fwrite(STDOUT, $sql . "\n");
