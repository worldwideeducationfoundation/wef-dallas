<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireLogin();

processReorder([
    'table' => 'annual_reports',
    'module' => 'reports',
]);
