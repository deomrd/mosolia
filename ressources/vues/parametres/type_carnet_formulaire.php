<?php
use AvecXY\Domaine\Montant;
use AvecXY\Domaine\TypeCarnet;

$id = (int) $ty['id'];
$action = $id === 0 ? '/parametres/types-carnet/nouveau' : '/parametres/types-carnet/' . $id;
// montant venant de la base ("1500.00") ou saisie refusee reaffichee telle quelle
$m = static fn ($v) => $brut ? (string) $v : Montant::pourChamp($v === null ? null : (string) $v);
$choix = static function (string $nom, array $valeurs, string $prefixe, ?string $actuel): string {
    $html = '<select name="' . e($nom) . '" data-champ="' . e($nom) . '">';
    foreach ($valeurs as $v) {
        $html .= '<option value="' . e($v) . '"' . ($actuel === $v ? ' selected' : '') . '>' . e(t($prefixe . strtolower($v))) . '</option>';
    }
    return $html . '</select>';
};
?>
<form method="post" action="<?= e(url($action)) ?>" data-type-carnet
      data-apercu="<?= e(t('carnets.apercu_parts')) ?>" data-apercu-aucun="<?= e(t('carnets.apercu_aucun')) ?>">
<?= champ_csrf() ?>

<header class="carte tete-page">
  <div>
    <p class="fil"><a href="<?= e(url('/parametres/types-carnet')) ?>"><?= e(t('menu.types_carnet')) ?></a></p>
    <h1><?= e($id === 0 ? t('carnets.nouveau') : $ty['nom']) ?></h1>
    <p class="aide"><?= e(t('carnets.aide_modification')) ?></p>
  </div>
  <div class="boutons">
    <a class="bouton clair" href="<?= e(url('/parametres/types-carnet')) ?>"><?= e(t('commun.annuler')) ?></a>
    <button class="bouton"><?= e(t('commun.enregistrer')) ?></button>
  </div>
</header>

<?php if ($id === 0): ?>
<section class="carte">
  <h2><?= e(t('carnets.modeles')) ?></h2>
  <p class="aide"><?= e(t('carnets.modeles_aide')) ?></p>
  <div class="modeles">
    <?php foreach ([
        'carnet' => ['mode' => 'PARTS', 'duree_jours' => '31', 'nombre_parts' => '31', 'taille_tranche' => '31', 'parts_benefice_par_tranche' => '1', 'prelevement_benefice' => 'DEBUT_TRANCHE', 'retrait' => 'TOTAL', 'fermeture' => 'PREMIER_RETRAIT', 'parts_reserve' => '', 'frais_tenue_type' => 'AUCUN'],
        'annuel' => ['mode' => 'PARTS', 'duree_jours' => '', 'nombre_parts' => '372', 'taille_tranche' => '31', 'parts_benefice_par_tranche' => '1', 'prelevement_benefice' => 'DEBUT_TRANCHE', 'retrait' => 'LIBRE', 'fermeture' => 'MANUELLE', 'parts_reserve' => '62', 'frais_tenue_type' => 'AUCUN'],
        'courant' => ['mode' => 'LIBRE', 'duree_jours' => '', 'fermeture' => 'MANUELLE', 'frais_tenue_type' => 'FIXE', 'frais_tenue_frequence' => 'MENSUEL'],
        'bloque' => ['mode' => 'BLOQUE', 'duree_jours' => '', 'fermeture' => 'MANUELLE', 'blocage' => 'RETRAITS', 'taux_base' => 'AN', 'interets_versement' => 'FIN', 'retrait_anticipe' => 'PERTE_INTERETS', 'frais_tenue_type' => 'AUCUN'],
    ] as $cle => $valeurs): ?>
      <button type="button" class="modele" data-modele="<?= e(json_encode($valeurs + ['nom' => mb_strtoupper(t('carnets.modele.' . $cle))])) ?>">
        <strong><?= e(t('carnets.modele.' . $cle)) ?></strong><small><?= e(t('carnets.modele.' . $cle . '_aide')) ?></small>
      </button>
    <?php endforeach; ?>
  </div>
</section>
<?php endif; ?>

