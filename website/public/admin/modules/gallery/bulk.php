<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

// Capability is checked inside processBulkAction(), which knows whether
// the requested action needs content.edit or content.delete.
processBulkAction([
    'table' => 'gallery_items',
    'module' => 'gallery',
    'trashType' => 'gallery',
    'labelColumn' => 'caption',
    'fileColumns' => ['image_path'],
]);
