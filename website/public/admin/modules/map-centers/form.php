<?php
require_once __DIR__ . '/../../includes/bootstrap.php';
require_once __DIR__ . '/_options.php';

requireCapability('content.view', '../../dashboard.php');

$user = currentUser();
$linkBase = '../../';

$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $id !== null;
$existing = null;

if ($isEdit) {
    $stmt = getDb()->prepare('SELECT * FROM map_centers WHERE id = :id AND deleted_at IS NULL LIMIT 1');
    $stmt->execute(['id' => $id]);
    $existing = $stmt->fetch();

    if (!$existing) {
        flashSet('error', 'That center could not be found.');
        redirect('index.php');
    }
}

$pageTitle = $isEdit ? 'Edit Center' : 'Add Center';
$activeNav = 'Map Centers';

$nextOrder = (int) getDb()->query('SELECT COALESCE(MAX(display_order), 0) + 1 FROM map_centers WHERE deleted_at IS NULL')->fetchColumn();
$values = flashInputGet() ?? $existing ?? [
    'lead_name' => '', 'sponsor' => '', 'village' => '', 'district' => 'Lower Chitral',
    'center_model' => 'Entrepreneurial Model', 'latitude' => '', 'longitude' => '',
    'display_order' => $nextOrder, 'status' => 'active',
];

// Sponsors already used on the map, offered as suggestions so a new center's
// sponsor is spelled the same way (the map's sponsor filter and the Honor
// Board link both match on the name).
$sponsorNames = getDb()->query(
    'SELECT DISTINCT sponsor FROM map_centers WHERE deleted_at IS NULL ORDER BY sponsor'
)->fetchAll(PDO::FETCH_COLUMN);

// The pin-placing map (assets/js/map-picker.js), on a local copy of Leaflet.
$extraStyles = ['assets/vendor/leaflet/leaflet.css'];
$extraScripts = ['assets/vendor/leaflet/leaflet.js', 'assets/js/map-picker.js'];

require __DIR__ . '/../../includes/layout-header.php';
?>
        <div class="module-header">
          <h1><?= e($pageTitle) ?></h1>
          <a href="index.php" class="link-back">&larr; Back to Map Centers</a>
        </div>

        <form method="post" action="save.php" class="admin-form">
          <?= csrfField() ?>
          <?php if ($isEdit): ?><input type="hidden" name="id" value="<?= (int) $id ?>"><?php endif; ?>

          <div class="field">
            <label for="village">Village</label>
            <input type="text" id="village" name="village" value="<?= e((string) $values['village']) ?>" required maxlength="255" placeholder="e.g. Dawashish Jughoor Lower Chitral">
            <p class="field-hint">Shown on the center's pin. Ending it with the district, as the existing centers do, reads well there.</p>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="district">District</label>
              <select id="district" name="district" required>
                <?php foreach (MAP_CENTER_DISTRICTS as $district): ?>
                  <option value="<?= e($district) ?>"<?= $values['district'] === $district ? ' selected' : '' ?>><?= e($district) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="field">
              <label for="center_model">Type</label>
              <select id="center_model" name="center_model" required>
                <?php foreach (MAP_CENTER_MODELS as $model): ?>
                  <option value="<?= e($model) ?>"<?= $values['center_model'] === $model ? ' selected' : '' ?>><?= e($model) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="sponsor">Sponsor</label>
              <input type="text" id="sponsor" name="sponsor" value="<?= e((string) $values['sponsor']) ?>" required maxlength="255" list="sponsor-names">
              <datalist id="sponsor-names">
                <?php foreach ($sponsorNames as $name): ?><option value="<?= e($name) ?>"><?php endforeach; ?>
              </datalist>
            </div>
            <div class="field">
              <label for="lead_name">Center lead (optional)</label>
              <input type="text" id="lead_name" name="lead_name" value="<?= e((string) ($values['lead_name'] ?? '')) ?>" maxlength="160" placeholder="The entrepreneur or teacher running it">
            </div>
          </div>

          <div class="field">
            <span class="field-label">Location on the map</span>
            <div id="center-map" class="center-map" data-lat-input="latitude" data-lng-input="longitude"
                 data-centers-url="../../../api/map-centers.php" aria-label="Map: click to place the center's pin, drag the pin to adjust"></div>
            <p class="field-hint" id="center-map-status" aria-live="polite">Click the map where the center is to place its pin, then drag the pin to adjust.</p>
          </div>

          <div class="form-row-2">
            <div class="field">
              <label for="latitude">Latitude</label>
              <input type="text" id="latitude" name="latitude" value="<?= e((string) $values['latitude']) ?>" required inputmode="decimal" placeholder="e.g. 35.838713">
            </div>
            <div class="field">
              <label for="longitude">Longitude</label>
              <input type="text" id="longitude" name="longitude" value="<?= e((string) $values['longitude']) ?>" required inputmode="decimal" placeholder="e.g. 71.791280">
            </div>
          </div>
          <p class="field-hint">
            In Google Maps, right-click the center's spot and click the numbers at the top of the menu to copy them
            (latitude first, e.g. <code>35.838713, 71.791280</code>).
            <?php if ($isEdit): ?>
              <a href="https://www.google.com/maps?q=<?= e((string) (float) $values['latitude']) ?>,<?= e((string) (float) $values['longitude']) ?>" target="_blank" rel="noopener">Check the current position on Google Maps</a>.
            <?php endif; ?>
          </p>

          <div class="form-row-2">
            <div class="field">
              <label for="display_order">Number</label>
              <input type="number" id="display_order" name="display_order" value="<?= e((string) ($values['display_order'] ?? 0)) ?>" step="1">
            </div>
            <div class="field">
              <label for="status">Status</label>
              <select id="status" name="status" required>
                <option value="active"<?= $values['status'] === 'active' ? ' selected' : '' ?>>Active (on the map)</option>
                <option value="draft"<?= $values['status'] === 'draft' ? ' selected' : '' ?>>Draft (hidden)</option>
              </select>
            </div>
          </div>

          <div class="form-actions">
            <button type="submit" class="btn-submit"><?= $isEdit ? 'Save Changes' : 'Add Center' ?></button>
          </div>
        </form>
<?php require __DIR__ . '/../../includes/layout-footer.php'; ?>
