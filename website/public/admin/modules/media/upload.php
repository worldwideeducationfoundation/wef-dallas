<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

requireCapability('media.upload', 'index.php');

$user = currentUser();
$pageTitle = 'Upload Files';
$activeNav = 'Media Manager';
$linkBase = '../../';

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Upload Files</h1>
          <a href="index.php" class="link-back">&larr; Back to Media Manager</a>
        </div>

        <form method="post" action="upload-save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>

          <div class="field">
            <label for="files">Files</label>
            <input type="file" id="files" name="files[]" accept=".jpg,.jpeg,.png,.webp,.pdf" multiple required>
            <p class="field-hint">
              Photos (JPG, PNG or WebP, up to 5MB each) and PDFs (up to 15MB each), up to 12 per upload.
              They go into the <strong>Library</strong> folder; use <strong>Copy link</strong> in the list to link to one,
              for example from a project update's "Read more" link.
            </p>
            <p class="field-hint">
              To show a photo in a section of the site (Team, Gallery, a project...), add it in that section instead -
              the section then shows it and keeps track of it.
            </p>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit">Upload</button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
