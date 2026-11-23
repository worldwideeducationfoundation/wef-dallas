<?php
/**
 * One submission, in full.
 *
 * The list can only show an excerpt, and an enquiry that has to be read in a
 * 120-character preview is one that gets misjudged. This is the page where the
 * whole message is legible, where the status moves, and where an internal note
 * can be left for whoever picks it up next.
 *
 * Opening a 'new' submission marks it 'read' automatically. That is the one
 * status change nobody should have to make by hand: it is what "new" means,
 * and leaving it to a button guarantees a list of items marked new that
 * everyone has already read.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/trash.php';
require_once __DIR__ . '/../../../../includes/form-submissions.php';
require_once __DIR__ . '/_helpers.php';

requireCapability('submissions.view', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';
$activeNav = 'Form Submissions';

$id = queryId();
if ($id === null) {
    redirect('index.php');
}

$submission = dbFetchOne(
    'SELECT s.*, u.name AS handled_by_name
       FROM form_submissions s
       LEFT JOIN admin_users u ON u.id = s.handled_by
      WHERE s.id = :id AND s.deleted_at IS NULL
      LIMIT 1',
    ['id' => $id]
);

if ($submission === null) {
    flashSet('error', 'That submission could not be found.');
    redirect('index.php');
}

// Auto-read, but only for someone who is allowed to act on submissions —
// a view-only reader should not be silently changing the queue for everyone
// else. Done before the header renders so the sidebar badge is already
// correct on this very page load.
if ($submission['status'] === 'new' && can('submissions.manage')) {
    submissionSetStatus([$id], 'read', (int) $user['id']);
    $submission['status'] = 'read';
    $submission['handled_by_name'] = $user['name'];
    $submission['handled_at'] = date('Y-m-d H:i:s');
}

$pageTitle = 'Submission from ' . $submission['name'];

$formLabel = submissionFormLabel($submission['form_type']);
$subjectLabel = submissionSubjectLabel($submission['form_type'], $submission['subject']);

// Mailing-list state for a newsletter signup — 'off' for everything else.
$syncState = submissionSyncState($submission, ccIsActive());

// Prefilled reply, opened in whatever mail client the admin actually uses.
// Deliberately a mailto: rather than a send-from-the-CMS feature: replying
// from the CMS would need SMTP credentials, a sent-items store and a bounce
// story, and would put WEF's replies somewhere their own mailbox cannot see.
$replySubject = 'Re: ' . $subjectLabel . ' — Worldwide Education Foundation';
$replyBody = "Hello " . $submission['name'] . ",\n\n\n\n"
    . "---\nYou wrote to us on " . date('j F Y', strtotime($submission['created_at'])) . ":\n"
    . $submission['message'];
$replyHref = 'mailto:' . rawurlencode($submission['email'])
    . '?subject=' . rawurlencode($replySubject)
    . '&body=' . rawurlencode($replyBody);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Submission from <?= e($submission['name']) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Form Submissions</a>
        </div>

        <div class="submission-detail">
          <div class="panel">
            <div class="panel-header">
              <h2><?= e($subjectLabel) ?></h2>
              <span class="status-badge status-badge--<?= e($submission['status']) ?>">
                <?= e(submissionStatusLabel($submission['status'])) ?>
              </span>
            </div>

            <dl class="submission-meta">
              <div>
                <dt>From</dt>
                <dd><?= e($submission['name']) ?></dd>
              </div>
              <div>
                <dt>Email</dt>
                <dd><a href="mailto:<?= e($submission['email']) ?>"><?= e($submission['email']) ?></a></dd>
              </div>
              <div>
                <dt>Form</dt>
                <dd><?= e($formLabel) ?></dd>
              </div>
              <div>
                <dt>Received</dt>
                <dd>
                  <time datetime="<?= e($submission['created_at']) ?>">
                    <?= e(date('j F Y \a\t g:i a', strtotime($submission['created_at']))) ?>
                  </time>
                </dd>
              </div>
              <?php if ($submission['handled_at'] !== null): ?>
                <div>
                  <dt>Last handled</dt>
                  <dd>
                    <?= e($submission['handled_by_name'] ?? 'Deleted user') ?>
                    on <?= e(date('j M Y \a\t g:i a', strtotime($submission['handled_at']))) ?>
                  </dd>
                </div>
              <?php endif; ?>
              <?php if ($syncState !== 'off'): ?>
                <div>
                  <dt>Mailing list</dt>
                  <dd>
                    <?php if ($syncState === 'synced'): ?>
                      Added to Constant Contact on
                      <?= e(date('j M Y \a\t g:i a', strtotime((string) $submission['newsletter_synced_at']))) ?>
                    <?php elseif ($syncState === 'failed'): ?>
                      <strong>Not added.</strong>
                      <span class="data-table-secondary"><?= e((string) $submission['newsletter_sync_error']) ?></span>
                    <?php else: ?>
                      <strong>Not sent.</strong>
                      <span class="data-table-secondary">
                        This signup arrived before Constant Contact was connected, so it was never
                        offered to the list.
                      </span>
                    <?php endif; ?>
                  </dd>
                </div>
              <?php endif; ?>
              <?php if (can('activity.view')): ?>
                <div>
                  <dt>Sender IP</dt>
                  <dd class="data-table-secondary"><?= e($submission['ip_address']) ?></dd>
                </div>
              <?php endif; ?>
            </dl>

            <?php if ($syncState === 'failed' || $syncState === 'pending'): ?>
              <?php if (can('submissions.manage')): ?>
                <!-- Retry runs the same ccSyncSubmission() the live signup does,
                     so a retried address is added on exactly the same terms as
                     one that worked first time. -->
                <form method="post" action="retry-sync.php" class="form-actions">
                  <?= csrfField() ?>
                  <input type="hidden" name="id" value="<?= (int) $submission['id'] ?>">
                  <button type="submit" class="btn-filter">Add this address to the mailing list</button>
                </form>
              <?php endif; ?>
            <?php endif; ?>

            <!-- The message, exactly as it was typed. Escaped by e() and
                 rendered in a pre-wrap block rather than run through nl2br or
                 any rich-text helper: this is untrusted text from an
                 anonymous visitor, and the only safe thing to do with it is
                 show it as characters. -->
            <div class="submission-message"><?= e($submission['message']) ?></div>
          </div>

          <?php if (can('submissions.manage')): ?>
            <!-- .admin-form is the same shell every content module's form uses,
                 so this page needs no styling of its own for the controls. -->
            <form method="post" action="status.php" class="admin-form">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int) $submission['id'] ?>">

              <h2 class="admin-form-title">Handle this submission</h2>

              <div class="field">
                <label for="submission-status">Status</label>
                <select id="submission-status" name="status" required>
                  <?php foreach (SUBMISSION_STATUSES as $value => $label): ?>
                    <option value="<?= e($value) ?>"<?= $submission['status'] === $value ? ' selected' : '' ?>>
                      <?= e($label) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="field">
                <label for="submission-note">Internal note</label>
                <textarea id="submission-note" name="admin_note" rows="4" maxlength="1000"
                          placeholder="Who is following this up, and what was agreed?"><?= e($submission['admin_note'] ?? '') ?></textarea>
                <p class="field-hint">Only visible here. The sender never sees this.</p>
              </div>

              <div class="form-actions">
                <button type="submit" class="btn-submit">Save</button>
                <a href="<?= e($replyHref) ?>" class="btn-filter">Reply by email</a>
              </div>
            </form>

            <form method="post" action="delete.php" class="submission-danger"
                  data-confirm="Move the message from &quot;<?= e($submission['name']) ?>&quot; to Trash? You can restore it from there.">
              <?= csrfField() ?>
              <input type="hidden" name="id" value="<?= (int) $submission['id'] ?>">
              <button type="submit" class="link-delete">Move this submission to Trash</button>
            </form>
          <?php endif; ?>
        </div>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
