<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

// Capability is checked inside processBulkAction(), which knows whether
// the requested action needs content.edit or content.delete.
processBulkAction([
    'table' => 'team_members',
    'module' => 'team',
    'trashType' => 'team',
    'labelColumn' => 'name',
    'fileColumns' => ['image_path'],
]);
