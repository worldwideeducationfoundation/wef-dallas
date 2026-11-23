<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

processBulkAction([
    'table' => 'project_updates',
    'module' => 'updates',
    'trashType' => 'updates',
    'labelColumn' => 'title',
    'fileColumns' => ['image_path'],
]);
