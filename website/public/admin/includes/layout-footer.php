      </main>
    </div>
  </div>
  <script src="<?= e($linkBase) ?>assets/js/admin.js" defer></script>
  <?php foreach ($extraScripts ?? [] as $extraScript): ?>
  <script src="<?= e($linkBase . $extraScript) ?>" defer></script>
  <?php endforeach; ?>
</body>
</html>
