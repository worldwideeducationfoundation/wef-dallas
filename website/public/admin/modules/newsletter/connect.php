<?php
/**
 * Starts the OAuth round trip to Constant Contact.
 *
 * POST rather than a plain link on purpose. This writes the anti-forgery state
 * into the session, which makes it a state change; as a GET, any page anywhere
 * could kick off an authorisation flow in a logged-in admin's browser just by
 * embedding an <img> pointing here.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/constant-contact.php';

requireCapability('newsletter.manage', '../../dashboard.php');

requirePostWithCsrf();

if (!ccIsConfigured()) {
    flashSet('error', 'Constant Contact is not set up in config/config.php yet.');
    redirect('index.php');
}

/**
 * The OAuth `state` parameter: a random value we send to Constant Contact and
 * require back, unchanged, on the callback.
 *
 * What it prevents is specific and worth naming — without it, an attacker can
 * complete an authorisation against THEIR OWN Constant Contact account, keep
 * the resulting code, and then trick a logged-in WEF administrator into
 * visiting the callback with it. WEF's website would end up wired to the
 * attacker's mailing list, and every subscriber the footer form collects would
 * be delivered to them.
 */
$state = bin2hex(random_bytes(32));
$_SESSION['cc_oauth_state'] = $state;
$_SESSION['cc_oauth_started_at'] = time();

logActivity(currentUser()['id'], 'update', 'settings', 'Started Constant Contact authorisation');

redirect(ccAuthorizeUrl($state));
