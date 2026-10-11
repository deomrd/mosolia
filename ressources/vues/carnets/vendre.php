<form method="post" action="<?= e(url('/carnets/nouveau')) ?>" data-vente-carnet>
<?= champ_csrf() ?>
<input type="hidden" name="client_id" value="<?= e($client['id']) ?>">
<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/clients/' . $client['id'])) ?>"><?= e($client['code'] . ' ' . $client['nom']) ?></a></p>
    <h1><?= e(t('carnets.vendre')) ?></h1>
  </div>
  <div class="boutons">
    <a class="bouton clair" href="<?= e(url('/clients/' . $client['id'])) ?>"><?= e(t('commun.annuler')) ?></a>
    <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
  </div>
</header>
<section class="carte">
  <?php if (!$types): ?>
    <p class="aide"><?= e(t('carnets.aucun_type')) ?></p>
  <?php else: $carnetDate = date('Y-m-d'); $carnetType = 0; $carnetMise = ''; $carnetVersement = ''; $facultatif = false;
    require __DIR__ . '/_champs_vente.php'; endif; ?>
</section>
</form>
