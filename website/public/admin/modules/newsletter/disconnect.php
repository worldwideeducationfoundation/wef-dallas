<?php
/**
 * Forgets the Constant Contact tokens held by this site.
 *
 * Local only — it does not revoke anything at Constant Contact's end. The
 * stored refresh token simply stops being used and ages out on its own. Say so
 * on screen rather than implying a revocation that did not happen: anyone
 * disconnecting because they think a token leaked needs to know they should
 * also regenerate the app secret in the Constant Contact developer portal.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/constant-contact.php';

requireCapability('newsletter.manage', '../../dashboard.php');

requirePostWithCsrf();

$user = currentUser();

ccClearTokens('Disconnected from the admin panel by ' . $user['name'] . '.');

logActivity((int) $user['id'], 'update', 'settings', 'Disconnected Constant Contact');
flashSet(
    'success',
    'Constant Contact disconnected. Newsletter signups are still saved under Form Submissions. '
        . 'This site has forgotten its access, but has not revoked it at Constant Contact — do that '
        . 'from your Constant Contact account if you disconnected because of a leak.'
);
redirect('index.php');