<section class="carte">
  <h2><?= e(t('carnets.section_carnet')) ?></h2>
  <fieldset class="segments">
    <legend><?= e(t('carnets.mode')) ?></legend>
    <?php foreach (TypeCarnet::MODES as $mode): ?>
      <label><input type="radio" name="mode" value="<?= e($mode) ?>"<?= $ty['mode'] === $mode ? ' checked' : '' ?>><span><?= e(t('carnets.mode.' . strtolower($mode))) ?></span></label>
    <?php endforeach; ?>
  </fieldset>
  <p class="aide" data-si-mode="PARTS"><?= e(t('carnets.mode_parts_aide')) ?></p>
  <p class="aide" data-si-mode="LIBRE"><?= e(t('carnets.mode_libre_aide')) ?></p>
  <p class="aide" data-si-mode="BLOQUE"><?= e(t('carnets.mode_bloque_aide')) ?></p>
  <div class="grille">
    <label><?= e(t('carnets.nom')) ?>
      <input name="nom" value="<?= e($ty['nom']) ?>" required maxlength="80" data-majuscules>
    </label>
    <label data-si-mode="PARTS LIBRE"><?= e(t('carnets.duree')) ?>
      <input name="duree_jours" type="number" min="0" max="3660" value="<?= e((string) $ty['duree_jours'] === '0' ? '' : $ty['duree_jours']) ?>">
      <small><?= e(t('carnets.duree_aide')) ?></small>
    </label>
    <label><?= e(t('carnets.prix')) ?> (<?= e($devise) ?>)
      <input name="prix" inputmode="decimal" value="<?= e($m($ty['prix'])) ?>" placeholder="0">
    </label>
    <label data-si-mode="PARTS"><?= e(t('carnets.nombre_parts')) ?>
      <input name="nombre_parts" type="number" min="1" max="3660" value="<?= e($ty['nombre_parts']) ?>" data-champ="nombre_parts">
    </label>
    <label data-si-mode="PARTS"><?= e(t('carnets.mise_min')) ?> (<?= e($devise) ?>)
      <input name="mise_minimum" inputmode="decimal" value="<?= e($m($ty['mise_minimum'])) ?>" placeholder="0">
    </label>
    <label data-si-mode="PARTS"><?= e(t('carnets.mise_max')) ?> (<?= e($devise) ?>)
      <input name="mise_maximum" inputmode="decimal" value="<?= e($m($ty['mise_maximum'])) ?>">
      <small><?= e(t('carnets.vide_sans_limite')) ?></small>
    </label>
    <label data-si-mode="LIBRE BLOQUE"><?= e(t('carnets.depot_min')) ?> (<?= e($devise) ?>)
      <input name="depot_minimum" inputmode="decimal" value="<?= e($m($ty['depot_minimum'])) ?>">
      <small><?= e(t('carnets.vide_sans_limite')) ?></small>
    </label>
    <label data-si-mode="LIBRE"><?= e(t('carnets.solde_min')) ?> (<?= e($devise) ?>)
      <input name="solde_minimum" inputmode="decimal" value="<?= e($m($ty['solde_minimum'] ?? null)) ?>">
      <small><?= e(t('carnets.solde_min_aide')) ?></small>
    </label>
  </div>
</section>

<section class="carte" data-si-mode="BLOQUE">
  <h2><?= e(t('carnets.section_blocage')) ?></h2>
  <div class="grille">
    <label><?= e(t('carnets.blocage')) ?>
      <?= $choix('blocage', TypeCarnet::BLOCAGES, 'carnets.blocage.', $ty['blocage'] ?? 'RETRAITS') ?>
    </label>
    <label><?= e(t('carnets.taux_base')) ?>
      <?= $choix('taux_base', TypeCarnet::TAUX_BASES, 'carnets.taux_base.', $ty['taux_base'] ?? 'AN') ?>
    </label>
    <label><?= e(t('carnets.interets_versement')) ?>
      <?= $choix('interets_versement', TypeCarnet::VERSEMENTS, 'carnets.versement.', $ty['interets_versement'] ?? 'FIN') ?>
    </label>
  </div>
  <h3><?= e(t('carnets.options')) ?></h3>
  <p class="aide"><?= e(t('carnets.options_aide')) ?></p>
  <div class="options-blocage">
    <?php $mois = array_values((array) ($ty['options_mois'] ?? [])); $taux = array_values((array) ($ty['options_taux'] ?? [])); ?>
    <?php for ($i = 0; $i < max(5, count($mois) + 1); $i++): ?>
      <div class="grille">
        <label><?= e(t('carnets.option_mois')) ?><input name="options_mois[]" type="number" min="1" max="120" value="<?= e($mois[$i] ?? '') ?>"></label>
        <label><?= e(t('carnets.option_taux')) ?><input name="options_taux[]" inputmode="decimal" value="<?= e($taux[$i] ?? '') ?>"></label>
      </div>
    <?php endfor; ?>
  </div>
  <div class="grille">
    <label><?= e(t('carnets.retrait_anticipe')) ?>
      <?= $choix('retrait_anticipe', TypeCarnet::ANTICIPES, 'carnets.anticipe.', $ty['retrait_anticipe'] ?? 'INTERDIT') ?>
    </label>
    <label><?= e(t('carnets.renouvellement')) ?>
      <?= $choix('renouvellement', ['AUCUN', 'AUTOMATIQUE'], 'carnets.renouvellement.', $ty['renouvellement'] ?? 'AUCUN') ?>
    </label>
    <label data-si-anticipe="PENALITE"><?= e(t('carnets.penalite_anticipe')) ?>
      <input name="penalite_anticipe" inputmode="decimal" value="<?= e($m($ty['penalite_anticipe'] ?? null)) ?>">
    </label>
  </div>
  <p class="aide"><?= e(t('carnets.blocage_aide')) ?></p>
