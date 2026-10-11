<header class="carte tete-page">
  <div>
    <h1><?= e(t('menu.repartition')) ?></h1>
    <p class="aide"><?= e(t('repartition.aide')) ?></p>
  </div>
</header>

<form class="carte filtres" method="get" action="<?= e(url('/repartition')) ?>">
  <label><?= e(t('commun.mois')) ?><input type="month" name="mois" value="<?= e($periode) ?>"></label>
  <button class="bouton clair"><?= e(t('commun.afficher')) ?></button>
  <span class="aide"><?= e(t('caisse.en_main')) ?> : <strong><?= e(argent($enMain)) ?></strong></span>
</form>

<section class="carte">
  <h2><?= e(t('repartition.du_mois', ['mois' => substr($periode, 5, 2) . '/' . substr($periode, 0, 4)])) ?></h2>
  <?php if ($faite !== null): ?>
    <p><span class="pastille"><?= e(t('paiements.paye_le', ['date' => jour($faite['date_paiement'])])) ?></span> <?= e(t('repartition.base')) ?> : <strong><?= e(argent($faite['montant_base'])) ?></strong></p>
    <div class="defile"><table>
      <thead><tr><th><?= e(t('repartition.beneficiaire')) ?></th><th class="nombre">%</th><th class="nombre"><?= e(t('caisse.montant')) ?></th></tr></thead>
      <tbody><?php foreach ($faite['lignes'] as $l): ?><tr><td><?= e($l['libelle']) ?></td><td class="nombre"><?= e((float) $l['pourcentage']) ?></td><td class="nombre"><?= e(argent($l['montant'])) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <?php if (peut('repartition.payer')): ?><div class="boutons"><?php $typeLigne = 'repartitions'; $idLigne = $faite['id']; $retourLigne = 'mois=' . $periode; require __DIR__ . '/_supprimer.php'; ?></div><?php endif; ?>
  <?php elseif ($projet['regle'] === null): ?>
    <?php if ($projet['charges'] > 0): ?><p><?= e(t('repartition.base')) ?> : <strong><?= e(argent($projet['part'] / 100)) ?></strong> − <?= e(t('repartition.charges')) ?> : <strong><?= e(argent($projet['charges'] / 100)) ?></strong></p><?php endif; ?>
    <p class="aide"><?= e(t('repartition.aucune_regle')) ?></p>
  <?php else: ?>
    <p><?= e(t('repartition.base')) ?> : <strong><?= e(argent($projet['part'] / 100)) ?></strong><?php if ($projet['charges'] > 0): ?> − <?= e(t('repartition.charges')) ?> : <strong><?= e(argent($projet['charges'] / 100)) ?></strong> = <?= e(t('repartition.a_repartir')) ?> : <strong><?= e(argent($projet['base'] / 100)) ?></strong><?php endif; ?> · <?= e(t('repartition.regle', ['nom' => $projet['regle']['nom']])) ?></p>
    <div class="defile"><table>
      <thead><tr><th><?= e(t('repartition.beneficiaire')) ?></th><th class="nombre">%</th><th class="nombre"><?= e(t('caisse.montant')) ?></th></tr></thead>
      <tbody><?php foreach ($projet['lignes'] as $l): ?><tr><td><?= e($l['libelle']) ?></td><td class="nombre"><?= e((float) $l['pourcentage']) ?></td><td class="nombre"><?= e(argent($l['montant'] / 100)) ?></td></tr><?php endforeach; ?></tbody>
    </table></div>
    <?php if (peut('repartition.payer') && $projet['base'] > 0): ?>
    <form class="boutons" method="post" action="<?= e(url('/repartition')) ?>" data-confirmer="<?= e(t('repartition.confirmer', ['montant' => argent($projet['base'] / 100)])) ?>">
      <?= champ_csrf() ?><input type="hidden" name="mois" value="<?= e($periode) ?>"><input type="hidden" name="date" value="<?= e(date('Y-m-d')) ?>">
      <button class="bouton"><?= e(t('repartition.payer')) ?></button>
    </form>
    <?php endif; ?>
  <?php endif; ?>
</section>

<?php if ($historique): ?>
<section class="carte">
  <h2><?= e(t('repartition.historique')) ?></h2>
  <div class="defile"><table>
    <thead><tr><th><?= e(t('commun.mois')) ?></th><th><?= e(t('commun.date')) ?></th><th class="nombre"><?= e(t('caisse.montant')) ?></th></tr></thead>
    <tbody><?php foreach ($historique as $h): ?><tr><td><a href="<?= e(url('/repartition?mois=' . $h['periode'])) ?>"><?= e($h['periode']) ?></a></td><td><?= e(jour($h['date_paiement'])) ?></td><td class="nombre"><?= e(argent($h['total'])) ?></td></tr><?php endforeach; ?></tbody>
  </table></div>
</section>
<?php endif; ?>
