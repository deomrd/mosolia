<?php
$action = $id === 0 ? '/parametres/credit/nouveau' : '/parametres/credit/' . $id;
$champ = static function (string $nom, string $type = 'text') use ($p): string {
    return '<label>' . e(t('credits.champ.' . $nom)) . '<input name="' . e($nom) . '" value="' . e($p[$nom] ?? '') . '" inputmode="' . ($type === 'nombre' ? 'decimal' : 'text') . '">'
        . '<small>' . e(t('credits.aide.' . $nom)) . '</small></label>';
};
$liste = static function (string $nom, array $valeurs, string $prefixe) use ($p): string {
    $html = '<label>' . e(t('credits.champ.' . $nom)) . '<select name="' . e($nom) . '" data-champ="' . e($nom) . '">';
    foreach ($valeurs as $v) {
        $html .= '<option value="' . e($v) . '"' . (($p[$nom] ?? '') === $v ? ' selected' : '') . '>' . e(t($prefixe . strtolower($v))) . '</option>';
    }
    return $html . '</select><small>' . e(t('credits.aide.' . $nom)) . '</small></label>';
};
$case = static function (string $nom) use ($p): string {
    return '<label class="case"><input type="checkbox" name="' . e($nom) . '" value="1"' . (($p[$nom] ?? '') === '1' ? ' checked' : '') . '><span><strong>' . e(t('credits.champ.' . $nom)) . '</strong><small>' . e(t('credits.aide.' . $nom)) . '</small></span></label>';
};
?>
<form method="post" action="<?= e(url($action)) ?>">
<?= champ_csrf() ?>
<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/parametres/credit')) ?>"><?= e(t('menu.regles_credit')) ?></a></p>
    <h1><?= e($id === 0 ? t('credits.nouvelles_regles') : $p['nom']) ?></h1>
  </div>
  <div class="boutons">
    <a class="bouton clair" href="<?= e(url('/parametres/credit')) ?>"><?= e(t('commun.annuler')) ?></a>
    <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
  </div>
</header>

<section class="carte">
  <h2><?= e(t('credits.section_pret')) ?></h2>
  <div class="grille">
    <label><?= e(t('credits.nom')) ?><input name="nom" value="<?= e($p['nom']) ?>" required maxlength="80" data-majuscules></label>
    <?= $liste('mode_calcul', \AvecXY\Domaine\Echeancier::MODES, 'credits.calcul.') ?>
    <?= $champ('taux_interet', 'nombre') ?><?= $champ('duree_jours', 'nombre') ?>
  </div>
  <p class="aide"><?= e(t('credits.calcul_aide')) ?></p>
  <?= $case('actif') ?>
</section>

<section class="carte">
  <h2><?= e(t('credits.section_frais_garantie')) ?></h2>
  <div class="grille">
    <?= $liste('frais_dossier_type', ['AUCUN', 'FIXE', 'POURCENTAGE'], 'credits.frais_dossier.') ?>
    <?= $champ('frais_dossier_valeur', 'nombre') ?>
    <?= $liste('frais_dossier_mode', ['RETENU', 'AJOUTE'], 'credits.frais_mode.') ?>
    <?= $champ('garantie_pct', 'nombre') ?>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('credits.section_limites')) ?></h2>
  <p class="aide"><?= e(t('credits.vide_non_applique')) ?></p>
  <div class="grille">
    <?= $champ('montant_minimum', 'nombre') ?><?= $champ('montant_maximum', 'nombre') ?><?= $champ('multiple_epargne_max', 'nombre') ?>
    <?= $champ('plafond_encours_pct_caisse', 'nombre') ?><?= $champ('credits_simultanes_max', 'nombre') ?>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('credits.section_eligibilite')) ?></h2>
  <div class="grille"><?= $champ('carnets_fermes_minimum', 'nombre') ?><?= $champ('anciennete_jours_minimum', 'nombre') ?></div>
  <?= $case('carnet_actif_requis') ?><?= $case('sans_retard_anterieur') ?>
</section>

