<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

processBulkAction([
    'table' => 'video_gallery',
    'module' => 'video-gallery',
    'trashType' => 'video-gallery',
    'labelColumn' => 'title',
    'fileColumns' => [],
]);
