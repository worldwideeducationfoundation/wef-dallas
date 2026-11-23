<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/../../includes/listing.php';
require_once __DIR__ . '/_helpers.php';

requireCapability('media.view', '../../dashboard.php');

$user = currentUser();
$pageTitle = 'Media Manager';
$activeNav = 'Media Manager';
$linkBase = '../../';

$uploadsRoot = realpath(__DIR__ . '/../../uploads');
$referenced = referencedUploadPaths();
$files = [];
$variants = [];
$totalBytes = 0;
$orphanCount = 0;
$folders = [];

if ($uploadsRoot !== false) {
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($uploadsRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $fileInfo) {
        // SKIP_DOTS only skips "." and "..", not dotfiles — .htaccess
        // (the upload-hardening file, see admin/uploads/.htaccess) lives
        // in this same tree and isn't user-uploaded content.
        if (!$fileInfo->isFile() || str_starts_with($fileInfo->getFilename(), '.')) {
            continue;
        }
        $relativePath = ltrim(str_replace('\\', '/', substr($fileInfo->getPathname(), strlen($uploadsRoot))), '/');
        $totalBytes += $fileInfo->getSize();

        // Size variants and WebP twins are generated from an original and
        // follow it (replaced and deleted with it), so they are counted
        // against their original instead of listed as files of their own.
        if (isImageDerivative($relativePath)) {
            $original = preg_replace(['/\.(jpg|jpeg|png)\.webp$/i', '/-\d+w(\.(jpg|jpeg|png|webp))(\.webp)?$/i'], ['.$1', '$1'], $relativePath);
            $variants[$original] = ($variants[$original] ?? 0) + 1;
            continue;
        }

        $folder = str_contains($relativePath, '/') ? explode('/', $relativePath, 2)[0] : '';
        $folders[$folder] = true;
        $inLibrary = $folder === MEDIA_LIBRARY_FOLDER;
        $isUsed = isset($referenced[$relativePath]);
        $status = $isUsed ? 'used' : ($inLibrary ? 'library' : 'orphaned');
        if ($status === 'orphaned') {
            $orphanCount++;
        }
        $files[] = [
            'path' => $relativePath,
            'folder' => $folder,
            'size' => $fileInfo->getSize(),
            'mtime' => $fileInfo->getMTime(),
            'used' => $isUsed,
            'status' => $status,
            'kind' => mediaKind($relativePath),
        ];
    }
}

$totalFiles = count($files);
ksort($folders);

// Media is a filesystem listing, not a table, so the search/sort/paging
// happens in PHP here rather than in SQL — same request parameters and
// the same toolbar as every other module, so it behaves identically from
// the user's side.
$params = listParams([
    'modified' => 'mtime',
    'file' => 'path',
    'size' => 'size',
    'status' => 'status',
], 'modified', ['usage', 'folder']);

if (!isset($_GET['dir']) && $params['sort'] === 'modified') {
    $params['dir'] = 'DESC'; // newest first, matching the previous default
}

if ($params['q'] !== '') {
    $needle = mb_strtolower($params['q']);
    $files = array_values(array_filter(
        $files,
        fn(array $f): bool => str_contains(mb_strtolower($f['path']), $needle)
    ));
}

if (isset($params['filters']['usage'])) {
    $wanted = $params['filters']['usage'];
    $files = array_values(array_filter($files, fn(array $f): bool => $f['status'] === $wanted));
}

if (isset($params['filters']['folder'])) {
    $wantedFolder = $params['filters']['folder'];
    $files = array_values(array_filter($files, fn(array $f): bool => $f['folder'] === $wantedFolder));
}

$sortKey = $params['sortSql'];
$direction = $params['dir'] === 'DESC' ? -1 : 1;
usort($files, fn($a, $b) => $direction * ($a[$sortKey] <=> $b[$sortKey]));

$matched = count($files);
$files = array_slice($files, $params['offset'], $params['perPage']);
$isFiltered = $params['q'] !== '' || $params['filters'] !== [];

