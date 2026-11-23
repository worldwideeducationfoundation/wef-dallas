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
    'what_header' => trim((string) ($_POST['what_header'] ?? '')),
    'what_sub' => trim((string) ($_POST['what_sub'] ?? '')) ?: null,
    'launch_year' => trim((string) ($_POST['launch_year'] ?? '')) ?: null,
    'where_text' => trim((string) ($_POST['where_text'] ?? '')) ?: null,
    'who_text' => trim((string) ($_POST['who_text'] ?? '')) ?: null,
    'impact_text' => trim((string) ($_POST['impact_text'] ?? '')) ?: null,
    'notes_text' => trim((string) ($_POST['notes_text'] ?? '')) ?: null,
    'notes_highlight' => trim((string) ($_POST['notes_highlight'] ?? '')) ?: null,
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['what_header'] === '' || mb_strlen($input['what_header']) > 200) {
    $errors[] = 'Project name is required (max 200 characters).';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE mission_projects SET what_header = :what_header, what_sub = :what_sub,
         launch_year = :launch_year, where_text = :where_text, who_text = :who_text,
         impact_text = :impact_text, notes_text = :notes_text, notes_highlight = :notes_highlight,
         display_order = :display_order, status = :status
         WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'mission-projects', $input['what_header']);
    flashSet('success', 'Mission project updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO mission_projects (what_header, what_sub, launch_year, where_text, who_text,
         impact_text, notes_text, notes_highlight, display_order, status)
         VALUES (:what_header, :what_sub, :launch_year, :where_text, :who_text,
         :impact_text, :notes_text, :notes_highlight, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'mission-projects', $input['what_header']);
    flashSet('success', 'Mission project created.');
}

redirect('index.php');
