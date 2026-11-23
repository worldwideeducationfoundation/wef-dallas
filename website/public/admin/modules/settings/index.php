<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/content.php';

requireCapability('settings.manage', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Settings';
$activeNav = 'Settings';
$linkBase = '../../';

// Row id=1 always exists after this — create it with NULLs on first
// visit so save.php can always be a plain UPDATE, never an upsert.
$existing = getDb()->query('SELECT * FROM site_settings WHERE id = 1')->fetch();
if (!$existing) {
    getDb()->exec('INSERT INTO site_settings (id) VALUES (1)');
    $existing = getDb()->query('SELECT * FROM site_settings WHERE id = 1')->fetch();
}

$values = flashInputGet() ?? $existing;

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Settings</h1>
        </div>

        <form method="post" action="save.php" class="admin-form">
          <?= csrfField() ?>

          <fieldset class="form-section">
            <legend>Contact details</legend>
            <p class="field-hint">Shown in the website footer, on the Contact and Volunteer pages, and in the home page contact section.</p>
            <div class="form-row-2">
              <div class="field">
                <label for="contact_email">Contact Email</label>
                <input type="email" id="contact_email" name="contact_email" value="<?= e($values['contact_email'] ?? '') ?>" maxlength="190">
              </div>
              <div class="field">
                <label for="contact_phone">Contact Phone</label>
                <input type="text" id="contact_phone" name="contact_phone" value="<?= e($values['contact_phone'] ?? '') ?>" maxlength="60">
              </div>
            </div>

            <div class="field">
              <label for="contact_address">Contact Address</label>
              <input type="text" id="contact_address" name="contact_address" value="<?= e($values['contact_address'] ?? '') ?>" maxlength="255">
            </div>
          </fieldset>

          <fieldset class="form-section">
            <legend>Donations</legend>
            <div class="field">
              <label for="donate_url">Donate link</label>
              <input type="url" id="donate_url" name="donate_url" value="<?= e($values['donate_url'] ?? '') ?>" maxlength="255" placeholder="<?= e(donateUrl()) ?>">
              <p class="field-hint">The checkout link the Donate page's "Continue" button opens. The "Donate Now" buttons in the header and on the home page open the Donate page, which then continues here. A Donorbox campaign link (https://donorbox.org/your-campaign) opens its donation form on the Donate page, with the chosen amount filled in. Leave blank to use the Bright Beginnings campaign on Donorbox.</p>
            </div>
          </fieldset>

          <fieldset class="form-section">
            <legend>Social media</legend>
            <p class="field-hint">The icons in the website footer. Leave a field blank to hide that icon.</p>
            <div class="form-row-2">
              <div class="field">
                <label for="facebook_url">Facebook URL</label>
                <input type="url" id="facebook_url" name="facebook_url" value="<?= e($values['facebook_url'] ?? '') ?>" maxlength="255">
              </div>
              <div class="field">
                <label for="twitter_url">X (Twitter) URL</label>
                <input type="url" id="twitter_url" name="twitter_url" value="<?= e($values['twitter_url'] ?? '') ?>" maxlength="255">
              </div>
            </div>

            <div class="form-row-2">
              <div class="field">
                <label for="instagram_url">Instagram URL</label>
                <input type="url" id="instagram_url" name="instagram_url" value="<?= e($values['instagram_url'] ?? '') ?>" maxlength="255">
              </div>
              <div class="field">
                <label for="linkedin_url">LinkedIn URL</label>
                <input type="url" id="linkedin_url" name="linkedin_url" value="<?= e($values['linkedin_url'] ?? '') ?>" maxlength="255">
              </div>
            </div>

            <div class="field">
              <label for="youtube_url">YouTube URL</label>
              <input type="url" id="youtube_url" name="youtube_url" value="<?= e($values['youtube_url'] ?? '') ?>" maxlength="255">
            </div>
          </fieldset>

          <div class="form-actions">
            <button type="submit" class="btn-submit">Save Settings</button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
