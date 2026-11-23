<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/reorder.php';

requireCapability('content.edit');

processReorder('project_updates', 'updates');
