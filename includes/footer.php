  </div><!-- /page-body -->
</div><!-- /main-content -->
</div><!-- /layout -->

<!-- ─── JS CDNs ─────────────────────────────────────────── -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<!-- ─── App Scripts ─────────────────────────────────────── -->
<script>
  window.CURRENCY_SYMBOL = '<?= CURRENCY_SYMBOL ?>';
  window.BASE_URL = '<?= BASE_URL ?>';
</script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
<script src="<?= BASE_URL ?>/assets/js/charts.js"></script>
<?php if (isset($loadPosJs) && $loadPosJs): ?>
<script src="<?= BASE_URL ?>/assets/js/pos.js"></script>
<?php endif; ?>

<?php if (isset($inlineJs) && $inlineJs): ?>
<script><?= $inlineJs ?></script>
<?php endif; ?>

</body>
</html>
