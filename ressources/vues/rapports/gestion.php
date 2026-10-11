<?php
use AvecXY\Application\ServiceIndicateurs;
$c = static fn (int $v): string => argent($v / 100);
$pct = static fn (?float $v): string => $v === null ? '—' : number_format($v, 2, ',', ' ') . ' %';
$r = $i['remboursement'];
?>
<header class="carte tete-page">
  <div><h1><?= e(t('menu.rapports_gestion')) ?></h1><p class="aide"><?= e(t('gestion.aide')) ?></p></div>
  <a class="bouton clair seulement-ordinateur" href="<?= e(url('/indicateurs/export?' . http_build_query(['du' => $f['du'], 'au' => $f['au'], 'agence' => $f['agence'] ?? '']))) ?>"><?= e(t('compta.exporter')) ?></a>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/indicateurs')) ?>">
  <label><?= e(t('commun.du')) ?><input type="date" name="du" value="<?= e($f['du']) ?>"></label>
  <label><?= e(t('commun.au')) ?><input type="date" name="au" value="<?= e($f['au']) ?>"></label>
  <?php if ($f['agences']): ?>
  <label><?= e(t('agences.agence')) ?>
    <select name="agence">
      <option value=""><?= e(t('agences.toutes')) ?></option>
      <option value="0"<?= $f['agence'] === 0 ? ' selected' : '' ?>><?= e(t('agences.siege')) ?></option>
      <?php foreach ($f['agences'] as $ag): ?><option value="<?= e($ag['id']) ?>"<?= $f['agence'] === (int) $ag['id'] ? ' selected' : '' ?>><?= e($ag['nom']) ?></option><?php endforeach; ?>
    </select>
  </label>
  <?php endif; ?>
  <button class="bouton clair"><?= e(t('commun.afficher')) ?></button>
</form>

<section class="carte">
  <h2><?= e(t('gestion.portefeuille')) ?> <small><?= e(t('compta.au_date', ['date' => jour($f['au'])])) ?></small></h2>
  <div class="tuiles">
    <div class="tuile fort"><span><?= e(t('gestion.encours_capital')) ?></span><strong><?= e($c($i['encours'])) ?></strong><small><?= e(t('gestion.n_credits', ['n' => $i['nombre_credits']])) ?></small></div>
    <?php foreach ([1, 30, 90] as $s): $niveau = $i['par_pct'][$s] > 10 ? 'alerte-forte' : ($i['par_pct'][$s] > 5 ? 'alerte' : ''); ?>
      <div class="tuile <?= $niveau ?>"><span><?= e(t('gestion.par', ['n' => $s])) ?></span><strong><?= e($pct($i['par_pct'][$s])) ?></strong><small><?= e($c($i['par'][$s])) ?></small></div>
    <?php endforeach; ?>
  </div>
  <p class="aide"><?= e(t('gestion.par_aide')) ?></p>
  <div class="deux-colonnes">
    <div class="defile"><table class="etat compact">
      <thead><tr><th><?= e(t('gestion.anciennete_retards')) ?></th><th class="nombre"><?= e(t('gestion.nombre')) ?></th><th class="nombre"><?= e(t('gestion.capital_restant')) ?></th></tr></thead>
      <tbody><?php foreach ($i['tranches'] as $cle => $tr): ?><tr><td><?= e(t('gestion.tranche.' . $cle)) ?></td><td class="nombre"><?= e($tr['nombre']) ?></td><td class="nombre"><?= e($c($tr['capital'])) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <div class="defile"><table class="etat compact">
      <thead><tr><th><?= e(t('gestion.par_produit')) ?></th><th class="nombre"><?= e(t('gestion.capital_restant')) ?></th><th class="nombre"><?= e(t('gestion.en_retard')) ?></th></tr></thead>
      <tbody>
      <?php if (!$i['produits']): ?><tr><td colspan="3" class="aide"><?= e(t('gestion.aucun_credit')) ?></td></tr><?php endif; ?>
      <?php foreach ($i['produits'] as $p): ?><tr><td><?= e($p['nom']) ?><br><small><?= e(t('gestion.n_credits', ['n' => $p['nombre']])) ?></small></td><td class="nombre"><?= e($c($p['capital'])) ?></td><td class="nombre"><?= e($c($p['en_retard'])) ?></td></tr><?php endforeach; ?>
      </tbody>
    </table></div>
  </div>
</section>

<section class="carte">
  <h2><?= e(t('gestion.flux')) ?> <small><?= e(t('compta.periode', ['du' => jour($f['du']), 'au' => jour($f['au'])])) ?></small></h2>
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('gestion.flux.decaisse')) ?></span><strong><?= e($c($i['flux']['decaisse'])) ?></strong><small><?= e(t('gestion.n_credits', ['n' => $i['flux']['decaisse_nombre']])) ?></small></div>
    <div class="tuile"><span><?= e(t('gestion.taux_remboursement')) ?></span><strong><?= e($pct($r['taux'])) ?></strong><small><?= e(t('gestion.taux_detail', ['paye' => $c($r['paye']), 'du' => $c($r['du'])])) ?></small></div>
    <div class="tuile"><span><?= e(t('gestion.flux.interets_recus')) ?></span><strong><?= e($c($i['flux']['interets_recus'])) ?></strong><small><?= e(t('gestion.dont_penalites_frais', ['penalites' => $c($i['flux']['penalites']), 'frais' => $c($i['flux']['frais_dossier'])])) ?></small></div>
    <div class="tuile"><span><?= e(t('gestion.flux.interets_verses')) ?></span><strong><?= e($c($i['flux']['interets_verses'])) ?></strong><small><?= e(t('gestion.sur_epargne')) ?></small></div>
  </div>
</section>

<div class="deux-colonnes">
<section class="carte">
  <h2><?= e(t('gestion.epargne_par_produit')) ?></h2>
  <div class="defile"><table class="etat compact">
    <thead><tr><th><?= e(t('carnets.type')) ?></th><th class="nombre masque-tel"><?= e(t('gestion.nombre')) ?></th><th class="nombre"><?= e(t('gestion.epargne')) ?></th></tr></thead>
    <tbody><?php foreach ($i['epargne'] as $e): ?><tr><td><?= e($e['nom']) ?><br><small><?= e(t('carnets.mode.' . strtolower($e['mode_affiche']))) ?></small></td><td class="nombre masque-tel"><?= e($e['nombre']) ?></td><td class="nombre"><?= e($c($e['epargne'])) ?></td></tr><?php endforeach; ?></tbody>
    <tfoot><tr><td><?= e(t('commun.total')) ?></td><td class="masque-tel"></td><td class="nombre"><?= e($c($i['epargne_totale'])) ?></td></tr></tfoot>
  </table></div>
</section>
<section class="carte">
  <h2><?= e(t('gestion.clientele')) ?></h2>
  <div class="tuiles">
    <?php foreach (['total', 'femmes', 'hommes', 'emprunteurs', 'avec_piece'] as $cle): ?>
      <div class="tuile"><span><?= e(t('gestion.clients.' . $cle)) ?></span><strong><?= e($i['clients'][$cle] ?? 0) ?></strong></div>
    <?php endforeach; ?>
  </div>
</section>
</div>
