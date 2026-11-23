<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireLogin();

processReorder([
    'table' => 'map_centers',
    'module' => 'map-centers',
]);
