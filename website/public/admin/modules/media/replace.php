<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_helpers.php';

requireCapability('media.upload', 'index.php');

$user = currentUser();
$pageTitle = 'Replace File';
$activeNav = 'Media Manager';
$linkBase = '../../';

$path = (string) ($_GET['path'] ?? '');
$abs = mediaFileFromPath($path);
$kind = mediaKind($path);
if ($abs === null || !in_array($kind, ['image', 'pdf'], true)) {
    flashSet('error', 'That file could not be found.');
    redirect('index.php');
}

$isUsed = isset(referencedUploadPaths()[$path]);
$url = '../../uploads/' . implode('/', array_map('rawurlencode', explode('/', $path)));

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Replace File</h1>
          <a href="index.php" class="link-back">&larr; Back to Media Manager</a>
        </div>

        <form method="post" action="replace-save.php" enctype="multipart/form-data" class="admin-form">
          <?= csrfField() ?>
          <input type="hidden" name="path" value="<?= e($path) ?>">

          <div class="field">
            <span class="field-label">Current file</span>
            <div class="current-image">
              <?php if ($kind === 'image'): ?>
                <img src="<?= e($url) ?>" alt="Current file">
              <?php endif; ?>
              <span><code><?= e($path) ?></code> - <?= $isUsed ? 'shown on the website' : 'not shown anywhere yet' ?>.</span>
            </div>
          </div>

          <div class="field">
            <label for="file">New <?= $kind === 'pdf' ? 'PDF' : 'photo' ?></label>
            <input type="file" id="file" name="file" accept="<?= $kind === 'pdf' ? '.pdf' : '.jpg,.jpeg,.png,.webp' ?>" required>
            <p class="field-hint">
              <?= $isUsed ? 'Every place on the website that shows this file will show the new one instead.' : 'The new file takes this one\'s place.' ?>
              The old file and its smaller copies are then deleted. <?= $kind === 'pdf' ? 'Up to 15MB.' : 'JPG, PNG or WebP, up to 5MB.' ?>
            </p>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit">Replace</button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
