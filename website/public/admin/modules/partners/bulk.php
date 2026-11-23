<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

processBulkAction([
    'table' => 'partners',
    'module' => 'partners',
    'trashType' => 'partners',
    'labelColumn' => 'name',
    'fileColumns' => ['logo_path'],
]);
