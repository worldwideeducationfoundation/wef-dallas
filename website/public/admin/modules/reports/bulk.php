<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

// Capability is checked inside processBulkAction(), which knows whether
// the requested action needs content.edit or content.delete.
processBulkAction([
    'table' => 'annual_reports',
    'module' => 'reports',
    'trashType' => 'reports',
    'labelColumn' => 'title',
    'fileColumns' => ['cover_image_path', 'pdf_path'],
]);
