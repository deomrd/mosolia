<section class="carte">
  <h1><?= e($titre) ?></h1>
  <p><?= e($texte) ?></p>
  <?php if ($detail !== ''): ?><pre class="detail"><?= e($detail) ?></pre><?php endif; ?>
  <p><a class="bouton" href="<?= e(url('/')) ?>"><?= e(t('commun.retour_accueil')) ?></a></p>
</section>
