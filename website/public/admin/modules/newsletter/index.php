<?php
/**
 * Newsletter — the Constant Contact connection.
 *
 * One screen that answers the only three questions anyone has about this
 * integration: is it connected, which list do signups join, and if it is not
 * working, why. Everything else in the module (connect, callback, disconnect,
 * save) is a handler this page links to.
 *
 * Its own module rather than a section of Settings because the actions here
 * are not "edit a field and save" — they are an OAuth round trip to a third
 * party, a list chosen from live data, and a failure state that needs
 * explaining. Bolting that onto the Settings form would have meant a save
 * button that sometimes redirects to constantcontact.com.
 */

require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/constant-contact.php';

requireCapability('newsletter.manage', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Newsletter';
$activeNav = 'Newsletter';
$linkBase = '../../';

$configured = ccIsConfigured();
$state = ccState();
$connected = !empty($state['refresh_token']);

/**
 * The list picker is populated from the live account, not from anything
 * stored. A list deleted in Constant Contact should disappear from this
 * dropdown rather than sit here until somebody notices signups failing.
 *
 * Only fetched when there is a connection to fetch it with — otherwise this
 * page would make a doomed API call on every load while disconnected.
 */
$lists = [];
$listError = null;
if ($connected) {
    $fetched = ccFetchLists();
    $lists = $fetched['lists'];
    $listError = $fetched['error'];
}

// A list id stored earlier that no longer exists on the account. Worth calling
// out explicitly: signups are failing, and the reason is not visible from the
// dropdown alone because the selected option simply is not in it.
$selectedListId = (string) ($state['list_id'] ?? '');
$selectedMissing = $connected
    && $selectedListId !== ''
    && $listError === null
    && !in_array($selectedListId, array_column($lists, 'id'), true);

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Newsletter</h1>
        </div>

        <?php if (!$configured): ?>
          <!-- Nothing on this screen can work without the app credentials, so
               this replaces the UI rather than sitting above it. -->
          <div class="panel">
            <div class="panel-header">
              <h2>Constant Contact is not set up yet</h2>
            </div>
            <div class="panel-body">
              <p>
                The footer newsletter form is working — every address typed into it is saved and
                appears under <a href="<?= e($linkBase) ?>modules/submissions/index.php">Form Submissions</a>.
                They are just not being added to a Constant Contact mailing list automatically yet.
              </p>
              <p>To switch that on, someone with access to the server needs to add three values to
                 <code>config/config.php</code>:</p>
              <ul class="field-hint-list">
                <li><code>CC_CLIENT_ID</code> — the API key from your Constant Contact developer app</li>
                <li><code>CC_CLIENT_SECRET</code> — the app secret generated alongside it</li>
                <li><code>CC_REDIRECT_URI</code> — <code><?= e(ccRedirectUri() ?: 'the address of this module\'s callback.php') ?></code></li>
              </ul>
              <p class="field-hint">
                Step-by-step instructions are in <code>config/config.sample.php</code>.
              </p>
            </div>
          </div>
        <?php else: ?>

          <div class="panel">
            <div class="panel-header">
              <h2>Connection</h2>
              <span class="status-badge status-badge--<?= $connected ? 'replied' : 'new' ?>">
                <?= $connected ? 'Connected' : 'Not connected' ?>
              </span>
            </div>

            <div class="panel-body">
              <?php if ($connected): ?>
                <dl class="submission-meta">
                  <div>
                    <dt>Connected</dt>
                    <dd>
                      <?php if ($state['connected_at'] !== null): ?>
                        <?= e(date('j M Y \a\t g:i a', strtotime((string) $state['connected_at']))) ?>
                      <?php else: ?>
                        &mdash;
                      <?php endif; ?>
                    </dd>
                  </div>
                  <div>
                    <dt>Signups are added to</dt>
                    <dd>
                      <?php if ($selectedListId === ''): ?>
                        <strong>No list chosen yet</strong>
                      <?php else: ?>
                        <?= e((string) ($state['list_name'] ?? $selectedListId)) ?>
                      <?php endif; ?>
                    </dd>
                  </div>
                  <div>
                    <dt>Access token renews</dt>
                    <dd class="data-table-secondary">
                      <?php if ($state['expires_at'] !== null): ?>
                        automatically, before <?= e(date('j M Y \a\t g:i a', strtotime((string) $state['expires_at']))) ?>
                      <?php else: ?>
                        on the next signup
                      <?php endif; ?>
                    </dd>
                  </div>
                </dl>
              <?php else: ?>
                <p>
                  Connect the site to your Constant Contact account. You will be sent to Constant
                  Contact to sign in and approve access, then brought straight back here.
                </p>
                <p class="field-hint">
                  Signups are being saved either way — connecting means they also reach your mailing
                  list without anyone copying them across.
                </p>
              <?php endif; ?>

              <?php if (!empty($state['last_error'])): ?>
                <!-- Escaped like any other stored string. This text originates
                     from Constant Contact, not from us, and is passed through
                     ccRedact() before it is ever written. -->
                <div class="alert alert-error" role="alert">
                  <strong>Last problem:</strong> <?= e((string) $state['last_error']) ?>
                  <?php if ($state['last_error_at'] !== null): ?>
                    <span class="data-table-secondary">
                      (<?= e(date('j M Y \a\t g:i a', strtotime((string) $state['last_error_at']))) ?>)
                    </span>
                  <?php endif; ?>
                </div>
              <?php endif; ?>

              <div class="form-actions">
                <!-- POST, not a link. Starting an OAuth flow is a state change
                     (it writes the CSRF state into the session), and a GET
                     would let any page on the internet start one by embedding
                     an image. -->
                <form method="post" action="connect.php">
                  <?= csrfField() ?>
                  <button type="submit" class="btn-submit">
                    <?= $connected ? 'Reconnect' : 'Connect to Constant Contact' ?>
                  </button>
                </form>
              </div>
            </div>
          </div>

          <?php if ($connected): ?>
            <div class="panel">
              <div class="panel-header">
                <h2>Mailing list</h2>
              </div>

              <div class="panel-body">
                <?php if ($listError !== null): ?>
                  <div class="alert alert-error" role="alert">
                    Could not read your lists from Constant Contact: <?= e($listError) ?>
                  </div>
                <?php elseif ($lists === []): ?>
                  <p>
                    This Constant Contact account has no mailing lists yet. Create one in Constant
                    Contact, then reload this page.
                  </p>
                <?php else: ?>
                  <?php if ($selectedMissing): ?>
                    <div class="alert alert-error" role="alert">
                      The list this site was sending signups to no longer exists on your Constant
                      Contact account. Choose another one below — until you do, new signups are
                      saved here but not added to any list.
                    </div>
                  <?php endif; ?>

                  <form method="post" action="save.php" class="admin-form">
                    <?= csrfField() ?>

                    <div class="field">
                      <label for="cc-list">Add newsletter signups to</label>
                      <select id="cc-list" name="list_id" required>
                        <option value="">Choose a list…</option>
                        <?php foreach ($lists as $list): ?>
                          <option value="<?= e($list['id']) ?>"<?= $list['id'] === $selectedListId ? ' selected' : '' ?>>
                            <?= e($list['name']) ?> (<?= number_format($list['count']) ?> contacts)
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <p class="field-hint">
                        Addresses are added with sign-up-form permission, which is what actually
                        happened — somebody typed their address into the form in the site footer.
                      </p>
                    </div>

                    <div class="form-actions">
                      <button type="submit" class="btn-submit">Save list</button>
                    </div>
                  </form>
                <?php endif; ?>
              </div>
            </div>

            <form method="post" action="disconnect.php" class="submission-danger"
                  data-confirm="Disconnect Constant Contact? New signups will still be saved here, but will stop being added to your mailing list.">
              <?= csrfField() ?>
              <button type="submit" class="link-delete">Disconnect Constant Contact</button>
            </form>
          <?php endif; ?>

        <?php endif; ?>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
