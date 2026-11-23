<?php
/**
 * Stores which mailing list newsletter signups are added to.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/constant-contact.php';

requireCapability('newsletter.manage', '../../dashboard.php');

requirePostWithCsrf();

$user = currentUser();
$listId = trim((string) ($_POST['list_id'] ?? ''));

if ($listId === '') {
    flashSet('error', 'Please choose a mailing list.');
    redirect('index.php');
}

/**
 * The submitted id is checked against the account's real lists rather than
 * stored as given.
 *
 * The dropdown already only offers valid options, but the dropdown is not what
 * arrives here — a POST is. Without this check any string would be written into
 * list_id, and the first sign anything was wrong would be newsletter signups
 * silently failing against a list that does not exist. Fetching also gets the
 * name, which is what the status screen displays.
 */
$fetched = ccFetchLists();

if (!$fetched['ok']) {
    flashSet('error', 'Could not confirm that list with Constant Contact: ' . $fetched['error']);
    redirect('index.php');
}

$match = null;
foreach ($fetched['lists'] as $list) {
    if ($list['id'] === $listId) {
        $match = $list;
        break;
    }
}

if ($match === null) {
    flashSet('error', 'That list no longer exists on your Constant Contact account. Please choose another.');
    redirect('index.php');
}

dbExecute(
    'UPDATE constant_contact SET list_id = :id, list_name = :name WHERE id = 1',
    ['id' => $match['id'], 'name' => mb_substr($match['name'], 0, 190)]
);

logActivity((int) $user['id'], 'update', 'settings', 'Newsletter list set to "' . $match['name'] . '"');
flashSet('success', 'Newsletter signups will be added to “' . $match['name'] . '”.');
redirect('index.php');