</section>

<section class="carte" data-si-mode="PARTS">
  <h2><?= e(t('carnets.section_benefice')) ?></h2>
  <p class="aide"><?= e(t('carnets.section_benefice_aide')) ?></p>
  <div class="grille">
    <label><?= e(t('carnets.tranche')) ?>
      <input name="taille_tranche" type="number" min="1" value="<?= e($ty['taille_tranche']) ?>" data-champ="taille_tranche">
      <small><?= e(t('carnets.tranche_aide')) ?></small>
    </label>
    <label><?= e(t('carnets.benefice_par_tranche')) ?>
      <input name="parts_benefice_par_tranche" type="number" min="0" value="<?= e($ty['parts_benefice_par_tranche']) ?>" data-champ="parts_benefice_par_tranche">
    </label>
    <label><?= e(t('carnets.prelevement')) ?>
      <?= $choix('prelevement_benefice', TypeCarnet::PRELEVEMENTS, 'carnets.prelevement.', $ty['prelevement_benefice']) ?>
    </label>
  </div>
  <p class="encart" data-apercu-texte role="status"></p>
</section>

<section class="carte">
  <h2><?= e(t('carnets.section_retraits')) ?></h2>
  <div class="grille">
    <label data-si-mode="PARTS"><?= e(t('carnets.retrait')) ?>
      <?= $choix('retrait', TypeCarnet::RETRAITS, 'carnets.retrait.', $ty['retrait']) ?>
    </label>
    <label><?= e(t('carnets.fermeture')) ?>
      <?= $choix('fermeture', TypeCarnet::FERMETURES, 'carnets.fermeture.', $ty['fermeture']) ?>
    </label>
    <label data-si-mode="PARTS" data-si-retrait="LIBRE"><?= e(t('carnets.parts_reserve')) ?>
      <input name="parts_reserve" type="number" min="0" value="<?= e($ty['parts_reserve'] ?? '') ?>">
      <small><?= e(t('carnets.parts_reserve_aide')) ?></small>
    </label>
  </div>
  <p class="aide" data-si-mode="LIBRE BLOQUE"><?= e(t('carnets.retrait_libre_aide')) ?></p>
</section>

<section class="carte">
  <h2><?= e(t('carnets.section_frais')) ?></h2>
  <p class="aide"><?= e(t('carnets.section_frais_aide')) ?></p>
  <div class="grille">
    <label><?= e(t('carnets.frais_type')) ?>
      <?= $choix('frais_tenue_type', TypeCarnet::FRAIS_TYPES, 'carnets.frais.', $ty['frais_tenue_type']) ?>
    </label>
    <label data-si-frais><?= e(t('carnets.frais_valeur')) ?>
      <input name="frais_tenue_valeur" inputmode="decimal" value="<?= e($m($ty['frais_tenue_valeur'])) ?>">
      <small><?= e(t('carnets.frais_valeur_aide', ['devise' => $devise])) ?></small>
    </label>
    <label data-si-frais><?= e(t('carnets.frais_frequence')) ?>
      <?= $choix('frais_tenue_frequence', TypeCarnet::FRAIS_FREQUENCES, 'carnets.frequence.', $ty['frais_tenue_frequence'] ?: 'MENSUEL') ?>
    </label>
  </div>
</section>

<section class="carte">
  <div class="droits">
    <label class="case">
      <input type="checkbox" name="actif" value="1"<?= (string) $ty['actif'] === '1' ? ' checked' : '' ?>>
      <span><?= e(t('carnets.actif')) ?><small><?= e(t('carnets.actif_aide')) ?></small></span>
    </label>
    <label class="case">
      <input type="checkbox" name="par_defaut" value="1"<?= (string) $ty['par_defaut'] === '1' ? ' checked' : '' ?>>
      <span><?= e(t('carnets.par_defaut')) ?><small><?= e(t('carnets.par_defaut_aide')) ?></small></span>
    </label>
  </div>
</section>
</form>
