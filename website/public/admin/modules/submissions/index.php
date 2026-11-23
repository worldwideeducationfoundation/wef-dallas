<?php
/**
 * Form Submissions — everything the public contact and volunteer forms sent.
 *
 * Reads the table that api/contact.php writes. Unlike the content modules
 * there is no create and no edit: rows arrive from outside and the only thing
 * an admin changes about one is how far through triage it is. What this page
 * has instead is a status workflow, a message preview, and an export — the
 * three things you actually want from an inbox.
 *
 * Sorted newest first by default, which is the only sensible order for an
 * inbox and the opposite of the display_order default the content lists use.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../../../includes/form-submissions.php';
require_once __DIR__ . '/_helpers.php';

requireCapability('submissions.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Form Submissions';
$activeNav = 'Form Submissions';
$linkBase = '../../';

$sortable = [
    'received' => 'created_at',
    'name' => 'name',
    'email' => 'email',
    'form' => 'form_type',
    'status' => 'status',
];

$params = listParams($sortable, 'received', ['form_type', 'status']);
if (!isset($_GET['dir'])) {
    $params['dir'] = 'DESC'; // newest first — see the file comment.
}

// Search covers the message body as well as the sender, because "who wrote to
// us about scholarships" is at least as common a question as "what did Sara
// send". notTrashed() keeps deleted rows out no matter which filters are on.
$where = listWhere(
    $params,
    ['name', 'email', 'subject', 'message'],
    ['form_type' => 'form_type', 'status' => 'status'],
    [notTrashed()]
);

$countStmt = getDb()->prepare('SELECT COUNT(*) FROM form_submissions' . $where['sql']);
$countStmt->execute($where['params']);
$total = (int) $countStmt->fetchColumn();

$rowsStmt = getDb()->prepare(
    'SELECT id, form_type, name, email, subject, message, status, created_at,
            newsletter_synced_at, newsletter_sync_error
     FROM form_submissions'
    . $where['sql']
    . listOrderLimit($params, 'id DESC')
);
$rowsStmt->execute($where['params']);
$submissions = $rowsStmt->fetchAll();

// Asked once for the whole page rather than per row — see submissionSyncBadge().
$ccActive = ccIsActive();

$unread = submissionUnreadCount();
$isFiltered = $params['q'] !== '' || $params['filters'] !== [];

// The export link carries the current search and filters, so "export what I am
// looking at" needs no second set of controls. page is dropped deliberately:
// an export of page 3 of a filtered list is almost never what was meant, and
// export.php ignores it anyway.
$exportQuery = listUrl(['page' => null]);
$exportUrl = 'export.php' . ($exportQuery === '?' ? '' : $exportQuery);

$formOptions = [];
foreach (SUBMISSION_FORMS as $key => $config) {
    $formOptions[$key] = $config['label'];
}

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Form Submissions</h1>
          <?php if ($total > 0): ?>
            <a href="<?= e($exportUrl) ?>" class="btn-primary">Export CSV</a>
          <?php endif; ?>
        </div>

        <?php if ($unread > 0): ?>
          <p class="submission-summary">
            <strong><?= $unread ?></strong> new submission<?= $unread === 1 ? '' : 's' ?>
            waiting to be read.
            <?php if (($params['filters']['status'] ?? '') !== 'new'): ?>
              <a href="<?= e(listUrl(['status' => 'new', 'page' => null])) ?>">Show only new</a>
            <?php endif; ?>
          </p>
        <?php endif; ?>

        <?= listToolbar($params, [
            'form_type' => ['label' => 'Forms', 'options' => $formOptions],
            'status' => ['label' => 'Statuses', 'options' => SUBMISSION_STATUSES],
        ], 'Search name, email, or message…') ?>

        <?php if (!$submissions): ?>
          <div class="empty-state">
            <?= $isFiltered
                ? 'No submissions match that search.'
                : 'No form submissions yet. Messages sent from the Contact and Volunteer pages will appear here.' ?>
          </div>
        <?php else: ?>
          <?php if (can('submissions.manage')): ?>
            <form method="post" action="bulk.php" id="bulk-form"><?= csrfField() ?></form>
            <?= submissionBulkToolbar() ?>
          <?php endif; ?>

          <div class="table-scroll">
            <table class="data-table">
              <thead>
                <tr>
                  <?php if (can('submissions.manage')): ?>
                    <th class="col-check">
                      <input type="checkbox" data-bulk-all aria-label="Select all submissions on this page">
                    </th>
                  <?php endif; ?>
                  <?= listSortHeader('Received', 'received', $params) ?>
                  <?= listSortHeader('From', 'name', $params) ?>
                  <?= listSortHeader('Form', 'form', $params) ?>
                  <th>Subject &amp; message</th>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($submissions as $row): ?>
                  <?php
                  // Unread rows are marked on the row itself rather than only by
                  // the status badge, so a full page of them reads as an inbox
                  // at a glance instead of a wall of identical lines.
                  $isNew = $row['status'] === 'new';
                  ?>
                  <tr<?= $isNew ? ' class="is-unread"' : '' ?>>
                    <?php if (can('submissions.manage')): ?>
                      <td class="col-check">
                        <input type="checkbox" name="ids[]" form="bulk-form" value="<?= (int) $row['id'] ?>"
                               data-bulk-item aria-label="Select the message from <?= e($row['name']) ?>">
                      </td>
                    <?php endif; ?>
                    <td>
                      <div class="data-table-primary"><?= e(date('M j, Y', strtotime($row['created_at']))) ?></div>
                      <div class="data-table-secondary"><?= e(date('g:i a', strtotime($row['created_at']))) ?></div>
                    </td>
                    <td>
                      <div class="data-table-primary"><?= e($row['name']) ?></div>
                      <div class="data-table-secondary"><?= e($row['email']) ?></div>
                    </td>
                    <td><?= e(submissionFormLabel($row['form_type'])) ?></td>
                    <td>
                      <div class="data-table-primary"><?= e(submissionSubjectLabel($row['form_type'], $row['subject'])) ?></div>
                      <div class="data-table-secondary submission-excerpt">
                        <?php
                        // Whitespace collapsed so a message written as short
                        // lines does not become a tall ragged cell; the full
                        // text is one click away on the detail page.
                        $excerpt = trim(preg_replace('/\s+/u', ' ', $row['message']) ?? '');
                        echo e(mb_strlen($excerpt) > 120 ? mb_substr($excerpt, 0, 120) . '…' : $excerpt);
                        ?>
                      </div>
                    </td>
                    <td>
                      <span class="status-badge status-badge--<?= e($row['status']) ?>">
                        <?= e(submissionStatusLabel($row['status'])) ?>
                      </span>
                      <?php // Newsletter rows carry a second badge: whether the
                            // address actually reached the mailing list. Empty
                            // for every other form, and for every row when the
                            // integration is switched off. ?>
                      <?php if ($badge = submissionSyncBadge($row, $ccActive)): ?>
                        <div class="submission-sync-badge"><?= $badge ?></div>
                      <?php endif; ?>
                    </td>
                    <td class="data-table-actions">
                      <a href="view.php?id=<?= (int) $row['id'] ?>" class="link-edit">
                        <?= $isNew ? 'Read' : 'View' ?><span class="sr-only"> the message from <?= e($row['name']) ?></span>
                      </a>
                      <?php if (can('submissions.manage')): ?>
                        <form method="post" action="delete.php" data-confirm="Move the message from &quot;<?= e($row['name']) ?>&quot; to Trash? You can restore it from there.">
                          <?= csrfField() ?>
                          <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                          <button type="submit" class="link-delete">Delete<span class="sr-only"> the message from <?= e($row['name']) ?></span></button>
                        </form>
                      <?php endif; ?>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?= listPagination($total, $params) ?>
        <?php endif; ?>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
