<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../../../includes/content-blocks.php';
require_once __DIR__ . '/../../includes/media-specs.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';

$pageKey = (string) ($_GET['page'] ?? '');

if (!isset(blockRegistry()[$pageKey])) {
    flashSet('error', 'That page could not be found.');
    redirect('index.php');
}

$config = blockRegistry()[$pageKey];
$pageTitle = 'Edit ' . $config['label'];
$activeNav = 'Page Content';

// Flashed input (from a failed save) wins, so nothing typed is lost.
$flashed = flashInputGet();

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($config['label']) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Page Content</a>
        </div>

        <?php $hasHeaderImage = in_array($pageKey, PAGE_HEADER_IMAGE_PAGES, true); ?>
        <form method="post" action="save.php" class="admin-form"<?= $hasHeaderImage || in_array('image', array_column($config['blocks'], 'type'), true) ? ' enctype="multipart/form-data"' : '' ?>>
          <?= csrfField() ?>
          <input type="hidden" name="page" value="<?= e($pageKey) ?>">

          <?php if ($hasHeaderImage): ?>
            <?php
              $headerRow = getDb()->prepare('SELECT header_image FROM pages WHERE page_key = :page');
              $headerRow->execute(['page' => $pageKey]);
              $currentHeader = $headerRow->fetchColumn() ?: null;
              // Home has no banner of its own - its image goes behind the "Our
              // Core Purpose" section - so it gets its own wording and crop.
              $isPurpose = $pageKey === 'home';
              $imageSpec = $isPurpose ? 'purpose-background' : 'page-header';
            ?>
            <fieldset class="form-section">
              <legend><?= $isPurpose ? 'Core Purpose background' : 'Header image' ?></legend>
              <div class="field">
                <label for="header_image"><?= $isPurpose ? 'Photo behind "Our Core Purpose" (optional)' : 'Banner photo (optional)' ?></label>
                <input type="file" id="header_image" name="header_image" accept=".jpg,.jpeg,.png,.webp"<?= imageSpecAttributes($imageSpec) ?><?= can('content.edit') ? '' : ' disabled' ?>>
                <?= imageSpecHelp($imageSpec) ?>
                <?php if ($currentHeader): ?>
                  <div class="current-image current-image--wide" id="current-header">
                    <img src="../../uploads/<?= e($currentHeader) ?>" alt="Current header image">
                    <span>Current header image - upload a new file to replace it.</span>
                  </div>
                  <?php if (can('content.edit')): ?>
                    <?= removeFileCheckbox('header_image', $currentHeader, 'current-header', $isPurpose ? 'Remove the photo (back to the plain green banner)' : 'Remove the header image (back to the plain green banner)') ?>
                  <?php endif; ?>
                <?php else: ?>
                  <p class="field-hint">No image yet - <?= $isPurpose ? 'the Core Purpose banner is' : 'the page shows' ?> plain green.</p>
                <?php endif; ?>
              </div>
            </fieldset>
          <?php endif; ?>

          <?php
          // Blocks are rendered under their optional 'group' heading. A page
          // with three blocks reads fine as a flat list; the home page has
          // around a dozen sections, and an unlabelled column of that many
          // textareas gives no clue which part of the page each one moves.
          // Blocks with no group fall under '' and render first, ungrouped.
          $grouped = [];
          foreach ($config['blocks'] as $key => $block) {
              $grouped[$block['group'] ?? ''][$key] = $block;
          }
          ?>

          <?php foreach ($grouped as $groupLabel => $blocks): ?>
            <?php if ($groupLabel !== ''): ?>
              <h2 class="admin-form-title block-group-title"><?= e($groupLabel) ?></h2>
            <?php endif; ?>

            <?php foreach ($blocks as $key => $block): ?>
              <?php
                $stored = blockValue($pageKey, $key);
                $value = $flashed[$key] ?? $stored;
                $isCustom = blockIsOverridden($pageKey, $key);
                $fieldId = 'block_' . preg_replace('/[^a-z0-9_]/i', '_', $key);
                $limit = blockLimit($block['type']);
              ?>
              <div class="field">
                <label for="<?= e($fieldId) ?>">
                  <?= e($block['label']) ?>
                </label>

                <?php if ($block['type'] === 'image'): ?>
                  <?php // The picture on the page now: the upload, or the original. ?>
                  <div class="current-image" id="current-<?= e($fieldId) ?>">
                    <img src="../../../<?= e(blockImage($pageKey, $key)) ?>" alt="Current picture">
                    <span><?= $isCustom ? 'Your uploaded picture.' : 'The original picture.' ?> Choose a file to replace it.</span>
                  </div>
                  <input type="file" id="<?= e($fieldId) ?>" name="block_image_<?= e($key) ?>" accept=".jpg,.jpeg,.png,.webp"<?= can('content.edit') ? '' : ' disabled' ?>>
                  <?php if (!empty($block['help'])): ?>
                    <p class="field-hint"><?= e($block['help']) ?></p>
                  <?php endif; ?>
                  <?php if ($isCustom && can('content.edit')): ?>
                    <label class="remove-file">
                      <input type="checkbox" name="reset[<?= e($key) ?>]" value="1" data-remove-toggle="current-<?= e($fieldId) ?>">
                      <span>Go back to the original picture</span>
                    </label>
                  <?php endif; ?>
              </div>
                  <?php continue; ?>
                <?php elseif ($block['type'] === 'body'): ?>
                  <textarea id="<?= e($fieldId) ?>" name="blocks[<?= e($key) ?>]" rows="14"
                            maxlength="<?= $limit ?>" class="block-body-input"><?= e($value) ?></textarea>
                  <p class="field-hint">
                    Leave a blank line between paragraphs.
                    Start a line with <code>- </code> for a bullet, or <code>## </code> for a subheading.
                    Everything else is shown exactly as typed — HTML is not allowed here and will appear as text.
                  </p>
                <?php elseif ($block['type'] === 'textarea'): ?>
                  <textarea id="<?= e($fieldId) ?>" name="blocks[<?= e($key) ?>]" rows="4" maxlength="<?= $limit ?>"><?= e($value) ?></textarea>
                <?php else: ?>
                  <input type="text" id="<?= e($fieldId) ?>" name="blocks[<?= e($key) ?>]" value="<?= e($value) ?>" maxlength="<?= $limit ?>">
                <?php endif; ?>

                <?php if (!empty($block['help'])): ?>
                  <p class="field-hint"><?= e($block['help']) ?></p>
                <?php endif; ?>

                <p class="field-help">
                  <?php if ($isCustom): ?>
                    <span class="status-badge status-badge--active">Customised</span>
                    <?php // Long defaults are truncated: a body block's original
                          // can be 2,000 characters, and printing all of it under
                          // every field buries the field itself. ?>
                    Original: “<?= e(mb_strlen($block['default']) > 160 ? mb_substr($block['default'], 0, 160) . '…' : $block['default']) ?>”
                  <?php else: ?>
                    <span class="status-badge status-badge--draft">Default</span>
                    Showing the original wording.
                  <?php endif; ?>
                </p>

                <?php if ($isCustom && can('content.edit')): ?>
                  <label class="remove-file">
                    <input type="checkbox" name="reset[<?= e($key) ?>]" value="1">
                    <span>Reset this block to its original wording</span>
                  </label>
                <?php endif; ?>
              </div>
            <?php endforeach; ?>
          <?php endforeach; ?>

          <?php if (can('content.edit')): ?>
            <div class="form-actions">
              <button type="submit" class="btn-submit">Save Changes</button>
            </div>
          <?php else: ?>
            <p class="field-help">You have read-only access to page content.</p>
          <?php endif; ?>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