<section class="carte">
  <h2><?= e(t('credits.section_remboursement')) ?></h2>
  <fieldset class="segments">
    <legend><?= e(t('credits.champ.mode_remboursement')) ?></legend>
    <?php foreach (['LIBRE', 'CALENDRIER'] as $v): ?>
      <label><input type="radio" name="mode_remboursement" value="<?= e($v) ?>"<?= ($p['mode_remboursement'] ?? 'LIBRE') === $v ? ' checked' : '' ?>><span><?= e(t('credits.mode.' . strtolower($v))) ?></span></label>
    <?php endforeach; ?>
  </fieldset>
  <p class="aide"><?= e(t('credits.remboursement_libre')) ?></p>
  <p class="aide"><?= e(t('credits.calendrier_aide')) ?></p>
  <div class="grille"><?= $champ('echeances_nombre', 'nombre') ?><?= $champ('echeances_intervalle', 'nombre') ?><?= $liste('echeances_unite', \AvecXY\Domaine\Echeancier::UNITES, 'credits.unite.') ?></div>
  <?= $case('remboursement_epargne') ?>
</section>

<section class="carte">
  <h2><?= e(t('credits.section_decision')) ?></h2>
  <?= $case('accord_agent_requis') ?><?= $case('derogation_autorisee') ?>
</section>

<section class="carte">
  <h2><?= e(t('credits.section_penalites')) ?></h2>
  <?= $case('reconduction') ?>
  <p class="aide"><?= e(t('credits.sans_reconduction')) ?></p>
  <div class="grille">
    <?= $champ('taux_penalite', 'nombre') ?>
    <label><?= e(t('credits.champ.base_penalite')) ?>
      <select name="base_penalite"><?php foreach (['RESTANT', 'CAPITAL'] as $v): ?><option value="<?= e($v) ?>"<?= $p['base_penalite'] === $v ? ' selected' : '' ?>><?= e(t('credits.base.' . strtolower($v))) ?></option><?php endforeach; ?></select>
    </label>
    <label><?= e(t('credits.champ.frequence_penalite')) ?>
      <select name="frequence_penalite"><?php foreach (['MANUELLE', 'JOUR', 'SEMAINE', 'MOIS'] as $v): ?><option value="<?= e($v) ?>"<?= $p['frequence_penalite'] === $v ? ' selected' : '' ?>><?= e(t('credits.frequence.' . strtolower($v))) ?></option><?php endforeach; ?></select>
      <small><?= e(t('credits.aide.frequence_penalite')) ?></small>
    </label>
    <?= $champ('jours_tolerance', 'nombre') ?>
  </div>
</section>
</form>

<?php if (!empty($exemple)): ?>
<section class="carte">
  <h2><?= e(t('credits.exemple_titre', ['montant' => argent($exemple['montant'] / 100)])) ?></h2>
  <p class="aide"><?= e(t('credits.exemple_aide')) ?></p>
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('credits.exemple_recu')) ?></span><strong><?= e(argent($exemple['verse'] / 100)) ?></strong></div>
    <div class="tuile"><span><?= e(t('credits.exemple_interets')) ?></span><strong><?= e(argent($exemple['interet'] / 100)) ?></strong></div>
    <?php if ($exemple['frais'] > 0): ?><div class="tuile"><span><?= e(t('credits.exemple_frais')) ?></span><strong><?= e(argent($exemple['frais'] / 100)) ?></strong><small><?= e(t('credits.frais_mode.' . strtolower($exemple['frais_mode']))) ?></small></div><?php endif; ?>
    <div class="tuile fort"><span><?= e(t('credits.exemple_total')) ?></span><strong><?= e(argent($exemple['total'] / 100)) ?></strong></div>
    <?php if ($exemple['garantie'] > 0): ?><div class="tuile"><span><?= e(t('credits.exemple_garantie')) ?></span><strong><?= e(argent($exemple['garantie'] / 100)) ?></strong></div><?php endif; ?>
  </div>
  <?php if ($exemple['echeances']): $lignesAmortissement = $exemple['echeances']; require __DIR__ . '/../credits/_amortissement.php'; endif; ?>
</section>
<?php endif; ?>
