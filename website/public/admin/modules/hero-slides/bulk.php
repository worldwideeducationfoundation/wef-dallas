<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

processBulkAction([
    'table' => 'hero_slides',
    'module' => 'hero-slides',
    'trashType' => 'hero-slides',
    'labelColumn' => 'title_highlight',
    'fileColumns' => ['image_path'],
]);
