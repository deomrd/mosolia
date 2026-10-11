<?php
// Champs communs a la creation d'un client et a la vente d'un carnet.
// Attend : $types, $carnetType, $carnetMise, $carnetDate, $carnetVersement, $facultatif.
use AvecXY\Domaine\Montant;
$dec = \AvecXY\Noyau\Noyau::instance()->contexte->decimales();
$defaut = $carnetType;
if ($defaut === 0 && !$facultatif) {
    foreach ($types as $ty) { if ((int) $ty['par_defaut'] === 1) { $defaut = (int) $ty['id']; } }
}
?>
<div class="grille">
  <label><?= e(t('carnets.type')) ?>
    <select name="type_id" data-type<?= $facultatif ? '' : ' required' ?>>
      <option value="" data-mode=""><?= e(t($facultatif ? 'carnets.sans_carnet' : 'commun.choisir')) ?></option>
      <?php foreach ($types as $ty): ?>
        <option value="<?= e($ty['id']) ?>" data-mode="<?= e(\AvecXY\Domaine\TypeCarnet::modeAffiche($ty)) ?>" data-prix="<?= e(argent($ty['prix'])) ?>"
          data-min="<?= e(Montant::pourChamp($ty['mise_minimum'])) ?>" data-parts="<?= e($ty['nombre_parts']) ?>"<?= $defaut === (int) $ty['id'] ? ' selected' : '' ?>><?= e($ty['nom']) ?></option>
      <?php endforeach; ?>
    </select>
    <small data-prix-texte data-modele="<?= e(t('carnets.prix_info')) ?>"></small>
  </label>
  <label data-si-type="PARTS"><?= e(t('carnets.mise')) ?>
    <input name="mise" inputmode="decimal" value="<?= e($carnetMise) ?>" data-mise>
    <small data-min-texte data-modele="<?= e(t('carnets.mise_min_info')) ?>"></small>
  </label>
  <?php foreach ($types as $ty): if (($ty['blocage'] ?? null) === null) { continue; } ?>
    <label data-si-type="T<?= e($ty['id']) ?>"><?= e(t('carnets.duree_blocage')) ?>
      <select name="option_blocage">
        <?php foreach (\AvecXY\Domaine\TypeCarnet::options($ty) as $o): ?>
          <option value="<?= e($o['mois']) ?>"<?= (string) ($carnetOption ?? '') === (string) $o['mois'] ? ' selected' : '' ?>><?= e(t('carnets.option_resume', ['mois' => $o['mois'], 'taux' => Montant::afficher($o['taux'], 2)]) . ' ' . t('carnets.taux_base.' . strtolower($ty['taux_base']))) ?></option>
        <?php endforeach; ?>
      </select>
      <small><?= e(t('carnets.duree_blocage_aide')) ?></small>
    </label>
  <?php endforeach; ?>
  <label data-si-type="PARTS LIBRE BLOQUE"><?= e(t('carnets.date_ouverture')) ?>
    <input name="date" type="date" value="<?= e($carnetDate) ?>" max="<?= e(date('Y-m-d')) ?>" required>
  </label>
  <label data-si-type="PARTS"><?= e(t('carnets.parts_du_jour')) ?>
    <input name="versement" inputmode="numeric" value="<?= e($carnetVersement) ?>" placeholder="0" data-versement-parts>
    <small><?= e(t('carnets.versement_aide')) ?></small>
  </label>
  <label data-si-type="LIBRE BLOQUE"><?= e(t('carnets.depot_du_jour')) ?>
    <input name="versement" inputmode="decimal" value="<?= e($carnetVersement) ?>" placeholder="0" data-versement-libre>
    <small><?= e(t('carnets.versement_aide')) ?></small>
  </label>
</div>
