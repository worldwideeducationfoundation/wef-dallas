<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

processBulkAction([
    'table' => 'completed_projects',
    'module' => 'completed-projects',
    'trashType' => 'completed-projects',
    'labelColumn' => 'title',
    'fileColumns' => ['image_path', 'secondary_image_path', 'tertiary_image_path', 'map_image_path'],
]);
