<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/trash.php';

// The whole handler lives in processDelete() (includes/trash.php): POST +
// CSRF gate, capability check, lookup, soft delete, audit line, flash,
// redirect. The type is the key into TRASH_TYPES, which already knows this
// module's table, label column and display noun.
processDelete('team');