$statusLabels = ['used' => 'In use', 'library' => 'Library', 'orphaned' => 'Unused'];
$statusBadges = ['used' => 'active', 'library' => 'active', 'orphaned' => 'draft'];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1>Media Manager</h1>
          <?php if (can('media.upload')): ?>
          <a href="upload.php" class="btn-primary">+ Upload Files</a>
          <?php endif; ?>
        </div>

        <div class="welcome-card">
          <p><strong><?= $totalFiles ?></strong> file<?= $totalFiles === 1 ? '' : 's' ?> in <code>admin/uploads/</code>
            — <strong><?= formatBytes($totalBytes) ?></strong> total, including the smaller copies made for phones and fast loading.
            <?php if ($orphanCount > 0): ?>
              <strong><?= $orphanCount ?></strong> unused (uploaded, but no longer shown anywhere) - safe to delete.
            <?php else: ?>
              No unused files.
            <?php endif; ?>
          </p>
          <p class="field-hint">
            <strong>In use</strong>: shown on the site; delete it from its own section (Team, Gallery...) instead.
            <strong>Replace</strong> swaps the file for a new one everywhere it appears.
            <strong>Library</strong>: files you uploaded here to link to (Copy link), kept until you delete them.
          </p>
        </div>

        <?php
          $folderOptions = [];
          foreach (array_keys($folders) as $folder) {
              $folderOptions[$folder] = $folder === '' ? '(top level)' : ucfirst($folder);
          }
        ?>
        <?= listToolbar($params, [
            'usage' => ['label' => 'Files', 'options' => $statusLabels],
            'folder' => ['label' => 'Folders', 'options' => $folderOptions],
        ], 'Search file names…') ?>

        <?php if (!$files): ?>
          <div class="empty-state">
            <?= $isFiltered ? 'No files match that search.' : 'No files uploaded yet.' ?>
          </div>
        <?php else: ?>
          <div class="table-scroll">
            <table class="data-table">
              <thead>
                <tr>
                  <th><span class="sr-only">Preview</span></th>
                  <?= listSortHeader('File', 'file', $params) ?>
                  <?= listSortHeader('Size', 'size', $params) ?>
                  <?= listSortHeader('Modified', 'modified', $params) ?>
                  <?= listSortHeader('Status', 'status', $params) ?>
                  <th><span class="sr-only">Actions</span></th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($files as $file): ?>
                <?php $url = '../../uploads/' . implode('/', array_map('rawurlencode', explode('/', $file['path']))); ?>
                <tr>
                  <td>
                    <?php if ($file['kind'] === 'image'): ?>
                      <img src="<?= e($url) ?>" alt="" class="data-table-avatar media-thumb" loading="lazy">
                    <?php else: ?>
                      <span class="data-table-avatar data-table-avatar--empty media-thumb" aria-hidden="true"><?= $file['kind'] === 'pdf' ? 'PDF' : 'FILE' ?></span>
                    <?php endif; ?>
                  </td>
                  <td class="data-table-primary">
                    <?= e($file['path']) ?>
                    <?php if (!empty($variants[$file['path']])): ?>
                      <span class="media-variants">+ <?= (int) $variants[$file['path']] ?> size<?= $variants[$file['path']] === 1 ? '' : 's' ?></span>
                    <?php endif; ?>
                  </td>
                  <td><?= e(formatBytes($file['size'])) ?></td>
                  <td><?= e(date('M j, Y', $file['mtime'])) ?></td>
                  <td>
                    <span class="status-badge status-badge--<?= $statusBadges[$file['status']] ?>"><?= $statusLabels[$file['status']] ?></span>
                  </td>
                  <td class="data-table-actions">
                    <a href="<?= e($url) ?>" target="_blank" rel="noopener" class="link-edit">View<span class="sr-only"> <?= e($file['path']) ?></span></a>
                    <button type="button" class="link-edit" data-copy-url="<?= e($url) ?>">Copy link</button>
                    <?php if (can('media.upload') && in_array($file['kind'], ['image', 'pdf'], true)): ?>
                      <a href="replace.php?path=<?= e(rawurlencode($file['path'])) ?>" class="link-edit">Replace</a>
                    <?php endif; ?>
                    <?php if (!$file['used'] && can('media.delete')): ?>
                      <form method="post" action="delete.php" data-confirm="Delete &quot;<?= e($file['path']) ?>&quot;<?= !empty($variants[$file['path']]) ? ' and its smaller copies' : '' ?>? This cannot be undone.">
                        <?= csrfField() ?>
                        <input type="hidden" name="path" value="<?= e($file['path']) ?>">
                        <button type="submit" class="link-delete">Delete<span class="sr-only"> <?= e($file['path']) ?></span></button>
                      </form>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              </tbody>
            </table>
          </div>
          <?= listPagination($matched, $params) ?>
        <?php endif; ?>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
