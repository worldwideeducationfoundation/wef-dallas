<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

processBulkAction([
    'table' => 'donate_options',
    'module' => 'donate-options',
    'trashType' => 'donate-options',
    'labelColumn' => 'slug',
    'fileColumns' => ['image_path'],
]);
