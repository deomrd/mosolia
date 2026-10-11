<?php $parts = $o['carnet_mode'] === 'PARTS'; ?>
<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/carnets/' . $o['carnet_id'])) ?>"><?= e(t('carnets.carnet')) ?> #<?= e($o['carnet_numero']) ?> · <?= e($o['client_code'] . ' ' . $o['client_nom']) ?></a></p>
    <h1><?= e(t('cotisations.corriger')) ?></h1>
    <?php if ($o['statut'] === 'VALIDE'): ?><p class="aide"><?= e(t('cotisations.corriger_validee')) ?></p><?php endif; ?>
  </div>
</header>
<form class="carte" method="post" action="<?= e(url('/cotisations/' . $o['id'])) ?>">
  <?= champ_csrf() ?>
  <div class="grille">
    <label><?= e(t($parts ? 'cotisations.parts' : 'cotisations.montant')) ?>
      <input name="valeur" <?= $parts ? 'type="number" min="1"' : 'inputmode="decimal"' ?> value="<?= e($parts ? $o['nombre_parts'] : \AvecXY\Domaine\Montant::pourChamp($o['montant_total'])) ?>" required>
    </label>
    <label><?= e(t('commun.date')) ?>
      <input name="date" type="date" value="<?= e($o['date_cotisation']) ?>" max="<?= e(date('Y-m-d')) ?>" required>
    </label>
  </div>
  <p class="espace boutons">
    <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
    <a class="bouton clair" href="<?= e(url('/carnets/' . $o['carnet_id'])) ?>"><?= e(t('commun.annuler')) ?></a>
  </p>
</form>
<form class="carte" method="post" action="<?= e(url('/cotisations/' . $o['id'] . '/supprimer')) ?>" data-confirmer="<?= e(t('cotisations.confirmer_suppression')) ?>">
  <?= champ_csrf() ?>
  <h2><?= e(t('commun.zone_suppression')) ?></h2>
  <p class="aide"><?= e(t('cotisations.suppression_aide')) ?></p>
  <label><?= e(t('corbeille.motif')) ?><input name="motif" maxlength="255"></label>
  <p class="espace"><button class="bouton danger"><?= e(t('commun.supprimer')) ?></button></p>
</form>
