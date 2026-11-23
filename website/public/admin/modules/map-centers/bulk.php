<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

processBulkAction([
    'table' => 'map_centers',
    'module' => 'map-centers',
    'trashType' => 'map-centers',
    'labelColumn' => 'village',
    'fileColumns' => [],
]);
