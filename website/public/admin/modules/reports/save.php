<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/upload.php';

requireLogin();

requirePostWithCsrf();

$user = currentUser();
$id = isset($_POST['id']) && $_POST['id'] !== '' ? (int) $_POST['id'] : null;
$isEdit = $id !== null;

requireCapability($isEdit ? 'content.edit' : 'content.create');

$backTarget = $isEdit ? 'form.php?id=' . $id : 'form.php';

$input = [
    'title' => trim((string) ($_POST['title'] ?? '')),
    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
    'display_order' => (int) ($_POST['display_order'] ?? 0),
    'status' => (string) ($_POST['status'] ?? 'draft'),
];

$errors = [];

if ($input['title'] === '' || mb_strlen($input['title']) > 160) {
    $errors[] = 'Title is required (max 160 characters).';
}
if (!in_array($input['status'], ['draft', 'active'], true)) {
    $errors[] = 'Please choose a valid status.';
}
// A report with no PDF at all isn't a report — required on create; on
// edit, the existing PDF can be kept without re-uploading.
if (!$isEdit && (empty($_FILES['pdf']) || $_FILES['pdf']['error'] === UPLOAD_ERR_NO_FILE)) {
    $errors[] = 'A report PDF is required.';
}

if ($errors) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', implode(' ', $errors));
    redirect($backTarget);
}

$oldCoverPath = null;
$oldPdfPath = null;
if ($isEdit) {
    $existingStmt = getDb()->prepare('SELECT cover_image_path, pdf_path FROM annual_reports WHERE id = :id LIMIT 1');
    $existingStmt->execute(['id' => $id]);
    $existingRow = $existingStmt->fetch();
    $oldCoverPath = $existingRow['cover_image_path'] ?? null;
    $oldPdfPath = $existingRow['pdf_path'] ?? null;
}

$coverUpload = handleImageUpload('cover_image', 'reports', $oldCoverPath, !empty($_POST['remove_cover_image']));
if (!$coverUpload['ok']) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', $coverUpload['error']);
    redirect($backTarget);
}

$pdfUpload = handleDocumentUpload('pdf', 'reports', $oldPdfPath);
if (!$pdfUpload['ok']) {
    flashInputSet(array_merge($input, ['id' => $id]));
    flashSet('error', $pdfUpload['error']);
    redirect($backTarget);
}

$input['cover_image_path'] = $coverUpload['path'];
$input['pdf_path'] = $pdfUpload['path'];

if ($isEdit) {
    $stmt = getDb()->prepare(
        'UPDATE annual_reports SET title = :title, description = :description,
         cover_image_path = :cover_image_path, pdf_path = :pdf_path,
         display_order = :display_order, status = :status WHERE id = :id'
    );
    $stmt->execute(array_merge($input, ['id' => $id]));
    logActivity($user['id'], 'update', 'reports', $input['title']);
    flashSet('success', 'Report updated.');
} else {
    $stmt = getDb()->prepare(
        'INSERT INTO annual_reports (title, description, cover_image_path, pdf_path, display_order, status)
         VALUES (:title, :description, :cover_image_path, :pdf_path, :display_order, :status)'
    );
    $stmt->execute($input);
    logActivity($user['id'], 'create', 'reports', $input['title']);
    flashSet('success', 'Report created.');
}

redirect('index.php');
