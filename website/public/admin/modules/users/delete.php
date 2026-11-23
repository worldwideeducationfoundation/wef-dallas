<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireCapability('users.manage');

requirePostWithCsrf();

$currentAdmin = currentUser();
$id = isset($_POST['id']) ? (int) $_POST['id'] : null;

if ($id) {
    // Guard 1: never allow deleting your own currently-logged-in account.
    if ((int) $id === (int) $currentAdmin['id']) {
        flashSet('error', 'You cannot delete your own account while logged in as it.');
        redirect('index.php');
    }

    $stmt = getDb()->prepare('SELECT name, email, role, status FROM admin_users WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $id]);
    $targetUser = $stmt->fetch();

    if (!$targetUser) {
        flashSet('error', 'That user could not be found.');
        redirect('index.php');
    }

    // Guard 2: never allow deleting the last remaining active super_admin
    // — would leave nobody able to manage users at all.
    if ($targetUser['role'] === 'super_admin' && $targetUser['status'] === 'active') {
        $activeSuperAdmins = getDb()->query(
            "SELECT COUNT(*) FROM admin_users WHERE role = 'super_admin' AND status = 'active'"
        )->fetchColumn();
        if ((int) $activeSuperAdmins <= 1) {
            flashSet('error', 'This is the last active Super Admin account and cannot be deleted.');
            redirect('index.php');
        }
    }

    $delete = getDb()->prepare('DELETE FROM admin_users WHERE id = :id');
    $delete->execute(['id' => $id]);

    logActivity($currentAdmin['id'], 'delete', 'users', $targetUser['email']);
    flashSet('success', 'User deleted.');
}

redirect('index.php');
