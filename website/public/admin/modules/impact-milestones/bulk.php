<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/bulk.php';

requireLogin();

processBulkAction([
    'table' => 'impact_milestones',
    'module' => 'impact-milestones',
    'trashType' => 'impact-milestones',
    'labelColumn' => 'milestone_date',
    'fileColumns' => [],
]);
