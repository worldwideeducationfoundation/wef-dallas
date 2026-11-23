<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

// Sponsors are listed per group, so the redirect has to carry the
// group back — otherwise applying an action would bounce the user to the
// group list instead of the sponsor list they were working in.
$groupId = isset($_POST['group_id']) ? (int) $_POST['group_id'] : 0;

processBulkAction([
    'table' => 'sponsors',
    'module' => 'sponsors',
    'trashType' => 'sponsors',
    'labelColumn' => 'name',
    'redirect' => $groupId > 0 ? 'sponsors.php?group_id=' . $groupId : 'index.php',
]);
