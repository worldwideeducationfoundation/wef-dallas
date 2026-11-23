<?php
require_once __DIR__ . '/../../includes/bootstrap.php';

requireLogin();

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isEdit = $id !== null;

requireCapability($isEdit ? 'content.edit' : 'content.create');

$backTarget = $isEdit ? 'form.php?id=' . $id : 'form.php';

$input = [
    'milestone_date' => trim((string) ($_POST['milestone_date'] ?? '')),
    'centers_target' => (int) ($_POST['centers_target'] ?? 0),
    'students_target' => (int) ($_POST['students_target'] ?? 0),
    'teachers_target' => (int) ($_POST['teachers_target'] ?? 0),
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['milestone_date'] === '' || mb_strlen($input['milestone_date']) > 40) {
    $errors[] = 'Target date is required (max 40 characters).';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}
foreach (['centers_target', 'students_target', 'teachers_target'] as $field) {
    if ($input[$field] < 0) {
        $errors[] = 'Targets cannot be negative.';
        break;
    }
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE impact_milestones SET milestone_date = :milestone_date, centers_target = :centers_target,
         students_target = :students_target, teachers_target = :teachers_target,
         display_order = :display_order, status = :status
         WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'impact-milestones', $input['milestone_date']);
    flashSet('success', 'Milestone updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO impact_milestones (milestone_date, centers_target, students_target, teachers_target, display_order, status)
         VALUES (:milestone_date, :centers_target, :students_target, :teachers_target, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'impact-milestones', $input['milestone_date']);
    flashSet('success', 'Milestone created.');
}

redirect('index.php');
