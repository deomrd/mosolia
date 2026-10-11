<?php
$c = static fn (int $v): string => argent($v / 100);
$b = $etats['bilan'];
$action = '/comptabilite/etats';
?>
<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.compta_etats')) ?></h1>
    <p class="aide"><?= e(t('compta.aide')) ?></p>
  </div>
  <?php if ($etats['equilibre']): ?><span class="pastille verte"><?= e(t('compta.equilibre')) ?></span><?php else: ?><span class="pastille rouge"><?= e(t('compta.desequilibre_etat')) ?></span><?php endif; ?>
</header>

<?php require __DIR__ . '/_filtres.php'; ?>

<section class="carte">
  <div class="tuiles">
    <div class="tuile"><span><?= e(t('compta.total_actif')) ?></span><strong><?= e($c($b['actif'])) ?></strong><small><?= e(t('compta.au_date', ['date' => jour($f['au'])])) ?></small></div>
    <div class="tuile <?= $etats['resultat'] >= 0 ? 'fort' : '' ?>"><span><?= e(t($etats['resultat'] >= 0 ? 'compta.benefice' : 'compta.perte')) ?></span><strong><?= e($c($etats['resultat'])) ?></strong><small><?= e(t('compta.periode', ['du' => jour($f['du']), 'au' => jour($f['au'])])) ?></small></div>
    <div class="tuile"><span><?= e(t('compta.total_produits')) ?></span><strong><?= e($c(array_sum(array_column($etats['produits'], 'mouvement')))) ?></strong></div>
    <div class="tuile"><span><?= e(t('compta.total_charges')) ?></span><strong><?= e($c(array_sum(array_column($etats['charges'], 'mouvement')))) ?></strong></div>
  </div>
</section>

<div class="deux-colonnes">
  <section class="carte">
    <h2><?= e(t('compta.bilan')) ?> <small><?= e(t('compta.au_date', ['date' => jour($f['au'])])) ?></small></h2>
    <table class="etat compact">
      <thead><tr><th colspan="2"><?= e(t('compta.nature.actif')) ?></th><th class="nombre"></th></tr></thead>
      <tbody>
      <?php foreach ($b['ACTIF'] as $l): ?><tr><td class="code-compte"><?= e($l['numero']) ?></td><td><?= e($l['libelle']) ?></td><td class="nombre"><?= e($c($l['solde'])) ?></td></tr><?php endforeach; ?>
      </tbody>
      <tfoot><tr><td></td><td><?= e(t('compta.total_actif')) ?></td><td class="nombre"><?= e($c($b['actif'])) ?></td></tr></tfoot>
    </table>
    <table class="etat compact">
      <thead><tr><th colspan="2"><?= e(t('compta.nature.passif')) ?></th><th class="nombre"></th></tr></thead>
      <tbody>
      <?php foreach ($b['PASSIF'] as $l): ?><tr><td class="code-compte"><?= e($l['numero']) ?></td><td><?= e($l['libelle']) ?></td><td class="nombre"><?= e($c($l['solde'])) ?></td></tr><?php endforeach; ?>
        <tr><td></td><td><?= e(t('compta.resultat_anterieur')) ?></td><td class="nombre"><?= e($c($b['resultat_anterieur'])) ?></td></tr>
        <tr><td></td><td><?= e(t('compta.resultat_exercice')) ?></td><td class="nombre"><?= e($c($b['resultat_exercice'])) ?></td></tr>
      </tbody>
      <tfoot><tr><td></td><td><?= e(t('compta.total_passif')) ?></td><td class="nombre"><?= e($c($b['passif_total'])) ?></td></tr></tfoot>
    </table>
  </section>

  <section class="carte">
    <h2><?= e(t('compta.compte_resultat')) ?> <small><?= e(t('compta.periode', ['du' => jour($f['du']), 'au' => jour($f['au'])])) ?></small></h2>
    <table class="etat compact">
      <thead><tr><th colspan="2"><?= e(t('compta.produits')) ?></th><th class="nombre"></th></tr></thead>
      <tbody>
      <?php foreach ($etats['produits'] as $l): if ($l['mouvement'] === 0) { continue; } ?><tr><td class="code-compte"><?= e($l['numero']) ?></td><td><?= e($l['libelle']) ?></td><td class="nombre"><?= e($c($l['mouvement'])) ?></td></tr><?php endforeach; ?>
      </tbody>
      <tfoot><tr><td></td><td><?= e(t('compta.total_produits')) ?></td><td class="nombre"><?= e($c(array_sum(array_column($etats['produits'], 'mouvement')))) ?></td></tr></tfoot>
    </table>
    <table class="etat compact">
      <thead><tr><th colspan="2"><?= e(t('compta.charges')) ?></th><th class="nombre"></th></tr></thead>
      <tbody>
      <?php foreach ($etats['charges'] as $l): if ($l['mouvement'] === 0) { continue; } ?><tr><td class="code-compte"><?= e($l['numero']) ?></td><td><?= e($l['libelle']) ?></td><td class="nombre"><?= e($c($l['mouvement'])) ?></td></tr><?php endforeach; ?>
      </tbody>
      <tfoot><tr><td></td><td><?= e(t('compta.total_charges')) ?></td><td class="nombre"><?= e($c(array_sum(array_column($etats['charges'], 'mouvement')))) ?></td></tr></tfoot>
    </table>
    <p class="resultat-net <?= $etats['resultat'] >= 0 ? 'positif' : 'negatif' ?>"><span><?= e(t($etats['resultat'] >= 0 ? 'compta.benefice' : 'compta.perte')) ?></span><strong><?= e($c($etats['resultat'])) ?></strong></p>
  </section>
</div>
<p class="aide"><?= e(t('compta.note_caisse')) ?></p>
