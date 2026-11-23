<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

processBulkAction([
    'table' => 'success_stories',
    'module' => 'success-stories',
    'trashType' => 'success-stories',
    'labelColumn' => 'title',
    'fileColumns' => ['image_path'],
]);
