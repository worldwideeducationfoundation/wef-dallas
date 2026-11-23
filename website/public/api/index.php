<?php
/**
 * Placeholder — no public API endpoints exist yet. Reserves the /api/
 * namespace and returns a real 404 instead of a blank page or directory
 * listing. Real endpoints (e.g. contact.php) go in this same folder.
 */

http_response_code(404);
header('Content-Type: application/json');
echo json_encode(['error' => 'Not found']);
