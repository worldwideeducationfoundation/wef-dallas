<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

processBulkAction([
    'table' => 'mission_projects',
    'module' => 'mission-projects',
    'trashType' => 'mission-projects',
    'labelColumn' => 'what_header',
    'fileColumns' => [],
]);
