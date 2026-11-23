<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireCapability('users.manage');

requirePostWithCsrf();

$currentAdmin = currentUser();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isEdit = $id !== null;

$backTarget = $isEdit ? 'form.php?id=' . $id : 'form.php';

$input = [
    'name' => trim((string) ($_POST['name'] ?? '')),
    'email' => trim(strtolower((string) ($_POST['email'] ?? ''))),
    'role' => (string) ($_POST['role'] ?? 'editor'),
    'status' => (string) ($_POST['status'] ?? 'active'),
];
$password = (string) ($_POST['password'] ?? '');

$errors = [];

if ($input['name'] === '' || mb_strlen($input['name']) > 120) {
    $errors[] = 'Name is required (max 120 characters).';
}
if (!filter_var($input['email'], FILTER_VALIDATE_EMAIL) || mb_strlen($input['email']) > 190) {
    $errors[] = 'A valid email address is required.';
}
if (!in_array($input['role'], ['super_admin', 'administrator', 'editor'], true)) {
    $errors[] = 'Please choose a valid role.';
}
if (!in_array($input['status'], ['active', 'disabled'], true)) {
    $errors[] = 'Please choose a valid status.';
}
// Password rules live in passwordPolicyError() (includes/auth.php) so this
// page and database/create-admin.php enforce exactly the same thing.
if (!$isEdit && $password === '') {
    $errors[] = 'Password is required.';
} elseif ($password !== '') {
    $policyError = passwordPolicyError($password, $input['email'], $input['name']);
    if ($policyError !== null) {
        $errors[] = $policyError;
    }
}

if (!$errors) {
    $emailCheck = getDb()->prepare(
        'SELECT id FROM admin_users WHERE email = :email' . ($isEdit ? ' AND id != :id' : '') . ' LIMIT 1'
    );
    $emailParams = ['email' => $input['email']];
    if ($isEdit) {
        $emailParams['id'] = $id;
    }
    $emailCheck->execute($emailParams);
    if ($emailCheck->fetch()) {
        $errors[] = 'That email is already in use by another account.';
    }
}

// Self-lockout guard: refuse to disable/demote yourself away from being
// the last active super_admin, same reasoning as the delete guard below.
if (!$errors && $isEdit && (int) $id === (int) $currentAdmin['id']
    && ($input['role'] !== 'super_admin' || $input['status'] !== 'active')) {
    $activeSuperAdmins = getDb()->query(
        "SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND status = 'active'"
    )->fetchColumn();
    if ((int) $activeSuperAdmins <= 1) {
        $errors[] = 'You are the last active Super Admin — you cannot change your own role or status away from that.';
    }
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

if ($isEdit) {
    if ($password !== '') {
        $stmt = getDb()->prepare(
            'UPDATE admin_users SET name = :name, email = :email, role = :role, status = :status,
             password_hash = :password_hash WHERE id = :id'
        );
        $stmt->execute(array_merge($input, [
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'id' => $id,
        ]));
    } else {
        $stmt = getDb()->prepare(
            'UPDATE admin_users SET name = :name, email = :email, role = :role, status = :status WHERE id = :id'
        );
        $stmt->execute(array_merge($input, ['id' => $id]));
    }
    logActivity($currentAdmin['id'], 'update', 'users', $input['email']);
    flashSet('success', 'User updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO admin_users (name, email, role, status, password_hash)
         VALUES (:name, :email, :role, :status, :password_hash)'
    );
    $stmt->execute(array_merge($input, ['password_hash' => password_hash($password, PASSWORD_DEFAULT)]));
    logActivity($currentAdmin['id'], 'create', 'users', $input['email']);
    flashSet('success', 'User created.');
}

redirect('index.php');
