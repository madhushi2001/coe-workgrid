<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once dirname(__DIR__) . '/app/database.php';

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

try {
    $pdo = database();
    $pdo->beginTransaction();
    $existingCount = (int) $pdo->query('SELECT COUNT(*) FROM admin_users')->fetchColumn();
    if ($existingCount > 0) {
        $pdo->rollBack();
        fwrite(STDERR, "An admin account already exists. Account creation is disabled by this setup command.\n");
        exit(1);
    }

    $statement = $pdo->prepare(
        'INSERT INTO admin_users (full_name, email, password_hash, role, is_active)
         VALUES (:full_name, :email, :password_hash, \'super_admin\', 1)'
    );
    $statement->execute([
        'full_name' => $fullName,
        'email' => $email,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    $pdo->commit();
    fwrite(STDOUT, "Admin account created. Visit http://127.0.0.1:8000/admin/login.php to sign in.\n");
} catch (Throwable $exception) {
    if (isset($pdo) && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, "Admin account was not created: " . $exception->getMessage() . "\n");
    exit(1);
}
